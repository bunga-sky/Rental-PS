<?php
// ============================================================
// MIDTRANS PAYMENT NOTIFICATION (WEBHOOK)
// POST /payment/notification.php
//
// !! File ini TIDAK butuh session/login !!
// !! Dipanggil langsung oleh server Midtrans !!
//
// KEAMANAN:
//   1. Verifikasi signature SHA512
//   2. Double-check status via Midtrans API
//   3. Idempotency via sp_process_payment_notification
//   4. IP whitelist (production)
// ============================================================

require_once __DIR__ . '/../config.php';

// Selalu return JSON ke Midtrans
header('Content-Type: application/json');

// Matikan output error ke response (log saja)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// ---- 1. Baca raw payload ----
$rawBody = file_get_contents('php://input');
if (!$rawBody) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Empty payload']);
    exit;
}

$payload = json_decode($rawBody, true);
if (!$payload) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

// ---- 2. Ambil field penting ----
$orderId           = $payload['order_id']           ?? '';
$transactionId     = $payload['transaction_id']     ?? '';
$transactionStatus = $payload['transaction_status'] ?? '';
$statusCode        = $payload['status_code']        ?? '';
$grossAmount       = $payload['gross_amount']        ?? '';
$paymentType       = $payload['payment_type']        ?? '';
$signatureKey      = $payload['signature_key']       ?? '';

if (!$orderId || !$transactionId || !$transactionStatus) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

// ---- 3. IP Whitelist (production only) ----
if (!isRequestFromMidtrans()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

// ---- 4. Log semua notifikasi masuk (SEBELUM proses apapun) ----
$ipAddress    = $_SERVER['REMOTE_ADDR'] ?? null;
$isSignValid  = verifyMidtransSignature($orderId, $statusCode, $grossAmount, $signatureKey) ? 1 : 0;

$stmtLog = $conn->prepare("
    INSERT INTO payment_notifications
        (order_id, transaction_id, status, raw_payload, signature_key, is_valid, ip_address)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$rawJson = json_encode($payload);
$stmtLog->bind_param('sssssls',
    $orderId, $transactionId, $transactionStatus,
    $rawJson, $signatureKey, $isSignValid, $ipAddress
);
$stmtLog->execute();
$notificationId = (int) $conn->insert_id;

// ---- 5. Verifikasi signature ----
if (!$isSignValid) {
    // Signature invalid — kemungkinan bukan dari Midtrans
    // Tetap return 200 agar Midtrans tidak retry, tapi tidak proses
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'message' => 'Invalid signature']);
    exit;
}

// ---- 6. Double-check via Midtrans API (server-to-server) ----
// Jangan percaya payload saja — verifikasi ke Midtrans langsung
$verifiedStatus = getMidtransTransactionStatus($orderId);

if (!$verifiedStatus) {
    // Midtrans API tidak bisa diakses — proses pakai payload saja
    // tapi catat sebagai warning
    error_log("[Midtrans] Gagal verifikasi API untuk order: $orderId");
    $verifiedTransactionStatus = $transactionStatus;
    $verifiedFraudStatus       = $payload['fraud_status'] ?? null;
} else {
    $verifiedTransactionStatus = $verifiedStatus['transaction_status'] ?? $transactionStatus;
    $verifiedFraudStatus       = $verifiedStatus['fraud_status']       ?? null;
}

// ---- 7. Handle fraud status (kartu kredit) ----
// capture + fraud = challenge → jangan langsung confirm
if ($verifiedTransactionStatus === 'capture') {
    if ($verifiedFraudStatus === 'accept') {
        $verifiedTransactionStatus = 'capture'; // aman, proses
    } elseif ($verifiedFraudStatus === 'challenge') {
        // Butuh review manual — set pending dulu
        $verifiedTransactionStatus = 'pending';
        error_log("[Midtrans] Fraud challenge untuk order: $orderId");
    }
}

// ---- 8. Panggil stored procedure (idempotent) ----
$stmt = $conn->prepare("
    CALL sp_process_payment_notification(?, ?, ?, ?, ?, @result_code, @result_msg)
");
$stmt->bind_param('ssssi',
    $orderId, $transactionId,
    $verifiedTransactionStatus,
    $paymentType, $notificationId
);
$stmt->execute();

$result     = $conn->query("SELECT @result_code AS code, @result_msg AS msg");
$out        = $result->fetch_assoc();
$resultCode = (int) $out['code'];
$resultMsg  =       $out['msg'];

// ---- 9. Update notification sebagai processed ----
$stmtDone = $conn->prepare("
    UPDATE payment_notifications SET is_processed = 1, processed_at = NOW() WHERE id = ?
");
$stmtDone->bind_param('i', $notificationId);
$stmtDone->execute();

// ---- 10. Post-payment actions ----
if ($resultCode === 0 && in_array($verifiedTransactionStatus, ['settlement', 'capture'], true)) {
    // Ambil booking_id
    $stmtBk = $conn->prepare("SELECT id, user_id FROM bookings WHERE order_id = ?");
    $stmtBk->bind_param('s', $orderId);
    $stmtBk->execute();
    $bk = $stmtBk->get_result()->fetch_assoc();

    if ($bk) {
        logActivity($conn, $bk['user_id'], 'payment.success', 'booking', $bk['id'],
            "Payment success via $paymentType — order $orderId");

        // TODO: Kirim email konfirmasi ke user
        // sendBookingConfirmationEmail($bk['user_id'], $orderId);
    }
}

if (in_array($verifiedTransactionStatus, ['expire', 'cancel', 'deny'], true)) {
    $stmtBk = $conn->prepare("SELECT id, user_id FROM bookings WHERE order_id = ?");
    $stmtBk->bind_param('s', $orderId);
    $stmtBk->execute();
    $bk = $stmtBk->get_result()->fetch_assoc();
    if ($bk) {
        logActivity($conn, $bk['user_id'], 'payment.failed', 'booking', $bk['id'],
            "Payment $verifiedTransactionStatus — order $orderId");
    }
}

// ---- 11. Response ke Midtrans ----
// Selalu 200 — jika kita return non-200, Midtrans akan retry terus
http_response_code(200);
echo json_encode([
    'status'      => $resultCode === 0 ? 'success' : ($resultCode === 1 ? 'duplicate' : 'error'),
    'message'     => $resultMsg,
    'order_id'    => $orderId,
    'tx_status'   => $verifiedTransactionStatus,
]);
exit;

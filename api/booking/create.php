<?php
// ============================================================
// API: Buat Booking Baru
// POST /api/booking/create.php
// Body: { ps_id, booking_date, start_time, duration }
// ============================================================

require_once __DIR__ . '/../../api/middleware.php';
requireMethod('POST');

$body = getJsonBody();
requireFields($body, ['ps_id', 'booking_date', 'start_time', 'duration']);

$psId        = (int)   $body['ps_id'];
$bookingDate = trim($body['booking_date']);
$startTime   = trim($body['start_time']);
$duration    = (int)   $body['duration'];

// Validasi
if ($bookingDate < date('Y-m-d')) {
    jsonResponse(['status' => 'error', 'message' => 'Tanggal tidak boleh lampau'], 422);
}
if ($duration < MIN_BOOKING_DURATION || $duration > MAX_BOOKING_DURATION) {
    jsonResponse(['status' => 'error', 'message' => 'Durasi tidak valid'], 422);
}

$endTime = date('H:i:s', strtotime($startTime . ' +' . $duration . ' hours'));

if ($startTime < OPEN_HOUR || $endTime > CLOSE_HOUR . ':00') {
    jsonResponse([
        'status'  => 'error',
        'message' => 'Di luar jam operasional (' . OPEN_HOUR . ' - ' . CLOSE_HOUR . ')'
    ], 422);
}

// Cek PS ada & tidak maintenance
$stmtPs = $conn->prepare("SELECT type FROM playstation WHERE id = ? AND status != 'maintenance'");
$stmtPs->bind_param('i', $psId);
$stmtPs->execute();
$ps = $stmtPs->get_result()->fetch_assoc();

if (!$ps) {
    jsonResponse(['status' => 'error', 'message' => 'PlayStation tidak tersedia'], 400);
}

// Hitung harga
$pricePerHour = ($ps['type'] === 'PS4') ? PS4_PRICE_PER_HOUR : PS5_PRICE_PER_HOUR;
$totalPrice   = $pricePerHour * $duration;

// Generate order_id (idempotency key)
$orderId = generateOrderId($currentUserId);

// Panggil stored procedure (atomic + race condition safe)
$stmt = $conn->prepare("
    CALL sp_create_booking(?, ?, ?, ?, ?, ?, ?, @booking_id, @result_code, @result_msg)
");
$stmt->bind_param('iiissssd',
    $currentUserId, $psId, $psId,
    $bookingDate, $startTime, $endTime,
    $orderId, $totalPrice
);

// Fix binding — sp_create_booking punya 7 IN params
$stmt = $conn->prepare("
    CALL sp_create_booking(?, ?, ?, ?, ?, ?, ?, @booking_id, @result_code, @result_msg)
");
$stmt->bind_param('iissss d',
    $currentUserId, $psId,
    $bookingDate, $startTime, $endTime,
    $orderId, $totalPrice
);
$stmt->execute();

// Ambil OUT params
$result = $conn->query("SELECT @booking_id AS booking_id, @result_code AS result_code, @result_msg AS result_msg");
$out    = $result->fetch_assoc();

$resultCode = (int) $out['result_code'];
$bookingId  = (int) $out['booking_id'];
$resultMsg  =       $out['result_msg'];

if ($resultCode === 1) {
    // Jadwal konflik
    jsonResponse(['status' => 'error', 'message' => $resultMsg], 409);
}

if ($resultCode === 2) {
    // Idempotent: order_id sudah ada, return booking yang ada
    // Ini normal jika user double-submit
    $stmtExisting = $conn->prepare("
        SELECT id, order_id, snap_token, payment_status, expired_at
        FROM bookings WHERE order_id = ?
    ");
    $stmtExisting->bind_param('s', $orderId);
    $stmtExisting->execute();
    $existing = $stmtExisting->get_result()->fetch_assoc();

    jsonResponse([
        'status'     => 'success',
        'message'    => 'Booking sudah ada (idempotent)',
        'booking_id' => $existing['id'],
        'order_id'   => $existing['order_id'],
        'snap_token' => $existing['snap_token'],
        'expired_at' => $existing['expired_at'],
    ]);
}

if ($resultCode !== 0) {
    jsonResponse(['status' => 'error', 'message' => $resultMsg ?: 'Booking gagal'], 500);
}

// Log aktivitas
logActivity($conn, $currentUserId, 'booking.created', 'booking', $bookingId,
    "Booking PS ID $psId tanggal $bookingDate $startTime-$endTime");

jsonResponse([
    'status'      => 'success',
    'message'     => 'Booking berhasil dibuat, lanjutkan ke pembayaran',
    'booking_id'  => $bookingId,
    'order_id'    => $orderId,
    'total_price' => $totalPrice,
    'formatted'   => 'Rp ' . number_format($totalPrice, 0, ',', '.'),
    'expired_at'  => date('Y-m-d H:i:s', strtotime('+' . BOOKING_EXPIRE_MINUTES . ' minutes')),
    'next_step'   => APP_URL . '/payment/process.php?booking_id=' . $bookingId,
]);

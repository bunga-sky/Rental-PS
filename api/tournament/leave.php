<?php
// ============================================================
// API: Leave Tournament
// POST /api/tournament/leave.php
// Body: { tournament_id }
// ============================================================

require_once __DIR__ . '/../../api/middleware.php';
requireMethod('POST');

$body = getJsonBody();
requireFields($body, ['tournament_id']);

$tournamentId = (int) $body['tournament_id'];

// Cek tournament masih upcoming
$stmt = $conn->prepare("SELECT id, name, status, entry_fee FROM tournaments WHERE id = ?");
$stmt->bind_param('i', $tournamentId);
$stmt->execute();
$tournament = $stmt->get_result()->fetch_assoc();

if (!$tournament) {
    jsonResponse(['status' => 'error', 'message' => 'Tournament tidak ditemukan'], 404);
}
if ($tournament['status'] !== 'upcoming') {
    jsonResponse(['status' => 'error', 'message' => 'Tidak bisa keluar dari tournament yang sudah berjalan'], 400);
}

// Cek user memang terdaftar
$stmtCheck = $conn->prepare("
    SELECT id FROM tournament_participants WHERE tournament_id = ? AND user_id = ?
");
$stmtCheck->bind_param('ii', $tournamentId, $currentUserId);
$stmtCheck->execute();
$participant = $stmtCheck->get_result()->fetch_assoc();

if (!$participant) {
    jsonResponse(['status' => 'error', 'message' => 'Kamu tidak terdaftar di tournament ini'], 404);
}

$conn->begin_transaction();
try {
    // Hapus dari participants
    $stmtLeave = $conn->prepare("
        DELETE FROM tournament_participants WHERE tournament_id = ? AND user_id = ?
    ");
    $stmtLeave->bind_param('ii', $tournamentId, $currentUserId);
    $stmtLeave->execute();

    // Jika ada entry fee yang sudah dibayar → buat refund request
    $refundMessage = null;
    if ((float) $tournament['entry_fee'] > 0) {
        $stmtPay = $conn->prepare("
            SELECT tp.id AS tp_id, p.id AS payment_id
            FROM tournament_payments tp
            LEFT JOIN payments p ON tp.order_id = p.order_id
            WHERE tp.tournament_id = ? AND tp.user_id = ? AND tp.status = 'paid'
        ");
        $stmtPay->bind_param('ii', $tournamentId, $currentUserId);
        $stmtPay->execute();
        $pay = $stmtPay->get_result()->fetch_assoc();

        if ($pay && $pay['payment_id']) {
            $reason = 'Keluar dari tournament ' . $tournament['name'];
            $stmtRefund = $conn->prepare("
                INSERT INTO refunds (payment_id, booking_id, user_id, amount, reason, status, refund_type)
                VALUES (?, NULL, ?, ?, ?, 'pending', 'full')
            ");
            $stmtRefund->bind_param('iids',
                $pay['payment_id'], $currentUserId,
                $tournament['entry_fee'], $reason
            );
            $stmtRefund->execute();
            $refundMessage = 'Refund entry fee akan diproses dalam 1-3 hari kerja';
        }
    }

    logActivity($conn, $currentUserId, 'tournament.left', 'tournaments', $tournamentId,
        "Keluar dari tournament: {$tournament['name']}");

    $conn->commit();
    jsonResponse([
        'status'  => 'success',
        'message' => 'Berhasil keluar dari tournament.',
        'refund'  => $refundMessage,
    ]);
} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(['status' => 'error', 'message' => 'Gagal keluar dari tournament'], 500);
}

<?php
// ============================================================
// API: Cancel Booking
// POST /api/booking/cancel.php
// Body: { booking_id }
// ============================================================

require_once __DIR__ . '/../../api/middleware.php';
requireMethod('POST');

$body = getJsonBody();
requireFields($body, ['booking_id']);

$bookingId = (int) $body['booking_id'];

// Ambil booking — pastikan milik user ini
$stmt = $conn->prepare("
    SELECT b.*, p.name AS ps_name, py.id AS payment_id, py.status AS midtrans_status
    FROM bookings b
    LEFT JOIN playstation p  ON b.ps_id    = p.id
    LEFT JOIN payments    py ON b.order_id = py.order_id
    WHERE b.id = ? AND b.user_id = ?
");
$stmt->bind_param('ii', $bookingId, $currentUserId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    jsonResponse(['status' => 'error', 'message' => 'Booking tidak ditemukan'], 404);
}

// Cek status booking
if ($booking['status'] === 'cancelled') {
    jsonResponse(['status' => 'error', 'message' => 'Booking sudah dibatalkan'], 400);
}
if ($booking['status'] === 'completed') {
    jsonResponse(['status' => 'error', 'message' => 'Booking yang sudah selesai tidak bisa dibatalkan'], 400);
}

// Cek apakah sudah bayar → perlu refund
$needsRefund = ($booking['payment_status'] === 'paid');

$conn->begin_transaction();
try {
    // Update booking jadi cancelled
    $stmtCancel = $conn->prepare("
        UPDATE bookings
        SET status = 'cancelled', payment_status = CASE WHEN payment_status = 'paid' THEN 'refunded' ELSE payment_status END,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmtCancel->bind_param('i', $bookingId);
    $stmtCancel->execute();

    // Nonaktifkan schedule
    $stmtSched = $conn->prepare("
        UPDATE playstation_schedules SET is_active = 0 WHERE booking_id = ?
    ");
    $stmtSched->bind_param('i', $bookingId);
    $stmtSched->execute();

    if ($needsRefund && $booking['payment_id']) {
        // Buat refund request
        $reason = $body['reason'] ?? 'Dibatalkan oleh customer';
        $stmtRefund = $conn->prepare("
            INSERT INTO refunds (payment_id, booking_id, user_id, amount, reason, status, refund_type)
            VALUES (?, ?, ?, ?, ?, 'pending', 'full')
        ");
        $stmtRefund->bind_param('iiids',
            $booking['payment_id'], $bookingId,
            $currentUserId, $booking['total_price'], $reason
        );
        $stmtRefund->execute();

        // Log aktivitas
        logActivity($conn, $currentUserId, 'booking.cancelled_with_refund', 'booking', $bookingId,
            "Cancel booking + refund request Rp " . number_format($booking['total_price'], 0, ',', '.'));

        $conn->commit();
        jsonResponse([
            'status'  => 'success',
            'message' => 'Booking dibatalkan. Refund sedang diproses oleh admin.',
            'refund'  => [
                'amount'    => $booking['total_price'],
                'formatted' => 'Rp ' . number_format($booking['total_price'], 0, ',', '.'),
                'status'    => 'pending',
                'note'      => 'Refund akan diproses dalam 1-3 hari kerja',
            ],
        ]);
    } else {
        // Belum bayar, langsung cancel
        logActivity($conn, $currentUserId, 'booking.cancelled', 'booking', $bookingId,
            "Cancel booking (belum bayar)");

        $conn->commit();
        jsonResponse([
            'status'  => 'success',
            'message' => 'Booking berhasil dibatalkan',
        ]);
    }
} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(['status' => 'error', 'message' => 'Gagal membatalkan booking'], 500);
}

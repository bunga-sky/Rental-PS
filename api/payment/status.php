<?php
// ============================================================
// API: Cek Status Payment
// GET /api/payment/status.php?order_id=RENTAL-12-xxx
// Dipakai frontend untuk polling status setelah user bayar
// ============================================================

require_once __DIR__ . '/../../api/middleware.php';
requireMethod('GET');

$orderId = isset($_GET['order_id']) ? trim($_GET['order_id']) : '';

if (!$orderId) {
    jsonResponse(['status' => 'error', 'message' => 'order_id wajib diisi'], 422);
}

// Ambil booking + payment
$stmt = $conn->prepare("
    SELECT
        b.id, b.order_id, b.booking_date, b.start_time, b.end_time,
        b.total_price, b.status, b.payment_status, b.payment_method,
        b.paid_at, b.expired_at,
        p.name AS ps_name, p.type AS ps_type,
        py.snap_token, py.transaction_id, py.status AS midtrans_status
    FROM bookings b
    LEFT JOIN playstation p  ON b.ps_id    = p.id
    LEFT JOIN payments    py ON b.order_id = py.order_id
    WHERE b.order_id = ? AND b.user_id = ?
");
$stmt->bind_param('si', $orderId, $currentUserId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    jsonResponse(['status' => 'error', 'message' => 'Order tidak ditemukan'], 404);
}

// Jika masih unpaid, cek apakah sudah expired
if ($booking['payment_status'] === 'unpaid' || $booking['payment_status'] === 'pending') {
    if ($booking['expired_at'] && $booking['expired_at'] < date('Y-m-d H:i:s')) {
        $booking['payment_status'] = 'expired';
    }
}

jsonResponse([
    'status'  => 'success',
    'booking' => [
        'id'             => $booking['id'],
        'order_id'       => $booking['order_id'],
        'ps_name'        => $booking['ps_name'],
        'ps_type'        => $booking['ps_type'],
        'booking_date'   => $booking['booking_date'],
        'start_time'     => substr($booking['start_time'], 0, 5),
        'end_time'       => substr($booking['end_time'], 0, 5),
        'total_price'    => $booking['total_price'],
        'formatted'      => 'Rp ' . number_format($booking['total_price'], 0, ',', '.'),
        'status'         => $booking['status'],
        'payment_status' => $booking['payment_status'],
        'payment_method' => $booking['payment_method'],
        'paid_at'        => $booking['paid_at'],
        'expired_at'     => $booking['expired_at'],
        'snap_token'     => $booking['snap_token'],
        'transaction_id' => $booking['transaction_id'],
    ],
]);

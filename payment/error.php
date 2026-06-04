<?php
// ============================================================
// PAYMENT ERROR
// Pembayaran gagal / ditolak
// GET /payment/error.php?order_id=RENTAL-xxx
// ============================================================

require_once __DIR__ . '/../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$currentUserId = (int) $_SESSION['user_id'];
$orderId       = isset($_GET['order_id']) ? trim($_GET['order_id']) : '';

if (!$orderId) {
    header('Location: ../user/booking.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT b.*, p.name AS ps_name, p.type AS ps_type, py.payment_type
    FROM bookings b
    LEFT JOIN playstation p  ON b.ps_id    = p.id
    LEFT JOIN payments    py ON b.order_id = py.order_id
    WHERE b.order_id = ? AND b.user_id = ?
");
$stmt->bind_param('si', $orderId, $currentUserId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

// Jika ternyata sudah berhasil bayar (race condition antara redirect & webhook)
if ($booking && $booking['payment_status'] === 'paid') {
    header('Location: finish.php?order_id=' . urlencode($orderId));
    exit;
}

include '../includes/header.php';
?>

<div class="container" style="max-width: 600px; margin: 2rem auto; text-align: center;">
    <div class="card">
        <div style="font-size: 4rem; margin-bottom: 1rem;">❌</div>
        <h2 style="color: #f56565; margin-bottom: .5rem;">Pembayaran Gagal</h2>
        <p style="color: #666; margin-bottom: 2rem;">
            Pembayaran tidak berhasil diproses. Booking kamu belum dikonfirmasi.
        </p>

        <?php if ($booking): ?>
        <div style="background: #fff5f5; border: 1px solid #fed7d7; border-radius: 8px; padding: 1.2rem; text-align:left; margin-bottom: 2rem;">
            <p style="margin:0; color:#666;">Order ID: <strong style="font-size:.85rem;"><?php echo htmlspecialchars($booking['order_id']); ?></strong></p>
            <p style="margin:.5rem 0 0; color:#666;">PlayStation: <strong><?php echo htmlspecialchars($booking['ps_name']); ?></strong></p>
            <p style="margin:.5rem 0 0; color:#666;">Tanggal: <strong><?php echo date('d F Y', strtotime($booking['booking_date'])); ?></strong></p>
            <p style="margin:.5rem 0 0; color:#666;">Total: <strong>Rp <?php echo number_format($booking['total_price'], 0, ',', '.'); ?></strong></p>
        </div>

        <?php
        // Cek apakah booking masih bisa dibayar ulang (belum expired)
        $canRetry = $booking['payment_status'] !== 'expired'
                 && $booking['expired_at']
                 && $booking['expired_at'] > date('Y-m-d H:i:s');
        ?>

        <?php if ($canRetry): ?>
        <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
            <a href="process.php?booking_id=<?php echo $booking['id']; ?>"
               class="btn btn-primary" style="flex:1;">
                🔄 Coba Bayar Lagi
            </a>
            <a href="../user/booking.php" class="btn" style="flex:1;">Batalkan</a>
        </div>
        <p style="color:#888; font-size:.8rem;">
            Booking masih aktif hingga <?php echo date('H:i', strtotime($booking['expired_at'])); ?> WIB.
            Kamu bisa coba metode pembayaran lain.
        </p>

        <?php else: ?>
        <div style="display: flex; gap: 1rem;">
            <a href="../user/booking.php" class="btn btn-primary" style="flex:1;">
                📋 Buat Booking Baru
            </a>
            <a href="../index.php" class="btn" style="flex:1;">🏠 Beranda</a>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <a href="../user/booking.php" class="btn btn-primary" style="width:100%;">
            Kembali ke Booking
        </a>
        <?php endif; ?>

        <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #eee;">
            <p style="color:#888; font-size:.85rem;">
                Butuh bantuan? Hubungi admin kami.<br>
                Sertakan Order ID saat menghubungi kami.
            </p>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<?php
// ============================================================
// PAYMENT PENDING
// User menutup Snap popup sebelum selesai / metode butuh waktu
// GET /payment/pending.php?order_id=RENTAL-xxx
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
    SELECT b.*, p.name AS ps_name, py.snap_token, py.payment_type,
           py.snap_redirect_url
    FROM bookings b
    LEFT JOIN playstation p  ON b.ps_id    = p.id
    LEFT JOIN payments    py ON b.order_id = py.order_id
    WHERE b.order_id = ? AND b.user_id = ?
");
$stmt->bind_param('si', $orderId, $currentUserId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header('Location: ../user/booking.php');
    exit;
}

// Jika ternyata sudah bayar (webhook masuk duluan) redirect ke finish
if ($booking['payment_status'] === 'paid') {
    header('Location: finish.php?order_id=' . urlencode($orderId));
    exit;
}

// Cek expired
$isExpired = $booking['expired_at'] && $booking['expired_at'] < date('Y-m-d H:i:s');

include '../includes/header.php';
?>

<div class="container" style="max-width: 600px; margin: 2rem auto; text-align: center;">
    <div class="card">
        <div style="font-size: 4rem; margin-bottom: 1rem;">
            <?php echo $isExpired ? '⌛' : '⏳'; ?>
        </div>

        <?php if ($isExpired): ?>
        <h2 style="color: #f56565; margin-bottom: .5rem;">Booking Expired</h2>
        <p style="color: #666; margin-bottom: 2rem;">
            Waktu pembayaran sudah habis. Silakan buat booking baru.
        </p>
        <a href="../user/booking.php" class="btn btn-primary" style="width:100%;">Buat Booking Baru</a>

        <?php else: ?>
        <h2 style="color: #ed8936; margin-bottom: .5rem;">Menunggu Pembayaran</h2>
        <p style="color: #666; margin-bottom: .5rem;">
            Selesaikan pembayaran sebelum waktu habis.
        </p>
        <p style="font-size: 1.3rem; font-weight: 700; color: #f56565;" id="countdown">
            Menghitung...
        </p>

        <div style="background: #f7fafc; border-radius: 8px; padding: 1.2rem; text-align:left; margin: 1.5rem 0;">
            <p style="margin:0; color:#666;">Order ID: <strong style="font-size:.85rem;"><?php echo htmlspecialchars($orderId); ?></strong></p>
            <p style="margin:.5rem 0 0; color:#666;">PlayStation: <strong><?php echo htmlspecialchars($booking['ps_name']); ?></strong></p>
            <p style="margin:.5rem 0 0; color:#666;">Total: <strong style="color:#48bb78;">Rp <?php echo number_format($booking['total_price'], 0, ',', '.'); ?></strong></p>
        </div>

        <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
            <?php if ($booking['snap_token']): ?>
            <button id="resume-btn" class="btn btn-primary" style="flex:1;">
                💳 Lanjutkan Bayar
            </button>
            <?php elseif ($booking['snap_redirect_url']): ?>
            <a href="<?php echo htmlspecialchars($booking['snap_redirect_url']); ?>"
               class="btn btn-primary" style="flex:1;">
                💳 Buka Halaman Bayar
            </a>
            <?php endif; ?>
            <a href="../user/booking.php" class="btn" style="flex:1;">Nanti Saja</a>
        </div>

        <p style="color:#888; font-size:.8rem;">
            Sudah transfer? Pembayaran akan diverifikasi otomatis dalam beberapa menit.
        </p>
        <?php endif; ?>
    </div>
</div>

<?php if (!$isExpired && $booking['snap_token']): ?>
<script src="<?php echo MIDTRANS_SNAP_URL; ?>"
        data-client-key="<?php echo MIDTRANS_CLIENT_KEY; ?>"></script>

<script>
// Countdown
(function() {
    const expiredAt = new Date('<?php echo $booking['expired_at']; ?>').getTime();
    const el        = document.getElementById('countdown');
    const btn       = document.getElementById('resume-btn');

    const tick = () => {
        const diff = expiredAt - Date.now();
        if (diff <= 0) {
            if (el)  el.textContent     = 'EXPIRED — Refresh halaman';
            if (btn) btn.disabled       = true;
            return;
        }
        const m = String(Math.floor((diff / 1000 / 60) % 60)).padStart(2, '0');
        const s = String(Math.floor((diff / 1000) % 60)).padStart(2, '0');
        if (el) el.textContent = `Sisa waktu: ${m}:${s}`;
    };
    tick();
    setInterval(tick, 1000);
})();

// Resume pembayaran
document.getElementById('resume-btn')?.addEventListener('click', function() {
    snap.pay('<?php echo $booking['snap_token']; ?>', {
        onSuccess: () => window.location.href = '<?php echo APP_URL; ?>/payment/finish.php?order_id=<?php echo urlencode($orderId); ?>',
        onPending: () => window.location.reload(),
        onError:   () => window.location.href = '<?php echo APP_URL; ?>/payment/error.php?order_id=<?php echo urlencode($orderId); ?>',
        onClose:   () => {}
    });
});

// Auto-polling status setiap 10 detik
let attempts = 0;
const poll = setInterval(() => {
    attempts++;
    fetch('<?php echo APP_URL; ?>/api/payment/status.php?order_id=<?php echo urlencode($orderId); ?>')
        .then(r => r.json())
        .then(data => {
            if (data.booking?.payment_status === 'paid') {
                clearInterval(poll);
                window.location.href = '<?php echo APP_URL; ?>/payment/finish.php?order_id=<?php echo urlencode($orderId); ?>';
            } else if (data.booking?.payment_status === 'expired') {
                clearInterval(poll);
                window.location.reload();
            }
        })
        .catch(() => {});
    if (attempts >= 18) clearInterval(poll); // stop setelah 3 menit
}, 10000);
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

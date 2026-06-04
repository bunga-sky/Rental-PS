<?php
// ============================================================
// PAYMENT PROCESS - FINAL FIX (ANTI-ERROR 400 & 401)
// ============================================================

require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$currentUserId = (int) $_SESSION['user_id'];
$bookingId = isset($_GET['booking_id']) ? (int) $_GET['booking_id'] : 0;

if (!$bookingId) {
    header('Location: ../user/booking.php');
    exit;
}

// 1. AMBIL DATA BOOKING
$stmt = $conn->prepare("
    SELECT b.*, p.name AS ps_name, p.type AS ps_type,
           u.username, u.email, u.phone
    FROM bookings b
    JOIN playstation p ON b.ps_id   = p.id
    JOIN users       u ON b.user_id = u.id
    WHERE b.id = ? AND b.user_id = ?
");
$stmt->bind_param('ii', $bookingId, $currentUserId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    $_SESSION['error'] = 'Booking tidak ditemukan';
    header('Location: ../user/booking.php');
    exit;
}

// 2. CEK STATUS
if ($booking['payment_status'] === 'paid') {
    header('Location: finish.php?order_id=' . urlencode($booking['order_id']));
    exit;
}

// 3. GENERATE SNAP TOKEN
$snapToken = null;

try {
    $start = strtotime($booking['start_time']);
    $end = strtotime($booking['end_time']);
    $duration_seconds = $end - $start;
    $duration_hours = (int) max(1, ceil($duration_seconds / 3600));

    $total_amount = (int) $booking['total_price'];
    $unit_price = (int) ($total_amount / $duration_hours);
    $final_gross_amount = $unit_price * $duration_hours;

    $params = [
        'transaction_details' => [
            'order_id' => $booking['order_id'],
            'gross_amount' => $final_gross_amount,
        ],
        'item_details' => [
            [
                'id' => 'PS-' . $booking['ps_id'],
                'price' => $unit_price,
                'quantity' => $duration_hours,
                'name' => 'Sewa ' . substr($booking['ps_name'], 0, 30),
            ]
        ],
        'customer_details' => [
            'first_name' => $booking['username'],
            'email' => $booking['email'],
            'phone' => $booking['phone'] ?? '',
        ],
        'callbacks' => [
            'finish' => MIDTRANS_FINISH_URL . '?order_id=' . urlencode($booking['order_id']),
        ],
        'expiry' => [
            'unit' => 'minutes',
            'duration' => 15,
        ],
    ];

    $snapToken = \Midtrans\Snap::getSnapToken($params);

    $updateStmt = $conn->prepare("UPDATE bookings SET snap_token = ?, payment_status = 'pending' WHERE id = ?");
    $updateStmt->bind_param('si', $snapToken, $bookingId);
    $updateStmt->execute();

    logActivity($conn, $currentUserId, 'payment.snap_created', 'booking', $bookingId, "Token: " . $snapToken);

} catch (\Exception $e) {
    $error_msg = $e->getMessage();
    logActivity($conn, $currentUserId, 'payment.error', 'booking', $bookingId, $error_msg);
    $error = "Detail Error: " . $error_msg;
}

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ── PAYMENT PAGE STYLES ── */
.payment-wrapper {
    min-height: calc(100vh - 140px);
    background: var(--bg);
    padding: 3rem 0 5rem;
    display: flex;
    align-items: flex-start;
    justify-content: center;
}

.payment-layout {
    width: 100%;
    max-width: 960px;
    padding: 0 20px;
    display: grid;
    grid-template-columns: 1fr 420px;
    gap: 2rem;
    align-items: start;
}

/* ── LEFT PANEL: Info ── */
.payment-info-panel {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.payment-breadcrumb {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-size: .8rem;
    color: var(--text-muted);
    margin-bottom: .25rem;
}

.payment-breadcrumb a {
    color: var(--text-muted);
    text-decoration: none;
    transition: color var(--dur) var(--ease);
}

.payment-breadcrumb a:hover {
    color: var(--primary);
}

.payment-breadcrumb svg {
    width: 14px;
    height: 14px;
    stroke: currentColor;
    opacity: .5;
}

.payment-heading {
    font-size: 1.65rem;
    font-weight: 800;
    color: var(--text);
    letter-spacing: -.02em;
    line-height: 1.2;
}

.payment-heading span {
    color: var(--primary);
}

.payment-subheading {
    font-size: .9rem;
    color: var(--text-muted);
    line-height: 1.6;
    margin-top: .25rem;
}

/* Detail card */
.detail-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.detail-card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: .65rem;
}

.detail-card-header svg {
    width: 18px;
    height: 18px;
    stroke: var(--primary);
}

.detail-card-header h3 {
    font-size: .9rem;
    font-weight: 700;
    color: var(--text);
    letter-spacing: -.01em;
}

.detail-rows {
    padding: .5rem 0;
}

.detail-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .85rem 1.5rem;
    transition: background var(--dur) var(--ease);
}

.detail-row:hover {
    background: var(--bg);
}

.detail-row + .detail-row {
    border-top: 1px solid var(--border);
}

.detail-row.total-row {
    border-top: 2px solid var(--border);
    padding-top: 1.1rem;
    padding-bottom: 1.1rem;
    margin-top: .25rem;
}

.detail-label {
    font-size: .82rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: .5rem;
}

.detail-label svg {
    width: 14px;
    height: 14px;
    stroke: currentColor;
    opacity: .6;
}

.detail-value {
    font-size: .88rem;
    font-weight: 600;
    color: var(--text);
}

.detail-value.total {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--primary);
}

.ps-type-badge {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .2rem .65rem;
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
}

.ps-type-badge.ps5 {
    background: var(--primary-light);
    color: var(--primary);
    border: 1px solid rgba(59,91,219,.2);
}

.ps-type-badge.ps4 {
    background: #DCFCE7;
    color: #166534;
    border: 1px solid rgba(46,204,113,.2);
}

/* Payment methods info */
.methods-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.25rem 1.5rem;
}

.methods-label {
    font-size: .75rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .07em;
    margin-bottom: 1rem;
}

.methods-list {
    display: flex;
    gap: .6rem;
    flex-wrap: wrap;
}

.method-chip {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .35rem .8rem;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: .75rem;
    font-weight: 600;
    color: var(--text-muted);
    background: var(--bg);
}

.method-chip svg {
    width: 13px;
    height: 13px;
    stroke: currentColor;
}

/* ── RIGHT PANEL: Action ── */
.payment-action-panel {
    position: sticky;
    top: 100px;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.action-card {
    background: var(--white);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.action-card-body {
    padding: 2rem;
}

.action-timer {
    display: flex;
    align-items: center;
    gap: .5rem;
    background: #FEF3C7;
    border: 1px solid #FDE68A;
    border-radius: var(--radius-sm);
    padding: .6rem 1rem;
    margin-bottom: 1.5rem;
}

.action-timer svg {
    width: 15px;
    height: 15px;
    stroke: #92400E;
    flex-shrink: 0;
}

.action-timer p {
    font-size: .78rem;
    color: #78350F;
    font-weight: 600;
    line-height: 1.4;
}

.action-summary-label {
    font-size: .75rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .07em;
    margin-bottom: .75rem;
}

.action-total {
    font-size: 2rem;
    font-weight: 800;
    color: var(--text);
    letter-spacing: -.03em;
    line-height: 1;
    margin-bottom: .3rem;
}

.action-total-sub {
    font-size: .8rem;
    color: var(--text-muted);
    margin-bottom: 1.75rem;
}

.btn-pay {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .6rem;
    width: 100%;
    padding: 1rem 1.5rem;
    background: var(--primary);
    color: var(--white);
    font-size: .95rem;
    font-weight: 700;
    border: none;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all var(--dur) var(--ease);
    letter-spacing: .01em;
}

.btn-pay svg {
    width: 18px;
    height: 18px;
    stroke: currentColor;
}

.btn-pay:hover {
    background: var(--primary-dark);
    box-shadow: 0 6px 20px rgba(59,91,219,.35);
    transform: translateY(-1px);
}

.btn-pay:active {
    transform: translateY(0);
    box-shadow: none;
}

.action-secure {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    margin-top: 1rem;
    font-size: .75rem;
    color: var(--text-muted);
}

.action-secure svg {
    width: 13px;
    height: 13px;
    stroke: currentColor;
    opacity: .6;
}

.action-divider {
    height: 1px;
    background: var(--border);
    margin: 1.5rem 2rem;
}

.action-order-id {
    padding: 0 2rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.action-order-label {
    font-size: .75rem;
    color: var(--text-muted);
    font-weight: 500;
}

.action-order-value {
    font-size: .8rem;
    font-weight: 700;
    color: var(--text);
    font-family: ui-monospace, monospace;
    letter-spacing: .02em;
}

/* Error state */
.error-panel {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 3rem 2.5rem;
    text-align: center;
    max-width: 520px;
    margin: 0 auto;
}

.error-icon-wrap {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #FEE2E2;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
}

.error-icon-wrap svg {
    width: 28px;
    height: 28px;
    stroke: #991B1B;
}

.error-panel h2 {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text);
    margin-bottom: .6rem;
}

.error-panel p {
    font-size: .875rem;
    color: var(--text-muted);
    line-height: 1.65;
    margin-bottom: 1.5rem;
}

.error-detail {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: .85rem 1rem;
    font-size: .78rem;
    font-family: ui-monospace, monospace;
    color: #991B1B;
    text-align: left;
    margin-bottom: 1.5rem;
    line-height: 1.5;
    word-break: break-all;
}

.error-hint {
    font-size: .8rem;
    color: var(--text-muted);
    margin-bottom: 1.75rem;
    line-height: 1.6;
}

.error-hint strong {
    color: var(--text);
}

/* ── RESPONSIVE ── */
@media (max-width: 820px) {
    .payment-layout {
        grid-template-columns: 1fr;
        max-width: 560px;
    }
    .payment-action-panel {
        position: static;
    }
}

@media (max-width: 480px) {
    .payment-wrapper {
        padding: 1.5rem 0 4rem;
    }
    .action-card-body {
        padding: 1.5rem;
    }
    .action-order-id {
        padding: 0 1.5rem 1.25rem;
    }
    .action-divider {
        margin: 1.25rem 1.5rem;
    }
}
</style>

<div class="payment-wrapper">
    <?php if (isset($error)): ?>
        <!-- ── ERROR STATE ── -->
        <div style="width:100%;max-width:560px;padding:0 20px;">
            <div class="error-panel">
                <div class="error-icon-wrap">
                    <i data-lucide="alert-triangle"></i>
                </div>
                <h2>Gagal Membuat Sesi Pembayaran</h2>
                <p>Terjadi kesalahan saat menghubungi Midtrans. Pastikan konfigurasi server sudah benar.</p>

                <div class="error-detail"><?php echo htmlspecialchars($error); ?></div>

                <p class="error-hint">
                    Periksa kembali <strong>Server Key</strong> dan <strong>Client Key</strong>
                    di file <strong>config_midtrans.php</strong>.
                </p>

                <a href="../user/booking.php" class="btn btn-secondary" style="display:inline-flex;align-items:center;gap:.45rem;">
                    <i data-lucide="arrow-left"></i>
                    Kembali ke Booking
                </a>
            </div>
        </div>

    <?php else: ?>
        <!-- ── MAIN PAYMENT LAYOUT ── -->
        <div class="payment-layout">

            <!-- LEFT: Info panel -->
            <div class="payment-info-panel">

                <!-- Breadcrumb -->
                <nav class="payment-breadcrumb">
                    <a href="../user/booking.php">Booking</a>
                    <i data-lucide="chevron-right"></i>
                    <span>Pembayaran</span>
                </nav>

                <!-- Heading -->
                <div>
                    <h1 class="payment-heading">Konfirmasi <span>Pembayaran</span></h1>
                    <p class="payment-subheading">Selesaikan pembayaran sebelum waktu habis. Sesi akan kedaluwarsa dalam 15 menit.</p>
                </div>

                <!-- Detail booking -->
                <div class="detail-card">
                    <div class="detail-card-header">
                        <i data-lucide="clipboard-list"></i>
                        <h3>Detail Pesanan</h3>
                    </div>
                    <div class="detail-rows">
                        <div class="detail-row">
                            <span class="detail-label">
                                <i data-lucide="hash"></i>
                                Order ID
                            </span>
                            <span class="detail-value" style="font-family:ui-monospace,monospace;font-size:.82rem;">
                                <?php echo htmlspecialchars($booking['order_id']); ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i data-lucide="monitor"></i>
                                Unit PlayStation
                            </span>
                            <span class="detail-value" style="display:flex;align-items:center;gap:.5rem;">
                                <?php echo htmlspecialchars($booking['ps_name']); ?>
                                <span class="ps-type-badge <?php echo strtolower($booking['ps_type']); ?>">
                                    <?php echo strtoupper($booking['ps_type']); ?>
                                </span>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i data-lucide="clock"></i>
                                Durasi Sewa
                            </span>
                            <span class="detail-value"><?php echo $duration_hours; ?> jam</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i data-lucide="calendar"></i>
                                Mulai Main
                            </span>
                            <span class="detail-value">
                                <?php echo date('d M Y, H:i', strtotime($booking['start_time'])); ?> WIB
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i data-lucide="calendar-x"></i>
                                Selesai
                            </span>
                            <span class="detail-value">
                                <?php echo date('d M Y, H:i', strtotime($booking['end_time'])); ?> WIB
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i data-lucide="tag"></i>
                                Harga per Jam
                            </span>
                            <span class="detail-value">Rp <?php echo number_format($unit_price, 0, ',', '.'); ?></span>
                        </div>
                        <div class="detail-row total-row">
                            <span class="detail-label" style="font-weight:700;color:var(--text);font-size:.875rem;">
                                Total Pembayaran
                            </span>
                            <span class="detail-value total">
                                Rp <?php echo number_format($final_gross_amount, 0, ',', '.'); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Payment methods -->
                <div class="methods-card">
                    <div class="methods-label">Metode Pembayaran Tersedia</div>
                    <div class="methods-list">
                        <span class="method-chip"><i data-lucide="smartphone"></i> GoPay</span>
                        <span class="method-chip"><i data-lucide="smartphone"></i> OVO</span>
                        <span class="method-chip"><i data-lucide="building-2"></i> Transfer Bank</span>
                        <span class="method-chip"><i data-lucide="qr-code"></i> QRIS</span>
                        <span class="method-chip"><i data-lucide="credit-card"></i> Kartu Kredit</span>
                    </div>
                </div>

            </div>

            <!-- RIGHT: Action panel -->
            <div class="payment-action-panel">
                <div class="action-card">
                    <div class="action-card-body">

                        <!-- Expiry warning -->
                        <div class="action-timer">
                            <i data-lucide="alarm-clock"></i>
                            <p>Selesaikan pembayaran dalam <strong>15 menit</strong> sebelum pesanan dibatalkan otomatis.</p>
                        </div>

                        <div class="action-summary-label">Total yang harus dibayar</div>
                        <div class="action-total">
                            Rp <?php echo number_format($final_gross_amount, 0, ',', '.'); ?>
                        </div>
                        <div class="action-total-sub">
                            <?php echo $duration_hours; ?> jam &times; Rp <?php echo number_format($unit_price, 0, ',', '.'); ?>
                        </div>

                        <button id="pay-btn" class="btn-pay">
                            <i data-lucide="wallet"></i>
                            Bayar Sekarang
                        </button>

                        <div class="action-secure">
                            <i data-lucide="shield-check"></i>
                            Pembayaran aman via Midtrans
                        </div>
                    </div>

                    <div class="action-divider"></div>

                    <div class="action-order-id">
                        <span class="action-order-label">Order ID</span>
                        <span class="action-order-value"><?php echo htmlspecialchars($booking['order_id']); ?></span>
                    </div>
                </div>

                <!-- Back link -->
                <a href="../user/booking.php"
                   style="display:inline-flex;align-items:center;gap:.4rem;font-size:.8rem;color:var(--text-muted);text-decoration:none;padding:.5rem 0;transition:color var(--dur) var(--ease);"
                   onmouseover="this.style.color='var(--primary)'"
                   onmouseout="this.style.color='var(--text-muted)'">
                    <i data-lucide="arrow-left" style="width:14px;height:14px;stroke:currentColor;"></i>
                    Kembali ke halaman booking
                </a>
            </div>

        </div>
    <?php endif; ?>
</div>

<script src="<?php echo MIDTRANS_SNAP_URL; ?>" data-client-key="<?php echo MIDTRANS_CLIENT_KEY; ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof lucide !== 'undefined') lucide.createIcons();

        const payButton = document.getElementById('pay-btn');
        if (payButton) {
            payButton.onclick = function () {
                snap.pay('<?php echo $snapToken; ?>', {
                    onSuccess: function () {
                        window.location.href = 'finish.php?order_id=<?php echo urlencode($booking['order_id']); ?>';
                    },
                    onPending: function () {
                        window.location.href = 'pending.php?order_id=<?php echo urlencode($booking['order_id']); ?>';
                    },
                    onError: function () {
                        alert('Pembayaran gagal. Silakan coba kembali.');
                    },
                    onClose: function () {
                        console.log('Customer menutup popup pembayaran.');
                    }
                });
            };
        }
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
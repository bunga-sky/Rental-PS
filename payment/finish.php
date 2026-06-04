<?php
// ============================================================
// PAYMENT FINISH
// Redirect setelah user selesai bayar (status: success)
// GET /payment/finish.php?order_id=RENTAL-xxx
//
// PENTING: Jangan update DB di sini!
// Update DB hanya dari notification.php (webhook)
// Halaman ini hanya untuk UX — tampilkan status ke user
// ============================================================

require_once __DIR__ . '/../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$currentUserId = (int) $_SESSION['user_id'];
$orderId = isset($_GET['order_id']) ? trim($_GET['order_id']) : '';

if (!$orderId) {
    header('Location: ../user/booking.php');
    exit;
}

// Polling status dari DB (webhook mungkin sudah/belum update)
$stmt = $conn->prepare("
    SELECT b.*, p.name AS ps_name, p.type AS ps_type,
           py.status AS midtrans_status, py.payment_type
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

// Jika webhook belum masuk, verifikasi manual ke Midtrans API
if ($booking['payment_status'] !== 'paid') {
    $verifiedStatus = getMidtransTransactionStatus($orderId);
    if ($verifiedStatus) {
        $txStatus = $verifiedStatus['transaction_status'] ?? '';
        if (in_array($txStatus, ['settlement', 'capture'], true)) {
            $conn->begin_transaction();
            try {
                $conn->query("UPDATE bookings SET payment_status='paid', status='confirmed', paid_at=NOW() WHERE order_id='$orderId'");
                $conn->query("UPDATE payments SET status='settlement', settlement_time=NOW() WHERE order_id='$orderId'");
                $conn->commit();
                $booking['payment_status'] = 'paid';
                $booking['status'] = 'confirmed';
            } catch (Exception $e) {
                $conn->rollback();
            }
        }
    }
}

include '../includes/header.php';
?>

<style>
    /* ── FINISH PAGE STYLES ── */
    .finish-wrapper {
        min-height: calc(100vh - 140px);
        background: var(--bg);
        padding: 3rem 0 5rem;
        display: flex;
        align-items: flex-start;
        justify-content: center;
    }

    .finish-inner {
        width: 100%;
        max-width: 600px;
        padding: 0 20px;
    }

    /* ── STATUS ICON ── */
    .status-icon-wrap {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        flex-shrink: 0;
    }

    .status-icon-wrap svg {
        width: 32px;
        height: 32px;
        stroke: currentColor;
        stroke-width: 2.5px;
    }

    .status-icon-wrap.success {
        background: #DCFCE7;
        color: #166534;
    }

    .status-icon-wrap.pending {
        background: #FEF3C7;
        color: #92400E;
    }

    .status-icon-wrap.unknown {
        background: var(--card-bg);
        color: var(--text-muted);
    }

    /* ── MAIN CARD ── */
    .finish-card {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
    }

    .finish-card-top {
        padding: 2.5rem 2rem 2rem;
        text-align: center;
        border-bottom: 1px solid var(--border);
    }

    .finish-status-title {
        font-size: 1.4rem;
        font-weight: 800;
        color: var(--text);
        letter-spacing: -.02em;
        margin-bottom: .4rem;
    }

    .finish-status-title.success {
        color: #166534;
    }

    .finish-status-title.pending {
        color: #92400E;
    }

    .finish-status-desc {
        font-size: .9rem;
        color: var(--text-muted);
        line-height: 1.65;
        max-width: 380px;
        margin: 0 auto;
    }

    /* ── DETAIL TABLE ── */
    .finish-details {
        padding: 0;
    }

    .finish-detail-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: .9rem 2rem;
        transition: background var(--dur) var(--ease);
    }

    .finish-detail-row:hover {
        background: var(--bg);
    }

    .finish-detail-row+.finish-detail-row {
        border-top: 1px solid var(--border);
    }

    .finish-detail-row.total-row {
        border-top: 2px solid var(--border);
        background: var(--bg);
        padding-top: 1.1rem;
        padding-bottom: 1.1rem;
    }

    .finish-detail-label {
        font-size: .82rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: .45rem;
    }

    .finish-detail-label svg {
        width: 14px;
        height: 14px;
        stroke: currentColor;
        opacity: .6;
        flex-shrink: 0;
    }

    .finish-detail-value {
        font-size: .88rem;
        font-weight: 600;
        color: var(--text);
        text-align: right;
    }

    .finish-detail-value.total {
        font-size: 1.15rem;
        font-weight: 800;
        color: #166534;
    }

    .finish-detail-value.mono {
        font-family: ui-monospace, monospace;
        font-size: .8rem;
        letter-spacing: .02em;
    }

    /* PS type badge */
    .ps-badge {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        padding: .15rem .55rem;
        border-radius: 999px;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        margin-left: .4rem;
    }

    .ps-badge.ps5 {
        background: var(--primary-light);
        color: var(--primary);
        border: 1px solid rgba(59, 91, 219, .2);
    }

    .ps-badge.ps4 {
        background: #DCFCE7;
        color: #166534;
        border: 1px solid rgba(46, 204, 113, .2);
    }

    /* ── ACTIONS ── */
    .finish-actions {
        padding: 1.5rem 2rem 2rem;
        display: flex;
        gap: .75rem;
        border-top: 1px solid var(--border);
    }

    .finish-actions .btn {
        flex: 1;
        justify-content: center;
    }

    /* ── PENDING POLLING ── */
    .polling-bar {
        margin: 1.25rem 2rem 0;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: .85rem 1rem;
        display: flex;
        align-items: center;
        gap: .65rem;
    }

    .polling-spinner {
        width: 16px;
        height: 16px;
        border: 2px solid var(--border);
        border-top-color: var(--warning);
        border-radius: 50%;
        animation: spin .8s linear infinite;
        flex-shrink: 0;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .polling-text {
        font-size: .8rem;
        color: var(--text-muted);
        line-height: 1.4;
    }

    .polling-text strong {
        color: var(--text);
    }

    /* ── ORDER ID STRIP ── */
    .order-strip {
        background: var(--bg);
        border-top: 1px solid var(--border);
        padding: .8rem 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .order-strip-label {
        font-size: .72rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: .07em;
    }

    .order-strip-value {
        font-size: .78rem;
        font-weight: 700;
        font-family: ui-monospace, monospace;
        color: var(--text);
        letter-spacing: .02em;
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 480px) {
        .finish-card-top {
            padding: 2rem 1.25rem 1.5rem;
        }

        .finish-detail-row {
            padding: .85rem 1.25rem;
        }

        .finish-actions {
            padding: 1.25rem 1.25rem 1.5rem;
            flex-direction: column;
        }

        .order-strip {
            padding: .75rem 1.25rem;
        }

        .polling-bar {
            margin: 1rem 1.25rem 0;
        }
    }
</style>

<div class="finish-wrapper">
    <div class="finish-inner">

        <?php if ($booking['payment_status'] === 'paid'): ?>
            <!-- ══════════════ SUCCESS ══════════════ -->
            <div class="finish-card">

                <div class="finish-card-top">
                    <div class="status-icon-wrap success">
                        <i data-lucide="check-circle"></i>
                    </div>
                    <h2 class="finish-status-title success">Pembayaran Berhasil</h2>
                    <p class="finish-status-desc">
                        Booking kamu sudah dikonfirmasi. Datang sesuai jadwal dan tunjukkan Order ID ini ke staf kami.
                    </p>
                </div>

                <div class="finish-details">
                    <div class="finish-detail-row">
                        <span class="finish-detail-label">
                            <i data-lucide="hash"></i> Order ID
                        </span>
                        <span class="finish-detail-value mono">
                            <?php echo htmlspecialchars($booking['order_id']); ?>
                        </span>
                    </div>
                    <div class="finish-detail-row">
                        <span class="finish-detail-label">
                            <i data-lucide="monitor"></i> Unit PlayStation
                        </span>
                        <span class="finish-detail-value">
                            <?php echo htmlspecialchars($booking['ps_name']); ?>
                            <span class="ps-badge <?php echo strtolower($booking['ps_type']); ?>">
                                <?php echo strtoupper($booking['ps_type']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="finish-detail-row">
                        <span class="finish-detail-label">
                            <i data-lucide="calendar"></i> Tanggal
                        </span>
                        <span class="finish-detail-value">
                            <?php echo date('d F Y', strtotime($booking['booking_date'])); ?>
                        </span>
                    </div>
                    <div class="finish-detail-row">
                        <span class="finish-detail-label">
                            <i data-lucide="clock"></i> Jam Main
                        </span>
                        <span class="finish-detail-value">
                            <?php echo date('H:i', strtotime($booking['start_time'])); ?> &ndash;
                            <?php echo date('H:i', strtotime($booking['end_time'])); ?> WIB
                        </span>
                    </div>
                    <div class="finish-detail-row">
                        <span class="finish-detail-label">
                            <i data-lucide="credit-card"></i> Metode Bayar
                        </span>
                        <span class="finish-detail-value">
                            <?php echo strtoupper($booking['payment_type'] ?? '-'); ?>
                        </span>
                    </div>
                    <div class="finish-detail-row total-row">
                        <span class="finish-detail-label" style="font-weight:700;color:var(--text);font-size:.875rem;">
                            Total Dibayar
                        </span>
                        <span class="finish-detail-value total">
                            Rp <?php echo number_format($booking['total_price'], 0, ',', '.'); ?>
                        </span>
                    </div>
                </div>

                <div class="finish-actions">
                    <a href="../user/booking.php" class="btn btn-primary">
                        <i data-lucide="calendar-check"></i>
                        Lihat Booking Saya
                    </a>
                    <a href="../index.php" class="btn btn-secondary">
                        <i data-lucide="home"></i>
                        Beranda
                    </a>
                </div>

            </div>

        <?php elseif ($booking['payment_status'] === 'pending'): ?>
            <!-- ══════════════ PENDING ══════════════ -->
            <div class="finish-card">

                <div class="finish-card-top">
                    <div class="status-icon-wrap pending">
                        <i data-lucide="clock"></i>
                    </div>
                    <h2 class="finish-status-title pending">Menunggu Konfirmasi</h2>
                    <p class="finish-status-desc">
                        Pembayaranmu sedang diverifikasi oleh sistem. Halaman ini akan diperbarui otomatis setelah status
                        diterima.
                    </p>
                    <div class="polling-bar">
                        <div class="polling-spinner"></div>
                        <p class="polling-text">
                            Memeriksa status secara otomatis&hellip;
                            <strong>Order ID: <?php echo htmlspecialchars($booking['order_id']); ?></strong>
                        </p>
                    </div>
                </div>

                <div class="finish-actions" style="padding-top:1.5rem;">
                    <a href="../user/booking.php" class="btn btn-primary">
                        <i data-lucide="calendar-check"></i>
                        Cek Status Booking
                    </a>
                    <a href="../index.php" class="btn btn-secondary">
                        <i data-lucide="home"></i>
                        Beranda
                    </a>
                </div>

            </div>

            <script>
                // Auto polling setiap 5 detik — max 1 menit
                let attempt = 0;
                const maxAttempts = 12;
                const poll = setInterval(() => {
                    attempt++;
                    fetch('<?php echo APP_URL; ?>/api/payment/status.php?order_id=<?php echo urlencode($orderId); ?>')
                        .then(r => r.json())
                        .then(data => {
                            if (data.booking && data.booking.payment_status === 'paid') {
                                clearInterval(poll);
                                window.location.reload();
                            }
                        })
                        .catch(() => { });
                    if (attempt >= maxAttempts) clearInterval(poll);
                }, 5000);
            </script>

        <?php else: ?>
            <!-- ══════════════ UNKNOWN ══════════════ -->
            <div class="finish-card">

                <div class="finish-card-top">
                    <div class="status-icon-wrap unknown">
                        <i data-lucide="help-circle"></i>
                    </div>
                    <h2 class="finish-status-title">Status Tidak Diketahui</h2>
                    <p class="finish-status-desc">
                        Kami tidak dapat memverifikasi status pembayaranmu saat ini. Silakan cek riwayat booking atau
                        hubungi admin jika ada pertanyaan.
                    </p>
                </div>

                <div class="order-strip">
                    <span class="order-strip-label">Order ID</span>
                    <span class="order-strip-value"><?php echo htmlspecialchars($booking['order_id']); ?></span>
                </div>

                <div class="finish-actions">
                    <a href="../user/booking.php" class="btn btn-primary">
                        <i data-lucide="calendar-check"></i>
                        Cek Booking Saya
                    </a>
                    <a href="../index.php" class="btn btn-secondary">
                        <i data-lucide="home"></i>
                        Beranda
                    </a>
                </div>

            </div>

        <?php endif; ?>

    </div>
</div>

<?php include '../includes/footer.php'; ?>
<?php
// ============================================================
// USER: Cancel Booking
// GET  /user/cancel_booking.php?id=123       → tampil halaman konfirmasi
// POST /user/cancel_booking.php              → proses cancel via API
// ============================================================

require_once '../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$currentUserId = (int) $_SESSION['user_id'];
$bookingId     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$bookingId) {
    header('Location: booking.php');
    exit;
}

// Ambil detail booking
$stmt = $conn->prepare("
    SELECT b.*, p.name AS ps_name, p.type AS ps_type,
           py.status AS midtrans_status, py.payment_type
    FROM bookings b
    LEFT JOIN playstation p  ON b.ps_id    = p.id
    LEFT JOIN payments    py ON b.order_id = py.order_id
    WHERE b.id = ? AND b.user_id = ?
");
$stmt->bind_param('ii', $bookingId, $currentUserId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    $_SESSION['error'] = 'Booking tidak ditemukan';
    header('Location: booking.php');
    exit;
}

// Validasi bisa dibatalkan
$canCancel = in_array($booking['status'], ['pending', 'confirmed'], true)
          && $booking['status'] !== 'completed';

$alreadyCancelled = $booking['status'] === 'cancelled';
$needsRefund      = $booking['payment_status'] === 'paid';

// ---- Proses Cancel (POST) ----
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_cancel'])) {
    if (!$canCancel) {
        $error = 'Booking ini tidak bisa dibatalkan';
    } else {
        $reason = trim($_POST['reason'] ?? 'Dibatalkan oleh customer');

        $conn->begin_transaction();
        try {
            // Update booking
            $stmtCancel = $conn->prepare("
                UPDATE bookings
                SET status = 'cancelled',
                    payment_status = CASE
                        WHEN payment_status = 'paid' THEN 'refunded'
                        ELSE payment_status
                    END,
                    updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            $stmtCancel->bind_param('ii', $bookingId, $currentUserId);
            $stmtCancel->execute();

            // Nonaktifkan schedule
            $conn->query("
                UPDATE playstation_schedules SET is_active = 0 WHERE booking_id = $bookingId
            ");

            // Jika sudah bayar → buat refund request
            if ($needsRefund) {
                $stmtPay = $conn->prepare("SELECT id FROM payments WHERE order_id = ?");
                $stmtPay->bind_param('s', $booking['order_id']);
                $stmtPay->execute();
                $pay = $stmtPay->get_result()->fetch_assoc();

                if ($pay) {
                    $stmtRefund = $conn->prepare("
                        INSERT INTO refunds (payment_id, booking_id, user_id, amount, reason, status, refund_type)
                        VALUES (?, ?, ?, ?, ?, 'pending', 'full')
                    ");
                    $stmtRefund->bind_param('iiids',
                        $pay['id'], $bookingId, $currentUserId,
                        $booking['total_price'], $reason
                    );
                    $stmtRefund->execute();
                }
            }

            logActivity($conn, $currentUserId, 'booking.cancelled', 'booking', $bookingId,
                "Cancel booking #$bookingId. Alasan: $reason");

            $conn->commit();

            // Redirect dengan success message
            $_SESSION['success'] = $needsRefund
                ? 'Booking dibatalkan. Refund Rp ' . number_format($booking['total_price'], 0, ',', '.') . ' sedang diproses (1-3 hari kerja).'
                : 'Booking berhasil dibatalkan.';

            header('Location: booking.php');
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Gagal membatalkan booking. Silakan coba lagi.';
        }
    }
}

include '../includes/header.php';
?>

<div class="container" style="max-width: 600px; margin: 2rem auto;">

    <h1 class="card-title">❌ Batalkan Booking</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($alreadyCancelled): ?>
    <!-- Sudah dibatalkan -->
    <div class="card" style="text-align:center;">
        <div style="font-size:3rem;margin-bottom:1rem;">✅</div>
        <h2>Booking Sudah Dibatalkan</h2>
        <p style="color:#888;">Booking ini sudah dibatalkan sebelumnya.</p>
        <a href="booking.php" class="btn btn-primary" style="margin-top:1rem;">Kembali ke Booking</a>
    </div>

    <?php elseif (!$canCancel): ?>
    <!-- Tidak bisa dibatalkan -->
    <div class="card" style="text-align:center;">
        <div style="font-size:3rem;margin-bottom:1rem;">⚠️</div>
        <h2>Tidak Bisa Dibatalkan</h2>
        <p style="color:#888;">
            Booking dengan status <strong><?php echo ucfirst($booking['status']); ?></strong>
            tidak dapat dibatalkan.
        </p>
        <a href="booking.php" class="btn btn-primary" style="margin-top:1rem;">Kembali ke Booking</a>
    </div>

    <?php else: ?>
    <!-- Form konfirmasi cancel -->
    <div class="card">
        <!-- Detail booking yang akan dibatalkan -->
        <div style="background:#fff5f5;border:1px solid #fed7d7;border-radius:8px;padding:1.2rem;margin-bottom:1.5rem;">
            <h3 style="margin:0 0 1rem;color:#c53030;">Detail Booking yang akan Dibatalkan</h3>
            <table style="width:100%;font-size:.95rem;">
                <tr>
                    <td style="color:#666;padding:.3rem 0;">PlayStation</td>
                    <td style="font-weight:600;">
                        <?php echo htmlspecialchars($booking['ps_name']); ?>
                        (<?php echo $booking['ps_type']; ?>)
                    </td>
                </tr>
                <tr>
                    <td style="color:#666;padding:.3rem 0;">Tanggal</td>
                    <td style="font-weight:600;"><?php echo date('d F Y', strtotime($booking['booking_date'])); ?></td>
                </tr>
                <tr>
                    <td style="color:#666;padding:.3rem 0;">Jam</td>
                    <td style="font-weight:600;">
                        <?php echo substr($booking['start_time'],0,5); ?> –
                        <?php echo substr($booking['end_time'],0,5); ?> WIB
                    </td>
                </tr>
                <tr>
                    <td style="color:#666;padding:.3rem 0;">Total</td>
                    <td style="font-weight:700;font-size:1.1rem;">
                        Rp <?php echo number_format($booking['total_price'],0,',','.'); ?>
                    </td>
                </tr>
                <tr>
                    <td style="color:#666;padding:.3rem 0;">Status Payment</td>
                    <td>
                        <?php
                        $payBadge = match($booking['payment_status']) {
                            'paid'    => 'badge-success',
                            'pending' => 'badge-warning',
                            'unpaid'  => 'badge-warning',
                            default   => ''
                        };
                        ?>
                        <span class="badge <?php echo $payBadge; ?>">
                            <?php echo ucfirst($booking['payment_status']); ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Info refund jika sudah bayar -->
        <?php if ($needsRefund): ?>
        <div class="alert alert-success" style="margin-bottom:1.5rem;">
            <strong>💰 Refund akan diproses</strong><br>
            Karena kamu sudah melakukan pembayaran, refund sebesar
            <strong>Rp <?php echo number_format($booking['total_price'],0,',','.'); ?></strong>
            akan diproses oleh admin dalam <strong>1-3 hari kerja</strong>.
        </div>
        <?php else: ?>
        <div class="alert" style="background:#f7fafc;margin-bottom:1.5rem;">
            ℹ️ Booking belum dibayar, tidak ada biaya yang dikembalikan.
        </div>
        <?php endif; ?>

        <!-- Form cancel -->
        <form method="POST">
            <div class="form-group">
                <label>Alasan Pembatalan <span style="color:#888;font-size:.85rem;">(opsional)</span></label>
                <select name="reason" class="form-control">
                    <option value="Dibatalkan oleh customer">Pilih alasan...</option>
                    <option value="Jadwal berubah">Jadwal berubah</option>
                    <option value="Salah pilih jadwal">Salah pilih jadwal</option>
                    <option value="Ada keperluan mendadak">Ada keperluan mendadak</option>
                    <option value="Ingin ganti unit PlayStation">Ingin ganti unit PlayStation</option>
                    <option value="Lainnya">Lainnya</option>
                </select>
            </div>

            <div style="display:flex;gap:1rem;margin-top:1.5rem;">
                <button type="submit" name="confirm_cancel" class="btn btn-danger" style="flex:1;"
                        onclick="return confirm('Yakin ingin membatalkan booking ini?')">
                    ❌ Ya, Batalkan Booking
                </button>
                <a href="booking.php" class="btn" style="flex:1;text-align:center;">
                    Tidak, Kembali
                </a>
            </div>
        </form>
    </div>
    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>

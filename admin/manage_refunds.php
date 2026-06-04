<?php
require_once '../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$adminId = (int) $_SESSION['user_id'];
$success = '';
$error = '';

// ---- Approve / Reject Refund ----
if (isset($_POST['process_refund'])) {
    $refundId = (int) $_POST['refund_id'];
    $action = $_POST['action'];
    $note = trim($_POST['note'] ?? '');

    if (!in_array($action, ['approved', 'rejected'], true)) {
        $error = 'Aksi tidak valid';
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("
                SELECT r.*, p.order_id, b.user_id AS cust_id
                FROM refunds r
                JOIN payments p ON r.payment_id = p.id
                JOIN bookings b ON r.booking_id  = b.id
                WHERE r.id = ? AND r.status = 'pending'
                FOR UPDATE
            ");
            $stmt->bind_param('i', $refundId);
            $stmt->execute();
            $refund = $stmt->get_result()->fetch_assoc();

            if (!$refund) {
                throw new Exception('Refund tidak ditemukan atau sudah diproses');
            }

            $newStatus = $action === 'approved' ? 'processed' : 'rejected';
            $stmtUp = $conn->prepare("
                UPDATE refunds SET status=?, processed_by=?, processed_at=NOW(), reason=CONCAT(reason, ' | Admin: ', ?)
                WHERE id=?
            ");
            $stmtUp->bind_param('sisi', $newStatus, $adminId, $note, $refundId);
            $stmtUp->execute();

            if ($action === 'approved') {
                $conn->query("UPDATE bookings SET payment_status='refunded' WHERE order_id='{$refund['order_id']}'");
                $conn->query("UPDATE payments SET status='refund' WHERE order_id='{$refund['order_id']}'");
            }

            logActivity(
                $conn,
                $adminId,
                "admin.refund.$action",
                'refunds',
                $refundId,
                "Admin $action refund #$refundId. Note: $note"
            );

            $conn->commit();
            $success = "Refund #$refundId berhasil di-" . ($action === 'approved' ? 'approve' : 'reject');
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// ---- Fetch Refunds ----
$filterStatus = $_GET['status'] ?? 'pending';

$stmt = $conn->prepare("
    SELECT r.*,
           u.username, u.email, u.phone,
           b.order_id, b.booking_date, b.start_time, b.end_time,
           ps.name AS ps_name,
           a.username AS admin_name
    FROM refunds r
    JOIN users u        ON r.user_id      = u.id
    JOIN bookings b     ON r.booking_id   = b.id
    JOIN playstation ps ON b.ps_id        = ps.id
    LEFT JOIN users a   ON r.processed_by = a.id
    WHERE (? = '' OR r.status = ?)
    ORDER BY r.created_at DESC
");
$stmt->bind_param('ss', $filterStatus, $filterStatus);
$stmt->execute();
$refunds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count per status
$counts = [];
$res = $conn->query("SELECT status, COUNT(*) AS c FROM refunds GROUP BY status");
while ($row = $res->fetch_assoc())
    $counts[$row['status']] = (int) $row['c'];
$totalAll = array_sum($counts);

include '../includes/header.php';
?>

<style>
    /* ── MANAGE REFUNDS ── */
    .ref-wrapper {
        padding: 2.5rem 0 5rem;
    }

    /* ── PAGE HEADER ── */
    .ref-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 2rem;
        padding-bottom: 1.75rem;
        border-bottom: 1px solid var(--border);
    }

    .ref-title {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--text);
        letter-spacing: -.02em;
        display: flex;
        align-items: center;
        gap: .65rem;
        margin-bottom: .2rem;
    }

    .ref-title svg {
        width: 28px;
        height: 28px;
        stroke: var(--primary);
    }

    .ref-subtitle {
        font-size: .875rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: .4rem;
    }

    .ref-subtitle svg {
        width: 13px;
        height: 13px;
        stroke: currentColor;
        opacity: .6;
    }

    .ref-back-btn {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: .45rem 1rem;
        font-size: .82rem;
        font-weight: 600;
        color: var(--text-muted);
        text-decoration: none;
        transition: all var(--dur) var(--ease);
    }

    .ref-back-btn svg {
        width: 14px;
        height: 14px;
        stroke: currentColor;
    }

    .ref-back-btn:hover {
        background: var(--bg);
        color: var(--text);
        border-color: var(--text-muted);
    }

    /* ── ALERT ── */
    .ref-alert {
        display: flex;
        align-items: center;
        gap: .75rem;
        border-radius: var(--radius-sm);
        padding: .9rem 1.1rem;
        margin-bottom: 1.5rem;
        font-size: .875rem;
    }

    .ref-alert svg {
        width: 17px;
        height: 17px;
        stroke: currentColor;
        flex-shrink: 0;
    }

    .ref-alert.success {
        background: #F0FDF4;
        color: #166534;
        border: 1px solid #BBF7D0;
        border-left: 4px solid #22C55E;
    }

    .ref-alert.danger {
        background: #FEF2F2;
        color: #991B1B;
        border: 1px solid #FECACA;
        border-left: 4px solid var(--danger);
    }

    /* ── QUICK NAV ── */
    .ref-nav {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: .75rem;
        margin-bottom: 2rem;
    }

    .ref-nav-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .55rem;
        padding: 1.1rem .75rem;
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        color: var(--text);
        text-decoration: none;
        font-size: .8rem;
        font-weight: 600;
        text-align: center;
        transition: all var(--dur) var(--ease);
        line-height: 1.3;
    }

    .ref-nav-btn:hover,
    .ref-nav-btn.active {
        background: var(--primary-light);
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .ref-nav-icon {
        width: 40px;
        height: 40px;
        border-radius: var(--radius-sm);
        background: var(--bg);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background var(--dur) var(--ease);
    }

    .ref-nav-btn:hover .ref-nav-icon,
    .ref-nav-btn.active .ref-nav-icon {
        background: var(--primary-light);
    }

    .ref-nav-icon svg {
        width: 20px;
        height: 20px;
        stroke: var(--primary);
    }

    /* ── MINI STATS ── */
    .ref-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .ref-stat {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1.1rem 1.25rem;
        display: flex;
        align-items: center;
        gap: .9rem;
        transition: box-shadow var(--dur) var(--ease), transform var(--dur) var(--ease);
    }

    .ref-stat:hover {
        box-shadow: var(--shadow);
        transform: translateY(-2px);
    }

    .ref-stat-icon {
        width: 40px;
        height: 40px;
        border-radius: var(--radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .ref-stat-icon svg {
        width: 19px;
        height: 19px;
        stroke: currentColor;
    }

    .ref-stat-icon.blue {
        background: var(--primary-light);
        color: var(--primary);
    }

    .ref-stat-icon.orange {
        background: #FEF3C7;
        color: #92400E;
    }

    .ref-stat-icon.green {
        background: #DCFCE7;
        color: #166534;
    }

    .ref-stat-icon.red {
        background: #FEE2E2;
        color: #991B1B;
    }

    .ref-stat-num {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--text);
        line-height: 1;
    }

    .ref-stat-label {
        font-size: .72rem;
        color: var(--text-muted);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: .05em;
        margin-top: .2rem;
    }

    /* ── TAB FILTER ── */
    .ref-tabs {
        display: flex;
        gap: .4rem;
        margin-bottom: 1.75rem;
        flex-wrap: wrap;
        padding: .5rem;
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius);
    }

    .ref-tab {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .45rem 1rem;
        border-radius: var(--radius-sm);
        font-size: .82rem;
        font-weight: 600;
        text-decoration: none;
        color: var(--text-muted);
        transition: all var(--dur) var(--ease);
        border: 1px solid transparent;
    }

    .ref-tab svg {
        width: 13px;
        height: 13px;
        stroke: currentColor;
    }

    .ref-tab:hover {
        background: var(--bg);
        color: var(--text);
    }

    .ref-tab.active {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }

    .ref-tab .tab-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 18px;
        padding: 0 5px;
        border-radius: 999px;
        font-size: .68rem;
        font-weight: 800;
        background: rgba(255, 255, 255, .25);
    }

    .ref-tab:not(.active) .tab-count {
        background: var(--bg);
        color: var(--text-muted);
    }

    /* ── REFUND CARDS ── */
    .ref-card {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
        margin-bottom: 1rem;
        transition: box-shadow var(--dur) var(--ease);
    }

    .ref-card:hover {
        box-shadow: var(--shadow);
    }

    .ref-card.pending-card {
        border-left: 4px solid #F59E0B;
    }

    .ref-card.processed-card {
        border-left: 4px solid #22C55E;
    }

    .ref-card.rejected-card {
        border-left: 4px solid var(--danger);
    }

    /* card header bar */
    .ref-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: .85rem 1.4rem;
        border-bottom: 1px solid var(--border);
        background: var(--bg);
        flex-wrap: wrap;
        gap: .5rem;
    }

    .ref-card-id {
        display: flex;
        align-items: center;
        gap: .75rem;
    }

    .ref-card-id-num {
        font-size: .8rem;
        font-weight: 700;
        color: var(--text-muted);
        font-family: ui-monospace, monospace;
    }

    .ref-card-date {
        font-size: .78rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: .3rem;
    }

    .ref-card-date svg {
        width: 12px;
        height: 12px;
        stroke: currentColor;
    }

    /* status badge */
    .ref-badge {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        padding: .22rem .65rem;
        border-radius: 999px;
        font-size: .7rem;
        font-weight: 700;
    }

    .ref-badge svg {
        width: 10px;
        height: 10px;
        stroke: currentColor;
    }

    .ref-pending {
        background: #FEF3C7;
        color: #92400E;
    }

    .ref-processed {
        background: #DCFCE7;
        color: #166534;
    }

    .ref-rejected {
        background: #FEE2E2;
        color: #991B1B;
    }

    /* card body */
    .ref-card-body {
        display: flex;
        gap: 2rem;
        padding: 1.25rem 1.4rem;
        flex-wrap: wrap;
    }

    /* info section */
    .ref-info {
        flex: 1;
        min-width: 260px;
    }

    .ref-info-grid {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: .3rem 1rem;
        font-size: .84rem;
    }

    .ref-info-label {
        color: var(--text-muted);
        font-size: .78rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        white-space: nowrap;
        padding-top: .1rem;
    }

    .ref-info-val {
        color: var(--text);
    }

    .ref-info-val strong {
        font-weight: 700;
    }

    .ref-order-id {
        font-family: ui-monospace, monospace;
        font-size: .73rem;
        color: var(--text-muted);
        word-break: break-all;
    }

    .ref-reason {
        font-style: italic;
        color: var(--text-muted);
    }

    /* action section */
    .ref-action {
        min-width: 210px;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: .75rem;
    }

    .ref-amount {
        font-size: 1.6rem;
        font-weight: 800;
        color: #166534;
        line-height: 1;
        text-align: right;
    }

    .ref-amount-label {
        font-size: .72rem;
        color: var(--text-muted);
        text-align: right;
        margin-top: .15rem;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    /* approve/reject form */
    .ref-process-form {
        width: 100%;
    }

    .ref-note-group {
        margin-bottom: .65rem;
    }

    .ref-note-group label {
        display: block;
        font-size: .72rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: .05em;
        margin-bottom: .3rem;
    }

    .ref-note-group input {
        width: 100%;
        font-size: .82rem;
        padding: .35rem .65rem;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--white);
        color: var(--text);
    }

    .ref-btn-row {
        display: flex;
        gap: .4rem;
        justify-content: flex-end;
    }

    .btn-approve,
    .btn-reject {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        font-size: .78rem;
        font-weight: 700;
        padding: .4rem .85rem;
        border-radius: var(--radius-sm);
        border: none;
        cursor: pointer;
        transition: all var(--dur) var(--ease);
        white-space: nowrap;
    }

    .btn-approve svg,
    .btn-reject svg {
        width: 13px;
        height: 13px;
        stroke: currentColor;
    }

    .btn-approve {
        background: #DCFCE7;
        color: #166534;
        border: 1px solid #BBF7D0;
    }

    .btn-approve:hover {
        background: #BBF7D0;
        border-color: #86EFAC;
    }

    .btn-reject {
        background: #FEE2E2;
        color: #991B1B;
        border: 1px solid #FECACA;
    }

    .btn-reject:hover {
        background: #FCA5A5;
        border-color: #F87171;
    }

    /* processed / rejected info */
    .ref-processed-info {
        display: flex;
        align-items: flex-start;
        gap: .5rem;
        padding: .65rem .85rem;
        border-radius: var(--radius-sm);
        font-size: .82rem;
        width: 100%;
    }

    .ref-processed-info svg {
        width: 15px;
        height: 15px;
        stroke: currentColor;
        flex-shrink: 0;
        margin-top: 1px;
    }

    .ref-processed-info.approved {
        background: #F0FDF4;
        color: #166534;
        border: 1px solid #BBF7D0;
    }

    .ref-processed-info.rejected {
        background: #FEF2F2;
        color: #991B1B;
        border: 1px solid #FECACA;
    }

    /* empty state */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-muted);
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
    }

    .empty-state svg {
        width: 44px;
        height: 44px;
        stroke: var(--border);
        display: block;
        margin: 0 auto .75rem;
    }

    .empty-state p {
        font-size: .9rem;
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 1100px) {
        .ref-nav {
            grid-template-columns: repeat(3, 1fr);
        }

        .ref-stats {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 760px) {
        .ref-nav {
            grid-template-columns: repeat(3, 1fr);
        }

        .ref-stats {
            grid-template-columns: repeat(2, 1fr);
        }

        .ref-action {
            align-items: flex-start;
            min-width: 100%;
        }

        .ref-btn-row {
            justify-content: flex-start;
        }

        .ref-amount {
            text-align: left;
        }

        .ref-amount-label {
            text-align: left;
        }
    }

    @media (max-width: 520px) {
        .ref-nav {
            grid-template-columns: repeat(2, 1fr);
        }

        .ref-stats {
            grid-template-columns: 1fr 1fr;
        }
    }
</style>

<div class="ref-wrapper">
    <div class="container">

        <!-- Page Header -->
        <div class="ref-header">
            <div>
                <h1 class="ref-title">
                    <i data-lucide="receipt"></i>
                    Kelola Refund
                </h1>
                <p class="ref-subtitle">
                    <i data-lucide="clock"></i>
                    <?php echo date('d F Y, H:i'); ?> WIB &nbsp;&middot;&nbsp; <?php echo $totalAll; ?> total permintaan
                    refund
                </p>
            </div>
            <a href="dashboard.php" class="ref-back-btn">
                <i data-lucide="arrow-left"></i>
                Dashboard
            </a>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="ref-alert success">
                <i data-lucide="check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="ref-alert danger">
                <i data-lucide="alert-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Quick Navigation -->
        <div class="ref-nav">
            <a href="manage_bookings.php" class="ref-nav-btn">
                <div class="ref-nav-icon"><i data-lucide="calendar-check"></i></div>
                Kelola Booking
            </a>
            <a href="manage_users.php" class="ref-nav-btn">
                <div class="ref-nav-icon"><i data-lucide="users"></i></div>
                Kelola Users
            </a>
            <a href="manage_playstation.php" class="ref-nav-btn">
                <div class="ref-nav-icon"><i data-lucide="monitor"></i></div>
                Kelola PS
            </a>
            <a href="manage_tournaments.php" class="ref-nav-btn">
                <div class="ref-nav-icon"><i data-lucide="trophy"></i></div>
                Tournament
            </a>
            <a href="manage_refunds.php" class="ref-nav-btn active">
                <div class="ref-nav-icon"><i data-lucide="receipt"></i></div>
                Refund
            </a>
            <a href="reports.php" class="ref-nav-btn">
                <div class="ref-nav-icon"><i data-lucide="bar-chart-2"></i></div>
                Laporan
            </a>
        </div>

        <!-- Mini Stats -->
        <div class="ref-stats">
            <div class="ref-stat">
                <div class="ref-stat-icon blue"><i data-lucide="receipt"></i></div>
                <div>
                    <div class="ref-stat-num"><?php echo $totalAll; ?></div>
                    <div class="ref-stat-label">Total Refund</div>
                </div>
            </div>
            <div class="ref-stat">
                <div class="ref-stat-icon orange"><i data-lucide="clock"></i></div>
                <div>
                    <div class="ref-stat-num"><?php echo $counts['pending'] ?? 0; ?></div>
                    <div class="ref-stat-label">Pending</div>
                </div>
            </div>
            <div class="ref-stat">
                <div class="ref-stat-icon green"><i data-lucide="check-circle"></i></div>
                <div>
                    <div class="ref-stat-num"><?php echo $counts['processed'] ?? 0; ?></div>
                    <div class="ref-stat-label">Diproses</div>
                </div>
            </div>
            <div class="ref-stat">
                <div class="ref-stat-icon red"><i data-lucide="x-circle"></i></div>
                <div>
                    <div class="ref-stat-num"><?php echo $counts['rejected'] ?? 0; ?></div>
                    <div class="ref-stat-label">Ditolak</div>
                </div>
            </div>
        </div>

        <!-- Tab Filter -->
        <div class="ref-tabs">
            <?php
            $tabs = [
                '' => ['label' => 'Semua', 'icon' => 'layers', 'count' => $totalAll],
                'pending' => ['label' => 'Pending', 'icon' => 'clock', 'count' => $counts['pending'] ?? 0],
                'processed' => ['label' => 'Diproses', 'icon' => 'check-circle', 'count' => $counts['processed'] ?? 0],
                'rejected' => ['label' => 'Ditolak', 'icon' => 'x-circle', 'count' => $counts['rejected'] ?? 0],
            ];
            foreach ($tabs as $val => $tab): ?>
                <a href="?status=<?php echo $val; ?>" class="ref-tab <?php echo $filterStatus === $val ? 'active' : ''; ?>">
                    <i data-lucide="<?php echo $tab['icon']; ?>"></i>
                    <?php echo $tab['label']; ?>
                    <span class="tab-count"><?php echo $tab['count']; ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Refund Cards -->
        <?php if (empty($refunds)): ?>
            <div class="empty-state">
                <i data-lucide="inbox"></i>
                <p>Tidak ada refund dengan status ini</p>
            </div>
        <?php else: ?>

            <?php foreach ($refunds as $r):
                $isPending = $r['status'] === 'pending';
                $isProcessed = $r['status'] === 'processed';
                $isRejected = $r['status'] === 'rejected';

                $cardClass = $isPending ? 'pending-card' : ($isProcessed ? 'processed-card' : 'rejected-card');
                $badgeClass = $isPending ? 'ref-pending' : ($isProcessed ? 'ref-processed' : 'ref-rejected');
                $badgeIcon = $isPending ? 'clock' : ($isProcessed ? 'check-circle' : 'x-circle');
                $badgeLabel = $isPending ? 'Pending' : ($isProcessed ? 'Diproses' : 'Ditolak');
                ?>
                <div class="ref-card <?php echo $cardClass; ?>">

                    <!-- Card Top Bar -->
                    <div class="ref-card-top">
                        <div class="ref-card-id">
                            <span class="ref-badge <?php echo $badgeClass; ?>">
                                <i data-lucide="<?php echo $badgeIcon; ?>"></i>
                                <?php echo $badgeLabel; ?>
                            </span>
                            <span class="ref-card-id-num">Refund #<?php echo $r['id']; ?></span>
                        </div>
                        <div class="ref-card-date">
                            <i data-lucide="calendar"></i>
                            <?php echo date('d M Y, H:i', strtotime($r['created_at'])); ?>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="ref-card-body">

                        <!-- Info Grid -->
                        <div class="ref-info">
                            <div class="ref-info-grid">
                                <span class="ref-info-label">Customer</span>
                                <span class="ref-info-val">
                                    <strong><?php echo htmlspecialchars($r['username']); ?></strong>
                                    <?php if ($r['phone']): ?>
                                        &nbsp;&middot;&nbsp; <span
                                            style="color:var(--text-muted);font-size:.8rem;"><?php echo htmlspecialchars($r['phone']); ?></span>
                                    <?php endif; ?>
                                </span>

                                <span class="ref-info-label">PlayStation</span>
                                <span class="ref-info-val"><?php echo htmlspecialchars($r['ps_name']); ?></span>

                                <span class="ref-info-label">Jadwal</span>
                                <span class="ref-info-val">
                                    <?php echo date('d M Y', strtotime($r['booking_date'])); ?>
                                    &nbsp;
                                    <span style="font-family:ui-monospace,monospace;font-size:.82rem;">
                                        <?php echo substr($r['start_time'], 0, 5) . '&nbsp;&ndash;&nbsp;' . substr($r['end_time'], 0, 5); ?>
                                    </span>
                                </span>

                                <span class="ref-info-label">Order ID</span>
                                <span class="ref-info-val ref-order-id"><?php echo htmlspecialchars($r['order_id']); ?></span>

                                <span class="ref-info-label">Alasan</span>
                                <span class="ref-info-val ref-reason"><?php echo htmlspecialchars($r['reason']); ?></span>
                            </div>
                        </div>

                        <!-- Amount + Action -->
                        <div class="ref-action">
                            <div>
                                <div class="ref-amount">Rp&nbsp;<?php echo number_format($r['amount'], 0, ',', '.'); ?></div>
                                <div class="ref-amount-label">Jumlah Refund</div>
                            </div>

                            <?php if ($isPending): ?>
                                <form method="POST" class="ref-process-form">
                                    <input type="hidden" name="refund_id" value="<?php echo $r['id']; ?>">
                                    <input type="hidden" name="action" value="approved" id="action-<?php echo $r['id']; ?>">
                                    <div class="ref-note-group">
                                        <label>Catatan Admin (opsional)</label>
                                        <input type="text" name="note" placeholder="Tulis catatan...">
                                    </div>
                                    <div class="ref-btn-row">
                                        <button type="submit" name="process_refund" class="btn-approve"
                                            onclick="document.getElementById('action-<?php echo $r['id']; ?>').value='approved';return confirm('Approve refund ini?')">
                                            <i data-lucide="check-circle"></i>
                                            Approve
                                        </button>
                                        <button type="submit" name="process_refund" class="btn-reject"
                                            onclick="document.getElementById('action-<?php echo $r['id']; ?>').value='rejected';return confirm('Reject refund ini?')">
                                            <i data-lucide="x-circle"></i>
                                            Reject
                                        </button>
                                    </div>
                                </form>

                            <?php elseif ($isProcessed): ?>
                                <div class="ref-processed-info approved">
                                    <i data-lucide="check-circle"></i>
                                    <div>
                                        Diproses oleh <strong><?php echo htmlspecialchars($r['admin_name'] ?? '-'); ?></strong><br>
                                        <span
                                            style="font-size:.78rem;opacity:.8;"><?php echo $r['processed_at'] ? date('d M Y, H:i', strtotime($r['processed_at'])) : '-'; ?></span>
                                    </div>
                                </div>

                            <?php elseif ($isRejected): ?>
                                <div class="ref-processed-info rejected">
                                    <i data-lucide="x-circle"></i>
                                    <div>
                                        Ditolak oleh <strong><?php echo htmlspecialchars($r['admin_name'] ?? '-'); ?></strong><br>
                                        <span
                                            style="font-size:.78rem;opacity:.8;"><?php echo $r['processed_at'] ? date('d M Y, H:i', strtotime($r['processed_at'])) : '-'; ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>

<?php include '../includes/footer.php'; ?>
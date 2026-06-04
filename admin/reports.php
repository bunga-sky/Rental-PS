<?php
require_once '../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$period = isset($_GET['period']) ? $_GET['period'] : 'month';

switch ($period) {
    case 'today':
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d');
        $period_label = 'Hari Ini';
        break;
    case 'week':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $end_date = date('Y-m-d', strtotime('sunday this week'));
        $period_label = 'Minggu Ini';
        break;
    case 'month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        $period_label = 'Bulan Ini';
        break;
    case 'year':
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        $period_label = 'Tahun Ini';
        break;
    default:
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        $period_label = 'Bulan Ini';
}

// Stats
$stmt = $conn->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(total_price),0) AS revenue FROM bookings WHERE DATE(booking_date) BETWEEN ? AND ?");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$booking_stats = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("SELECT COALESCE(SUM(total_price),0) AS paid FROM bookings WHERE DATE(booking_date) BETWEEN ? AND ? AND payment_status='paid'");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$paid_stats = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM duel_matches WHERE DATE(match_date) BETWEEN ? AND ?");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$duel_stats = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM bookings WHERE DATE(booking_date) BETWEEN ? AND ? AND status='cancelled'");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$cancel_stats = $stmt->get_result()->fetch_assoc();

// Detail rows
$stmt = $conn->prepare("
    SELECT b.*, u.username, p.name AS ps_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN playstation p ON b.ps_id = p.id
    WHERE DATE(b.booking_date) BETWEEN ? AND ?
    ORDER BY b.booking_date DESC, b.created_at DESC
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<style>
    /* ── REPORT PAGE ── */
    .report-wrapper {
        padding: 2.5rem 0 5rem;
    }

    /* ── PAGE HEADER ── */
    .report-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 2rem;
        padding-bottom: 1.75rem;
        border-bottom: 1px solid var(--border);
    }

    .report-title {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--text);
        letter-spacing: -.02em;
        display: flex;
        align-items: center;
        gap: .65rem;
        margin-bottom: .2rem;
    }

    .report-title svg {
        width: 28px;
        height: 28px;
        stroke: var(--primary);
    }

    .report-subtitle {
        font-size: .875rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: .4rem;
    }

    .report-subtitle svg {
        width: 13px;
        height: 13px;
        stroke: currentColor;
        opacity: .6;
    }

    .report-admin-badge {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        background: #FFF7ED;
        color: #C2410C;
        border: 1px solid #FEDDBA;
        border-radius: 999px;
        padding: .35rem .9rem;
        font-size: .78rem;
        font-weight: 700;
    }

    .report-admin-badge svg {
        width: 13px;
        height: 13px;
        stroke: currentColor;
    }

    /* ── QUICK ACTIONS NAV ── */
    .dash-actions-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: .75rem;
        margin-bottom: 2rem;
    }

    .dash-action-btn {
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

    .dash-action-btn:hover {
        background: var(--primary-light);
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .dash-action-btn.active {
        background: var(--primary-light);
        border-color: var(--primary);
        color: var(--primary);
    }

    .dash-action-icon {
        width: 40px;
        height: 40px;
        border-radius: var(--radius-sm);
        background: var(--bg);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background var(--dur) var(--ease);
    }

    .dash-action-btn:hover .dash-action-icon,
    .dash-action-btn.active .dash-action-icon {
        background: var(--primary-light);
    }

    .dash-action-icon svg {
        width: 20px;
        height: 20px;
        stroke: var(--primary);
    }

    /* ── PERIOD FILTER ── */
    .period-bar {
        display: flex;
        align-items: center;
        gap: .5rem;
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: .5rem;
        margin-bottom: 1.5rem;
        width: fit-content;
    }

    .period-btn {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .5rem 1.1rem;
        border-radius: calc(var(--radius) - 2px);
        font-size: .83rem;
        font-weight: 600;
        color: var(--text-muted);
        text-decoration: none;
        transition: all var(--dur) var(--ease);
    }

    .period-btn:hover {
        background: var(--bg);
        color: var(--text);
    }

    .period-btn.active {
        background: var(--primary);
        color: #fff;
        box-shadow: 0 1px 4px rgba(0, 0, 0, .12);
    }

    .period-btn svg {
        width: 14px;
        height: 14px;
        stroke: currentColor;
    }

    /* ── STATS GRID ── */
    .report-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .dash-stat {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1.25rem 1.4rem;
        display: flex;
        flex-direction: column;
        gap: .35rem;
        transition: box-shadow var(--dur) var(--ease), transform var(--dur) var(--ease);
    }

    .dash-stat:hover {
        box-shadow: var(--shadow);
        transform: translateY(-2px);
    }

    .dash-stat-icon {
        width: 40px;
        height: 40px;
        border-radius: var(--radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: .25rem;
    }

    .dash-stat-icon svg {
        width: 20px;
        height: 20px;
        stroke: currentColor;
    }

    .dash-stat-icon.green {
        background: #DCFCE7;
        color: #166534;
    }

    .dash-stat-icon.teal {
        background: #F0FDFA;
        color: #0F766E;
    }

    .dash-stat-icon.indigo {
        background: #EEF2FF;
        color: #4338CA;
    }

    .dash-stat-icon.red {
        background: #FEE2E2;
        color: #991B1B;
    }

    .dash-stat-num {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--text);
        line-height: 1;
    }

    .dash-stat-num.sm {
        font-size: 1.15rem;
    }

    .dash-stat-label {
        font-size: .75rem;
        color: var(--text-muted);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .dash-stat-sub {
        font-size: .75rem;
        color: var(--text-muted);
        margin-top: .1rem;
    }

    /* ── SECTION DIVIDER ── */
    .report-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .report-section-title {
        display: flex;
        align-items: center;
        gap: .5rem;
        font-size: .95rem;
        font-weight: 700;
        color: var(--text);
    }

    .report-section-title svg {
        width: 17px;
        height: 17px;
        stroke: var(--primary);
    }

    .report-period-tag {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        background: var(--primary-light);
        color: var(--primary);
        border-radius: 999px;
        padding: .25rem .75rem;
        font-size: .75rem;
        font-weight: 700;
    }

    .report-period-tag svg {
        width: 12px;
        height: 12px;
        stroke: currentColor;
    }

    /* ── TABLE CARD ── */
    .report-table-card {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
        margin-bottom: 2rem;
    }

    .report-table-header {
        padding: 1.2rem 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .report-table-title {
        display: flex;
        align-items: center;
        gap: .55rem;
        font-size: .95rem;
        font-weight: 700;
        color: var(--text);
    }

    .report-table-title svg {
        width: 17px;
        height: 17px;
        stroke: var(--primary);
    }

    .report-table {
        width: 100%;
        border-collapse: collapse;
        font-size: .85rem;
    }

    .report-table th {
        background: var(--bg);
        color: var(--text-muted);
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        padding: .75rem 1.25rem;
        text-align: left;
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }

    .report-table td {
        padding: .9rem 1.25rem;
        color: var(--text);
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
    }

    .report-table tbody tr:last-child td {
        border-bottom: none;
    }

    .report-table tbody tr {
        transition: background var(--dur) var(--ease);
    }

    .report-table tbody tr:hover {
        background: var(--bg);
    }

    /* Badges */
    .pay-badge {
        display: inline-flex;
        align-items: center;
        padding: .2rem .6rem;
        border-radius: 999px;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .03em;
        white-space: nowrap;
    }

    .pay-paid {
        background: #DCFCE7;
        color: #166534;
    }

    .pay-pending {
        background: #FEF3C7;
        color: #92400E;
    }

    .pay-unpaid {
        background: var(--bg);
        color: var(--text-muted);
        border: 1px solid var(--border);
    }

    .pay-expired {
        background: #FEE2E2;
        color: #991B1B;
    }

    .pay-failed {
        background: #FEE2E2;
        color: #991B1B;
    }

    .st-confirmed {
        background: var(--primary-light);
        color: var(--primary);
    }

    .st-completed {
        background: #DCFCE7;
        color: #166534;
    }

    .st-cancelled {
        background: #FEE2E2;
        color: #991B1B;
    }

    .st-pending {
        background: #FEF3C7;
        color: #92400E;
    }

    /* User cell */
    .user-cell {
        display: flex;
        align-items: center;
        gap: .55rem;
    }

    .user-avatar-sm {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--primary-light);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .68rem;
        font-weight: 800;
        text-transform: uppercase;
        flex-shrink: 0;
    }

    .user-name {
        font-weight: 600;
        font-size: .85rem;
    }

    /* Print button */
    .btn-print {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: var(--radius-sm);
        padding: .55rem 1.1rem;
        font-size: .83rem;
        font-weight: 700;
        cursor: pointer;
        transition: background var(--dur) var(--ease);
    }

    .btn-print:hover {
        background: var(--primary-dark);
    }

    .btn-print svg {
        width: 15px;
        height: 15px;
        stroke: currentColor;
    }

    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 3rem 1.5rem;
        color: var(--text-muted);
    }

    .empty-state svg {
        width: 40px;
        height: 40px;
        stroke: var(--border);
        margin-bottom: .75rem;
    }

    .empty-state p {
        font-size: .9rem;
    }

    /* Responsive */
    @media (max-width: 1100px) {
        .report-stats {
            grid-template-columns: repeat(2, 1fr);
        }

        .dash-actions-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 760px) {
        .report-stats {
            grid-template-columns: repeat(2, 1fr);
        }

        .dash-actions-grid {
            grid-template-columns: repeat(3, 1fr);
        }

        .period-bar {
            flex-wrap: wrap;
        }

        .report-table th:nth-child(4),
        .report-table td:nth-child(4) {
            display: none;
        }
    }

    @media (max-width: 520px) {
        .dash-actions-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media print {

        .dash-actions-grid,
        .period-bar,
        .btn-print,
        .report-admin-badge {
            display: none !important;
        }

        .report-table-card {
            border: none;
        }
    }
</style>

<div class="report-wrapper">
    <div class="container">

        <!-- Page Header -->
        <div class="report-header">
            <div>
                <h1 class="report-title">
                    <i data-lucide="bar-chart-2"></i>
                    Laporan Transaksi
                </h1>
                <p class="report-subtitle">
                    <i data-lucide="clock"></i>
                    <?php echo date('d F Y, H:i'); ?> WIB &mdash; Periode: <strong><?php echo $period_label; ?></strong>
                    (<?php echo date('d M Y', strtotime($start_date)); ?> &ndash;
                    <?php echo date('d M Y', strtotime($end_date)); ?>)
                </p>
            </div>
            <div class="report-admin-badge">
                <i data-lucide="shield-check"></i>
                Administrator
            </div>
        </div>

        <!-- Quick Actions Nav (sama persis dengan dashboard) -->
        <div class="dash-actions-grid">
           
            <a href="manage_bookings.php" class="dash-action-btn">
                <div class="dash-action-icon"><i data-lucide="calendar-check"></i></div>
                Kelola Booking
            </a>
            <a href="manage_users.php" class="dash-action-btn">
                <div class="dash-action-icon"><i data-lucide="users"></i></div>
                Kelola Users
            </a>
            <a href="manage_playstation.php" class="dash-action-btn">
                <div class="dash-action-icon"><i data-lucide="monitor"></i></div>
                Kelola PS
            </a>
            <a href="manage_tournaments.php" class="dash-action-btn">
                <div class="dash-action-icon"><i data-lucide="trophy"></i></div>
                Tournament
            </a>
            <a href="manage_refunds.php" class="dash-action-btn">
                <div class="dash-action-icon"><i data-lucide="receipt"></i></div>
                Refund
            </a>
            <a href="reports.php" class="dash-action-btn">
                <div class="dash-action-icon"><i data-lucide="receipt"></i></div>
                Laporan
            </a>
            
        </div>

        <!-- Period Filter -->
        <div class="period-bar">
            <a href="?period=today" class="period-btn <?php echo $period === 'today' ? 'active' : ''; ?>">
                <i data-lucide="sun"></i> Hari Ini
            </a>
            <a href="?period=week" class="period-btn <?php echo $period === 'week' ? 'active' : ''; ?>">
                <i data-lucide="calendar-days"></i> Minggu Ini
            </a>
            <a href="?period=month" class="period-btn <?php echo $period === 'month' ? 'active' : ''; ?>">
                <i data-lucide="calendar"></i> Bulan Ini
            </a>
            <a href="?period=year" class="period-btn <?php echo $period === 'year' ? 'active' : ''; ?>">
                <i data-lucide="archive"></i> Tahun Ini
            </a>
        </div>

        <!-- Stats -->
        <div class="report-stats">

            <div class="dash-stat">
                <div class="dash-stat-icon green"><i data-lucide="calendar-check"></i></div>
                <div class="dash-stat-num"><?php echo (int) $booking_stats['total']; ?></div>
                <div class="dash-stat-label">Total Booking</div>
                <div class="dash-stat-sub"><?php echo $period_label; ?></div>
            </div>

            <div class="dash-stat">
                <div class="dash-stat-icon teal"><i data-lucide="banknote"></i></div>
                <div class="dash-stat-num sm">Rp <?php echo number_format((float) $paid_stats['paid'], 0, ',', '.'); ?>
                </div>
                <div class="dash-stat-label">Revenue Terbayar</div>
                <div class="dash-stat-sub">Status: paid</div>
            </div>

            <div class="dash-stat">
                <div class="dash-stat-icon indigo"><i data-lucide="swords"></i></div>
                <div class="dash-stat-num"><?php echo (int) $duel_stats['total']; ?></div>
                <div class="dash-stat-label">Total Duel</div>
                <div class="dash-stat-sub"><?php echo $period_label; ?></div>
            </div>

            <div class="dash-stat">
                <div class="dash-stat-icon red"><i data-lucide="x-circle"></i></div>
                <div class="dash-stat-num"><?php echo (int) $cancel_stats['total']; ?></div>
                <div class="dash-stat-label">Booking Dibatalkan</div>
                <div class="dash-stat-sub"><?php echo $period_label; ?></div>
            </div>

        </div>

        <!-- Detail Table -->
        <div class="report-table-card">
            <div class="report-table-header">
                <div class="report-table-title">
                    <i data-lucide="list"></i>
                    Detail Transaksi &mdash;
                    <span style="color:var(--text-muted); font-weight:500; font-size:.85rem;">
                        <?php echo date('d M Y', strtotime($start_date)); ?>
                        &ndash;
                        <?php echo date('d M Y', strtotime($end_date)); ?>
                    </span>
                </div>
                <button class="btn-print" onclick="window.print()">
                    <i data-lucide="printer"></i>
                    Cetak Laporan
                </button>
            </div>

            <div style="overflow-x: auto;">
                <?php if (empty($rows)): ?>
                    <div class="empty-state">
                        <i data-lucide="inbox"></i>
                        <p>Tidak ada transaksi pada periode ini.</p>
                    </div>
                <?php else: ?>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Customer</th>
                                <th>Unit PS</th>
                                <th>Jam</th>
                                <th>Total</th>
                                <th>Payment</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row):
                                $payClass = match ($row['payment_status']) {
                                    'paid' => 'pay-paid',
                                    'pending' => 'pay-pending',
                                    'unpaid' => 'pay-unpaid',
                                    'expired' => 'pay-expired',
                                    'failed' => 'pay-failed',
                                    default => 'pay-unpaid',
                                };
                                $stClass = match ($row['status']) {
                                    'confirmed' => 'st-confirmed',
                                    'completed' => 'st-completed',
                                    'cancelled' => 'st-cancelled',
                                    default => 'st-pending',
                                };
                                $initial = strtoupper(substr($row['username'], 0, 2));
                                ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($row['booking_date'])); ?></td>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar-sm"><?php echo $initial; ?></div>
                                            <span class="user-name"><?php echo htmlspecialchars($row['username']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['ps_name']); ?></td>
                                    <td style="white-space:nowrap;">
                                        <?php echo substr($row['start_time'], 0, 5); ?> &ndash;
                                        <?php echo substr($row['end_time'], 0, 5); ?>
                                    </td>
                                    <td style="font-weight:600;">Rp
                                        <?php echo number_format($row['total_price'], 0, ',', '.'); ?></td>
                                    <td><span
                                            class="pay-badge <?php echo $payClass; ?>"><?php echo ucfirst($row['payment_status']); ?></span>
                                    </td>
                                    <td><span
                                            class="pay-badge <?php echo $stClass; ?>"><?php echo ucfirst($row['status']); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>
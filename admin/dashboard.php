<?php
require_once '../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$stats = [];
$r = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='customer' AND is_active=1");
$stats['users'] = (int) $r->fetch_assoc()['total'];

$r = $conn->query("SELECT COUNT(*) AS total FROM bookings WHERE booking_date=CURDATE()");
$stats['bookings_today'] = (int) $r->fetch_assoc()['total'];

$r = $conn->query("SELECT COALESCE(SUM(total_price),0) AS total FROM bookings WHERE payment_status='paid' AND MONTH(paid_at)=MONTH(NOW()) AND YEAR(paid_at)=YEAR(NOW())");
$stats['revenue_month'] = (float) $r->fetch_assoc()['total'];

$weekNum = (int) date('W');
$r = $conn->query("SELECT COUNT(*) AS total FROM duel_matches WHERE week_number=$weekNum");
$stats['duels_week'] = (int) $r->fetch_assoc()['total'];

$r = $conn->query("SELECT COUNT(*) AS total FROM bookings WHERE booking_date=CURDATE() AND start_time<=CURTIME() AND end_time>CURTIME() AND status='confirmed'");
$stats['ps_in_use'] = (int) $r->fetch_assoc()['total'];

$r = $conn->query("SELECT COUNT(*) AS total FROM bookings WHERE payment_status IN ('unpaid','pending') AND status='pending'");
$stats['pending_payment'] = (int) $r->fetch_assoc()['total'];

$r = $conn->query("SELECT COUNT(*) AS total FROM refunds WHERE status='pending'");
$stats['pending_refunds'] = (int) $r->fetch_assoc()['total'];

$r = $conn->query("SELECT COUNT(*) AS total FROM tournaments WHERE status IN ('upcoming','ongoing')");
$stats['active_tournaments'] = (int) $r->fetch_assoc()['total'];

$recentBookings = $conn->query("
    SELECT b.id, b.order_id, b.booking_date, b.start_time, b.end_time,
           b.total_price, b.status, b.payment_status,
           u.username, p.name AS ps_name
    FROM bookings b
    JOIN users u ON b.user_id=u.id
    JOIN playstation p ON b.ps_id=p.id
    ORDER BY b.created_at DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<style>
/* ── ADMIN DASHBOARD ── */
.dash-wrapper {
    padding: 2.5rem 0 5rem;
}

/* ── PAGE HEADER ── */
.dash-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 2rem;
    padding-bottom: 1.75rem;
    border-bottom: 1px solid var(--border);
}

.dash-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text);
    letter-spacing: -.02em;
    display: flex;
    align-items: center;
    gap: .65rem;
    margin-bottom: .2rem;
}

.dash-title svg { width: 28px; height: 28px; stroke: var(--primary); }

.dash-subtitle {
    font-size: .875rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: .4rem;
}

.dash-subtitle svg { width: 13px; height: 13px; stroke: currentColor; opacity: .6; }

.dash-admin-badge {
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

.dash-admin-badge svg { width: 13px; height: 13px; stroke: currentColor; }

/* ── ALERT BANNER ── */
.dash-alert {
    display: flex;
    align-items: center;
    gap: .75rem;
    background: #FEF2F2;
    border: 1px solid #FECACA;
    border-left: 4px solid var(--danger);
    border-radius: var(--radius-sm);
    padding: .9rem 1.1rem;
    margin-bottom: 1.75rem;
    font-size: .875rem;
    color: #991B1B;
}

.dash-alert svg { width: 18px; height: 18px; stroke: currentColor; flex-shrink: 0; }
.dash-alert a { color: #991B1B; font-weight: 700; text-decoration: underline; margin-left: .25rem; }
.dash-alert a:hover { color: #7F1D1D; }

/* ── STATS GRID ── */
.dash-stats {
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
    position: relative;
    overflow: hidden;
}

.dash-stat:hover {
    box-shadow: var(--shadow);
    transform: translateY(-2px);
}

.dash-stat.alert-stat {
    border-color: #FECACA;
}

.dash-stat.alert-stat-orange {
    border-color: #FDE68A;
}

.dash-stat-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: .25rem;
    flex-shrink: 0;
}

.dash-stat-icon svg { width: 20px; height: 20px; stroke: currentColor; }

.dash-stat-icon.blue   { background: var(--primary-light); color: var(--primary); }
.dash-stat-icon.green  { background: #DCFCE7; color: #166534; }
.dash-stat-icon.purple { background: #F5F3FF; color: #6D28D9; }
.dash-stat-icon.teal   { background: #F0FDFA; color: #0F766E; }
.dash-stat-icon.orange { background: #FEF3C7; color: #92400E; }
.dash-stat-icon.red    { background: #FEE2E2; color: #991B1B; }
.dash-stat-icon.indigo { background: #EEF2FF; color: #4338CA; }
.dash-stat-icon.yellow { background: #FEFCE8; color: #A16207; }

.dash-stat-num {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text);
    line-height: 1;
}

.dash-stat-num.sm { font-size: 1.15rem; }

.dash-stat-label {
    font-size: .75rem;
    color: var(--text-muted);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: .05em;
}

.dash-stat-dot {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--danger);
    animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: .5; transform: scale(1.3); }
}

/* ── QUICK ACTIONS ── */
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

.dash-action-btn:hover .dash-action-icon {
    background: var(--primary-light);
}

.dash-action-icon svg { width: 20px; height: 20px; stroke: var(--primary); }

/* ── RECENT BOOKINGS TABLE ── */
.dash-table-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.dash-table-header {
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.dash-table-title {
    display: flex;
    align-items: center;
    gap: .55rem;
    font-size: .95rem;
    font-weight: 700;
    color: var(--text);
}

.dash-table-title svg { width: 17px; height: 17px; stroke: var(--primary); }

.dash-table-link {
    font-size: .8rem;
    color: var(--primary);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: .25rem;
}

.dash-table-link svg { width: 13px; height: 13px; stroke: currentColor; }
.dash-table-link:hover { color: var(--primary-dark); }

.dash-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .85rem;
}

.dash-table th {
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

.dash-table td {
    padding: .9rem 1.25rem;
    color: var(--text);
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.dash-table tbody tr:last-child td { border-bottom: none; }
.dash-table tbody tr { transition: background var(--dur) var(--ease); }
.dash-table tbody tr:hover { background: var(--bg); }

.order-id-cell {
    font-family: ui-monospace, monospace;
    font-size: .75rem;
    color: var(--text-muted);
    letter-spacing: .02em;
}

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

.user-name { font-weight: 600; font-size: .85rem; }

/* Payment status badges */
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

.pay-paid     { background: #DCFCE7; color: #166534; }
.pay-pending  { background: #FEF3C7; color: #92400E; }
.pay-unpaid   { background: var(--card-bg); color: var(--text-muted); border: 1px solid var(--border); }
.pay-expired  { background: #FEE2E2; color: #991B1B; }
.pay-failed   { background: #FEE2E2; color: #991B1B; }

.st-confirmed { background: var(--primary-light); color: var(--primary); }
.st-completed { background: #DCFCE7; color: #166534; }
.st-cancelled { background: #FEE2E2; color: #991B1B; }
.st-pending   { background: #FEF3C7; color: #92400E; }

/* ── RESPONSIVE ── */
@media (max-width: 1100px) {
    .dash-stats        { grid-template-columns: repeat(4, 1fr); }
    .dash-actions-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 760px) {
    .dash-stats        { grid-template-columns: repeat(2, 1fr); }
    .dash-actions-grid { grid-template-columns: repeat(3, 1fr); }
    .dash-table th:nth-child(4),
    .dash-table td:nth-child(4) { display: none; }
}

@media (max-width: 520px) {
    .dash-stats        { grid-template-columns: 1fr 1fr; }
    .dash-actions-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<div class="dash-wrapper">
<div class="container">

    <!-- Page Header -->
    <div class="dash-header">
        <div>
            <h1 class="dash-title">
                <i data-lucide="layout-dashboard"></i>
                Dashboard Admin
            </h1>
            <p class="dash-subtitle">
                <i data-lucide="clock"></i>
                <?php echo date('d F Y, H:i'); ?> WIB
            </p>
        </div>
        <div class="dash-admin-badge">
            <i data-lucide="shield-check"></i>
            Administrator
        </div>
    </div>

    <!-- Alert: Pending Refunds -->
    <?php if ($stats['pending_refunds'] > 0): ?>
        <div class="dash-alert">
            <i data-lucide="alert-triangle"></i>
            <span>
                Ada <strong><?php echo $stats['pending_refunds']; ?> refund</strong> menunggu persetujuan.
                <a href="manage_refunds.php">Proses sekarang</a>
            </span>
        </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="dash-stats">

        <div class="dash-stat">
            <div class="dash-stat-icon blue"><i data-lucide="users"></i></div>
            <div class="dash-stat-num"><?php echo $stats['users']; ?></div>
            <div class="dash-stat-label">Total Users Aktif</div>
        </div>

        <div class="dash-stat">
            <div class="dash-stat-icon green"><i data-lucide="calendar-check"></i></div>
            <div class="dash-stat-num"><?php echo $stats['bookings_today']; ?></div>
            <div class="dash-stat-label">Booking Hari Ini</div>
        </div>

        <div class="dash-stat">
            <div class="dash-stat-icon teal"><i data-lucide="banknote"></i></div>
            <div class="dash-stat-num sm">Rp <?php echo number_format($stats['revenue_month'], 0, ',', '.'); ?></div>
            <div class="dash-stat-label">Revenue Bulan Ini</div>
        </div>

        <div class="dash-stat">
            <div class="dash-stat-icon purple"><i data-lucide="monitor"></i></div>
            <div class="dash-stat-num"><?php echo $stats['ps_in_use']; ?></div>
            <div class="dash-stat-label">PS Dipakai Sekarang</div>
        </div>

        <div class="dash-stat <?php echo $stats['pending_payment'] > 0 ? 'alert-stat-orange' : ''; ?>">
            <?php if ($stats['pending_payment'] > 0): ?>
                    <div class="dash-stat-dot" style="background:#F59E0B;"></div>
            <?php endif; ?>
            <div class="dash-stat-icon orange"><i data-lucide="clock"></i></div>
            <div class="dash-stat-num"><?php echo $stats['pending_payment']; ?></div>
            <div class="dash-stat-label">Pending Payment</div>
        </div>

        <div class="dash-stat <?php echo $stats['pending_refunds'] > 0 ? 'alert-stat' : ''; ?>">
            <?php if ($stats['pending_refunds'] > 0): ?>
                    <div class="dash-stat-dot"></div>
            <?php endif; ?>
            <div class="dash-stat-icon red"><i data-lucide="receipt"></i></div>
            <div class="dash-stat-num"><?php echo $stats['pending_refunds']; ?></div>
            <div class="dash-stat-label">Refund Pending</div>
        </div>

        <div class="dash-stat">
            <div class="dash-stat-icon indigo"><i data-lucide="swords"></i></div>
            <div class="dash-stat-num"><?php echo $stats['duels_week']; ?></div>
            <div class="dash-stat-label">Duel Minggu Ini</div>
        </div>

        <div class="dash-stat">
            <div class="dash-stat-icon yellow"><i data-lucide="trophy"></i></div>
            <div class="dash-stat-num"><?php echo $stats['active_tournaments']; ?></div>
            <div class="dash-stat-label">Tournament Aktif</div>
        </div>

    </div>

    <!-- Quick Actions -->
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
            <div class="dash-action-icon"><i data-lucide="bar-chart-2"></i></div>
            Laporan
        </a>
    </div>

    <!-- Recent Bookings -->
    <div class="dash-table-card">
        <div class="dash-table-header">
            <div class="dash-table-title">
                <i data-lucide="list"></i>
                Booking Terbaru
            </div>
            <a href="manage_bookings.php" class="dash-table-link">
                Lihat semua
                <i data-lucide="arrow-right"></i>
            </a>
        </div>

        <div style="overflow-x: auto;">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>User</th>
                        <th>Unit PS</th>
                        <th>Tanggal</th>
                        <th>Jam</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentBookings as $b):
                    $payClass = match ($b['payment_status']) {
                        'paid' => 'pay-paid',
                        'pending' => 'pay-pending',
                        'unpaid' => 'pay-unpaid',
                        'expired' => 'pay-expired',
                        'failed' => 'pay-failed',
                        default => 'pay-unpaid',
                    };
                    $stClass = match ($b['status']) {
                        'confirmed' => 'st-confirmed',
                        'completed' => 'st-completed',
                        'cancelled' => 'st-cancelled',
                        default => 'st-pending',
                    };
                    $initial = strtoupper(substr($b['username'], 0, 2));
                    ?>
                    <tr>
                        <td>
                            <span class="order-id-cell">
                                <?php echo htmlspecialchars(substr($b['order_id'], 0, 20)); ?>&hellip;
                            </span>
                        </td>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar-sm"><?php echo $initial; ?></div>
                                <span class="user-name"><?php echo htmlspecialchars($b['username']); ?></span>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($b['ps_name']); ?></td>
                        <td><?php echo date('d M Y', strtotime($b['booking_date'])); ?></td>
                        <td style="white-space:nowrap;">
                            <?php echo substr($b['start_time'], 0, 5); ?> &ndash; <?php echo substr($b['end_time'], 0, 5); ?>
                        </td>
                        <td style="font-weight:600;">Rp <?php echo number_format($b['total_price'], 0, ',', '.'); ?></td>
                        <td><span class="pay-badge <?php echo $payClass; ?>"><?php echo ucfirst($b['payment_status']); ?></span></td>
                        <td><span class="pay-badge <?php echo $stClass; ?>"><?php echo ucfirst($b['status']); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</div>

<?php include '../includes/footer.php'; ?>
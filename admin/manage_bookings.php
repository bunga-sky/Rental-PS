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

// ---- Actions ----

// Update status booking manual
if (isset($_POST['update_status'])) {
    $bookingId = (int) $_POST['booking_id'];
    $newStatus = $_POST['new_status'];
    $allowed = ['pending', 'confirmed', 'completed', 'cancelled'];

    if (in_array($newStatus, $allowed, true)) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE bookings SET status=?, updated_at=NOW() WHERE id=?");
            $stmt->bind_param('si', $newStatus, $bookingId);
            $stmt->execute();

            if ($newStatus === 'cancelled') {
                $conn->query("UPDATE playstation_schedules SET is_active=0 WHERE booking_id=$bookingId");
            }

            logActivity(
                $conn,
                $adminId,
                'admin.booking_status_update',
                'booking',
                $bookingId,
                "Admin update status booking #$bookingId → $newStatus"
            );
            $conn->commit();
            $success = "Status booking #$bookingId berhasil diupdate ke " . ucfirst($newStatus);
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Gagal update status';
        }
    }
}

// ---- Filter & Pagination ----
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';
$filterPayment = isset($_GET['pay']) ? trim($_GET['pay']) : '';
$filterDate = isset($_GET['date']) ? trim($_GET['date']) : '';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build WHERE
$where = ['1=1'];
$params = [];
$types = '';

if ($filterStatus) {
    $where[] = 'b.status = ?';
    $params[] = $filterStatus;
    $types .= 's';
}
if ($filterPayment) {
    $where[] = 'b.payment_status = ?';
    $params[] = $filterPayment;
    $types .= 's';
}
if ($filterDate) {
    $where[] = 'b.booking_date = ?';
    $params[] = $filterDate;
    $types .= 's';
}
if ($search) {
    $where[] = '(u.username LIKE ? OR b.order_id LIKE ?)';
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

$whereStr = implode(' AND ', $where);

// Count total
$stmtCount = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM bookings b
    JOIN users u ON b.user_id=u.id
    WHERE $whereStr
");
if ($types) {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$totalRows = (int) $stmtCount->get_result()->fetch_assoc()['total'];
$totalPages = (int) ceil($totalRows / $perPage);

// Fetch bookings
$stmtList = $conn->prepare("
    SELECT b.id, b.order_id, b.booking_date, b.start_time, b.end_time,
           b.total_price, b.status, b.payment_status, b.payment_method,
           b.paid_at, b.expired_at, b.created_at,
           u.id AS user_id, u.username, u.email, u.phone,
           p.name AS ps_name, p.type AS ps_type
    FROM bookings b
    JOIN users u       ON b.user_id = u.id
    JOIN playstation p ON b.ps_id   = p.id
    WHERE $whereStr
    ORDER BY b.created_at DESC
    LIMIT ? OFFSET ?
");
$allParams = array_merge($params, [$perPage, $offset]);
$allTypes = $types . 'ii';
$stmtList->bind_param($allTypes, ...$allParams);
$stmtList->execute();
$bookings = $stmtList->get_result()->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<style>
    /* ── MANAGE BOOKINGS ── */
    .mgmt-wrapper {
        padding: 2.5rem 0 5rem;
    }

    /* ── PAGE HEADER ── */
    .mgmt-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 2rem;
        padding-bottom: 1.75rem;
        border-bottom: 1px solid var(--border);
    }

    .mgmt-title {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--text);
        letter-spacing: -.02em;
        display: flex;
        align-items: center;
        gap: .65rem;
        margin-bottom: .2rem;
    }

    .mgmt-title svg {
        width: 28px;
        height: 28px;
        stroke: var(--primary);
    }

    .mgmt-subtitle {
        font-size: .875rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: .4rem;
    }

    .mgmt-subtitle svg {
        width: 13px;
        height: 13px;
        stroke: currentColor;
        opacity: .6;
    }

    .mgmt-back-btn {
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

    .mgmt-back-btn svg {
        width: 14px;
        height: 14px;
        stroke: currentColor;
    }

    .mgmt-back-btn:hover {
        background: var(--bg);
        color: var(--text);
        border-color: var(--text-muted);
    }

    /* ── ALERT ── */
    .mgmt-alert {
        display: flex;
        align-items: center;
        gap: .75rem;
        border-radius: var(--radius-sm);
        padding: .9rem 1.1rem;
        margin-bottom: 1.5rem;
        font-size: .875rem;
        border-left: 4px solid;
    }

    .mgmt-alert svg {
        width: 17px;
        height: 17px;
        stroke: currentColor;
        flex-shrink: 0;
    }

    .mgmt-alert.success {
        background: #F0FDF4;
        border-color: #22C55E;
        color: #166534;
        border: 1px solid #BBF7D0;
        border-left: 4px solid #22C55E;
    }

    .mgmt-alert.danger {
        background: #FEF2F2;
        border-color: var(--danger);
        color: #991B1B;
        border: 1px solid #FECACA;
        border-left: 4px solid var(--danger);
    }

    /* ── QUICK NAV ── */
    .mgmt-nav {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: .75rem;
        margin-bottom: 2rem;
    }

    .mgmt-nav-btn {
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

    .mgmt-nav-btn:hover,
    .mgmt-nav-btn.active {
        background: var(--primary-light);
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .mgmt-nav-icon {
        width: 40px;
        height: 40px;
        border-radius: var(--radius-sm);
        background: var(--bg);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background var(--dur) var(--ease);
    }

    .mgmt-nav-btn:hover .mgmt-nav-icon,
    .mgmt-nav-btn.active .mgmt-nav-icon {
        background: var(--primary-light);
    }

    .mgmt-nav-icon svg {
        width: 20px;
        height: 20px;
        stroke: var(--primary);
    }

    /* ── FILTER CARD ── */
    .filter-card {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
    }

    .filter-card-title {
        display: flex;
        align-items: center;
        gap: .5rem;
        font-size: .85rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: .06em;
        margin-bottom: 1rem;
    }

    .filter-card-title svg {
        width: 14px;
        height: 14px;
        stroke: currentColor;
    }

    .filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: .85rem;
        align-items: flex-end;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: .3rem;
    }

    .filter-group label {
        font-size: .78rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .filter-group .form-control {
        min-width: 160px;
        font-size: .85rem;
    }

    .filter-group input[type="text"] {
        min-width: 200px;
    }

    .filter-actions {
        display: flex;
        gap: .5rem;
        align-items: flex-end;
        padding-bottom: 1px;
    }

    /* ── TABLE CARD ── */
    .mgmt-table-card {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
    }

    .mgmt-table-header {
        padding: 1.2rem 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .mgmt-table-title {
        display: flex;
        align-items: center;
        gap: .55rem;
        font-size: .95rem;
        font-weight: 700;
        color: var(--text);
    }

    .mgmt-table-title svg {
        width: 17px;
        height: 17px;
        stroke: var(--primary);
    }

    .mgmt-table-count {
        font-size: .82rem;
        color: var(--text-muted);
        font-weight: 400;
    }

    .mgmt-table {
        width: 100%;
        border-collapse: collapse;
        font-size: .85rem;
    }

    .mgmt-table th {
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

    .mgmt-table td {
        padding: .9rem 1.25rem;
        color: var(--text);
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
    }

    .mgmt-table tbody tr:last-child td {
        border-bottom: none;
    }

    .mgmt-table tbody tr {
        transition: background var(--dur) var(--ease);
    }

    .mgmt-table tbody tr:hover {
        background: var(--bg);
    }

    /* ── CELLS ── */
    .order-id-cell {
        font-family: ui-monospace, monospace;
        font-size: .75rem;
        color: var(--text-muted);
        letter-spacing: .02em;
    }

    .row-num {
        font-size: .8rem;
        color: var(--text-muted);
    }

    .user-cell {
        display: flex;
        align-items: center;
        gap: .55rem;
    }

    .user-avatar-sm {
        width: 30px;
        height: 30px;
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

    .user-phone {
        font-size: .75rem;
        color: var(--text-muted);
        margin-top: .1rem;
    }

    .ps-name {
        font-weight: 600;
    }

    .ps-type {
        font-size: .75rem;
        color: var(--text-muted);
        margin-top: .1rem;
    }

    .pay-method {
        font-size: .73rem;
        color: var(--text-muted);
        margin-top: .2rem;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    /* ── BADGES ── */
    .pay-badge {
        display: inline-flex;
        align-items: center;
        padding: .22rem .65rem;
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
        background: var(--card-bg, #F9FAFB);
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

    .pay-refunded {
        background: #EEF2FF;
        color: #4338CA;
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

    /* ── INLINE STATUS UPDATE ── */
    .status-form {
        display: flex;
        align-items: center;
        gap: .4rem;
    }

    .status-form select {
        font-size: .78rem;
        padding: .25rem .5rem;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
        background: var(--white);
        color: var(--text);
        min-width: 110px;
    }

    .btn-update {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        font-size: .75rem;
        font-weight: 600;
        padding: .28rem .65rem;
        background: var(--primary);
        color: var(--white);
        border: none;
        border-radius: var(--radius-sm);
        cursor: pointer;
        transition: background var(--dur) var(--ease);
        white-space: nowrap;
    }

    .btn-update svg {
        width: 12px;
        height: 12px;
        stroke: currentColor;
    }

    .btn-update:hover {
        background: var(--primary-dark);
    }

    /* ── EMPTY STATE ── */
    .empty-state {
        text-align: center;
        padding: 3.5rem 2rem;
        color: var(--text-muted);
    }

    .empty-state svg {
        width: 44px;
        height: 44px;
        stroke: var(--border);
        margin-bottom: 1rem;
    }

    .empty-state p {
        font-size: .9rem;
    }

    /* ── PAGINATION ── */
    .pagination {
        display: flex;
        gap: .4rem;
        justify-content: center;
        align-items: center;
        padding: 1.25rem 1.5rem;
        border-top: 1px solid var(--border);
        flex-wrap: wrap;
    }

    .page-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
        background: var(--white);
        color: var(--text);
        font-size: .82rem;
        font-weight: 600;
        text-decoration: none;
        transition: all var(--dur) var(--ease);
    }

    .page-btn:hover {
        background: var(--bg);
        border-color: var(--primary);
        color: var(--primary);
    }

    .page-btn.active {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 1100px) {
        .mgmt-nav {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 760px) {
        .mgmt-nav {
            grid-template-columns: repeat(3, 1fr);
        }

        .mgmt-table th:nth-child(4),
        .mgmt-table td:nth-child(4) {
            display: none;
        }
    }

    @media (max-width: 520px) {
        .mgmt-nav {
            grid-template-columns: repeat(2, 1fr);
        }

        .filter-row {
            flex-direction: column;
        }

        .filter-group .form-control,
        .filter-group input[type="text"] {
            min-width: 100%;
            width: 100%;
        }
    }
</style>

<div class="mgmt-wrapper">
    <div class="container">

        <!-- Page Header -->
        <div class="mgmt-header">
            <div>
                <h1 class="mgmt-title">
                    <i data-lucide="calendar-check"></i>
                    Kelola Booking
                </h1>
                <p class="mgmt-subtitle">
                    <i data-lucide="clock"></i>
                    <?php echo date('d F Y, H:i'); ?> WIB &nbsp;&middot;&nbsp; <?php echo $totalRows; ?> total booking
                </p>
            </div>
            <a href="dashboard.php" class="mgmt-back-btn">
                <i data-lucide="arrow-left"></i>
                Dashboard
            </a>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="mgmt-alert success">
                <i data-lucide="check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mgmt-alert danger">
                <i data-lucide="alert-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Quick Navigation -->
        <div class="mgmt-nav">
            <a href="manage_bookings.php" class="mgmt-nav-btn active">
                <div class="mgmt-nav-icon"><i data-lucide="calendar-check"></i></div>
                Kelola Booking
            </a>
            <a href="manage_users.php" class="mgmt-nav-btn">
                <div class="mgmt-nav-icon"><i data-lucide="users"></i></div>
                Kelola Users
            </a>
            <a href="manage_playstation.php" class="mgmt-nav-btn">
                <div class="mgmt-nav-icon"><i data-lucide="monitor"></i></div>
                Kelola PS
            </a>
            <a href="manage_tournaments.php" class="mgmt-nav-btn">
                <div class="mgmt-nav-icon"><i data-lucide="trophy"></i></div>
                Tournament
            </a>
            <a href="manage_refunds.php" class="mgmt-nav-btn">
                <div class="mgmt-nav-icon"><i data-lucide="receipt"></i></div>
                Refund
            </a>
            <a href="reports.php" class="mgmt-nav-btn">
                <div class="mgmt-nav-icon"><i data-lucide="bar-chart-2"></i></div>
                Laporan
            </a>
        </div>

        <!-- Filter Card -->
        <div class="filter-card">
            <div class="filter-card-title">
                <i data-lucide="sliders-horizontal"></i>
                Filter Booking
            </div>
            <form method="GET">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Cari</label>
                        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>"
                            class="form-control" placeholder="Username / Order ID">
                    </div>
                    <div class="filter-group">
                        <label>Status Booking</label>
                        <select name="status" class="form-control">
                            <option value="">Semua Status</option>
                            <?php foreach (['pending', 'confirmed', 'completed', 'cancelled'] as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo $filterStatus === $s ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($s); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Status Payment</label>
                        <select name="pay" class="form-control">
                            <option value="">Semua Payment</option>
                            <?php foreach (['unpaid', 'pending', 'paid', 'failed', 'expired', 'refunded'] as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo $filterPayment === $s ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($s); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Tanggal Booking</label>
                        <input type="date" name="date" value="<?php echo $filterDate; ?>" class="form-control">
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary"
                            style="display:inline-flex;align-items:center;gap:.4rem;">
                            <i data-lucide="search" style="width:14px;height:14px;stroke:currentColor;"></i>
                            Filter
                        </button>
                        <a href="manage_bookings.php" class="btn"
                            style="display:inline-flex;align-items:center;gap:.4rem;">
                            <i data-lucide="x" style="width:14px;height:14px;stroke:currentColor;"></i>
                            Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table Card -->
        <div class="mgmt-table-card">
            <div class="mgmt-table-header">
                <div class="mgmt-table-title">
                    <i data-lucide="list"></i>
                    Daftar Booking
                    <span class="mgmt-table-count">&mdash; <?php echo $totalRows; ?> data ditemukan</span>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table class="mgmt-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Order ID</th>
                            <th>User</th>
                            <th>Unit PS</th>
                            <th>Tanggal</th>
                            <th>Jam</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Update Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <i data-lucide="inbox"></i>
                                        <p>Tidak ada booking yang ditemukan</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($bookings as $i => $b):
                            $payClass = match ($b['payment_status']) {
                                'paid' => 'pay-paid',
                                'pending' => 'pay-pending',
                                'unpaid' => 'pay-unpaid',
                                'expired' => 'pay-expired',
                                'failed' => 'pay-failed',
                                'refunded' => 'pay-refunded',
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
                                <td><span class="row-num"><?php echo $offset + $i + 1; ?></span></td>
                                <td>
                                    <span class="order-id-cell" title="<?php echo htmlspecialchars($b['order_id']); ?>">
                                        <?php echo htmlspecialchars(substr($b['order_id'], 0, 18)); ?>&hellip;
                                    </span>
                                </td>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar-sm"><?php echo $initial; ?></div>
                                        <div>
                                            <div class="user-name"><?php echo htmlspecialchars($b['username']); ?></div>
                                            <?php if ($b['phone']): ?>
                                                <div class="user-phone"><?php echo htmlspecialchars($b['phone']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="ps-name"><?php echo htmlspecialchars($b['ps_name']); ?></div>
                                    <div class="ps-type"><?php echo htmlspecialchars($b['ps_type']); ?></div>
                                </td>
                                <td style="white-space:nowrap;"><?php echo date('d M Y', strtotime($b['booking_date'])); ?>
                                </td>
                                <td style="white-space:nowrap;font-family:ui-monospace,monospace;font-size:.82rem;">
                                    <?php echo substr($b['start_time'], 0, 5); ?>&nbsp;&ndash;&nbsp;<?php echo substr($b['end_time'], 0, 5); ?>
                                </td>
                                <td style="font-weight:700;white-space:nowrap;">
                                    Rp&nbsp;<?php echo number_format($b['total_price'], 0, ',', '.'); ?>
                                </td>
                                <td>
                                    <span
                                        class="pay-badge <?php echo $payClass; ?>"><?php echo ucfirst($b['payment_status']); ?></span>
                                    <?php if ($b['payment_method']): ?>
                                        <div class="pay-method"><?php echo strtoupper($b['payment_method']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span
                                        class="pay-badge <?php echo $stClass; ?>"><?php echo ucfirst($b['status']); ?></span>
                                </td>
                                <td>
                                    <form method="POST" class="status-form">
                                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                        <select name="new_status">
                                            <?php foreach (['pending', 'confirmed', 'completed', 'cancelled'] as $s): ?>
                                                <option value="<?php echo $s; ?>" <?php echo $b['status'] === $s ? 'selected' : ''; ?>>
                                                    <?php echo ucfirst($s); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="update_status" class="btn-update">
                                            <i data-lucide="check"></i>
                                            Simpan
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filterStatus); ?>&pay=<?php echo urlencode($filterPayment); ?>&date=<?php echo $filterDate; ?>&q=<?php echo urlencode($search); ?>"
                            class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>
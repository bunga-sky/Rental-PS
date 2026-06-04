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

// Toggle active/inactive
if (isset($_GET['toggle'])) {
    $uid = (int) $_GET['toggle'];
    $conn->query("UPDATE users SET is_active = NOT is_active WHERE id=$uid AND role='customer'");
    $success = 'Status user berhasil diubah';
}

// Reset password
if (isset($_POST['reset_password'])) {
    $uid = (int) $_POST['user_id'];
    $newPass = password_hash('rental123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=? AND role='customer'");
    $stmt->bind_param('si', $newPass, $uid);
    $stmt->execute();
    logActivity($conn, $adminId, 'admin.reset_password', 'users', $uid, "Reset password user #$uid");
    $success = "Password user #$uid direset ke: rental123";
}

// Hapus user
if (isset($_GET['delete'])) {
    $uid = (int) $_GET['delete'];
    $check = $conn->query("SELECT COUNT(*) AS c FROM bookings WHERE user_id=$uid AND status IN ('pending','confirmed')")->fetch_assoc();
    if ($check['c'] > 0) {
        $error = 'User masih punya booking aktif, tidak bisa dihapus!';
    } else {
        $conn->query("DELETE FROM users WHERE id=$uid AND role='customer'");
        logActivity($conn, $adminId, 'admin.user_deleted', 'users', $uid, "Hapus user #$uid");
        $success = 'User berhasil dihapus';
    }
}

// Filter & search
$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = ["role='customer'"];
$params = [];
$types = '';

if ($search) {
    $where[] = '(username LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
    $types .= 'sss';
}
if ($filter === 'active') {
    $where[] = 'is_active=1';
}
if ($filter === 'inactive') {
    $where[] = 'is_active=0';
}

$whereStr = implode(' AND ', $where);

$countRes = $conn->prepare("SELECT COUNT(*) AS c FROM users WHERE $whereStr");
if ($types)
    $countRes->bind_param($types, ...$params);
$countRes->execute();
$totalRows = (int) $countRes->get_result()->fetch_assoc()['c'];
$totalPages = (int) ceil($totalRows / $perPage);

$allParams = array_merge($params, [$perPage, $offset]);
$allTypes = $types . 'ii';
$stmt = $conn->prepare("
    SELECT u.*,
       (SELECT COUNT(*) FROM bookings b WHERE b.user_id=u.id AND b.payment_status='paid') AS paid_bookings,
       (SELECT COALESCE(SUM(b.total_price),0) FROM bookings b WHERE b.user_id=u.id AND b.payment_status='paid') AS total_spent
    FROM users u WHERE $whereStr ORDER BY u.created_at DESC LIMIT ? OFFSET ?
");
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Summary counts
$totalActive = (int) $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='customer' AND is_active=1")->fetch_assoc()['c'];
$totalInactive = (int) $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='customer' AND is_active=0")->fetch_assoc()['c'];
$totalAll = $totalActive + $totalInactive;

include '../includes/header.php';
?>

<style>
    /* ── MANAGE USERS ── */
    .usr-wrapper {
        padding: 2.5rem 0 5rem;
    }

    /* ── PAGE HEADER ── */
    .usr-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 2rem;
        padding-bottom: 1.75rem;
        border-bottom: 1px solid var(--border);
    }

    .usr-title {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--text);
        letter-spacing: -.02em;
        display: flex;
        align-items: center;
        gap: .65rem;
        margin-bottom: .2rem;
    }

    .usr-title svg {
        width: 28px;
        height: 28px;
        stroke: var(--primary);
    }

    .usr-subtitle {
        font-size: .875rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: .4rem;
    }

    .usr-subtitle svg {
        width: 13px;
        height: 13px;
        stroke: currentColor;
        opacity: .6;
    }

    .usr-back-btn {
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

    .usr-back-btn svg {
        width: 14px;
        height: 14px;
        stroke: currentColor;
    }

    .usr-back-btn:hover {
        background: var(--bg);
        color: var(--text);
        border-color: var(--text-muted);
    }

    /* ── ALERT ── */
    .usr-alert {
        display: flex;
        align-items: center;
        gap: .75rem;
        border-radius: var(--radius-sm);
        padding: .9rem 1.1rem;
        margin-bottom: 1.5rem;
        font-size: .875rem;
    }

    .usr-alert svg {
        width: 17px;
        height: 17px;
        stroke: currentColor;
        flex-shrink: 0;
    }

    .usr-alert.success {
        background: #F0FDF4;
        color: #166534;
        border: 1px solid #BBF7D0;
        border-left: 4px solid #22C55E;
    }

    .usr-alert.danger {
        background: #FEF2F2;
        color: #991B1B;
        border: 1px solid #FECACA;
        border-left: 4px solid var(--danger);
    }

    /* ── QUICK NAV ── */
    .usr-nav {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: .75rem;
        margin-bottom: 2rem;
    }

    .usr-nav-btn {
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

    .usr-nav-btn:hover,
    .usr-nav-btn.active {
        background: var(--primary-light);
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .usr-nav-icon {
        width: 40px;
        height: 40px;
        border-radius: var(--radius-sm);
        background: var(--bg);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background var(--dur) var(--ease);
    }

    .usr-nav-btn:hover .usr-nav-icon,
    .usr-nav-btn.active .usr-nav-icon {
        background: var(--primary-light);
    }

    .usr-nav-icon svg {
        width: 20px;
        height: 20px;
        stroke: var(--primary);
    }

    /* ── MINI STATS ── */
    .usr-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .usr-stat {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1.1rem 1.25rem;
        display: flex;
        align-items: center;
        gap: .9rem;
        transition: box-shadow var(--dur) var(--ease), transform var(--dur) var(--ease);
    }

    .usr-stat:hover {
        box-shadow: var(--shadow);
        transform: translateY(-2px);
    }

    .usr-stat-icon {
        width: 40px;
        height: 40px;
        border-radius: var(--radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .usr-stat-icon svg {
        width: 19px;
        height: 19px;
        stroke: currentColor;
    }

    .usr-stat-icon.blue {
        background: var(--primary-light);
        color: var(--primary);
    }

    .usr-stat-icon.green {
        background: #DCFCE7;
        color: #166534;
    }

    .usr-stat-icon.red {
        background: #FEE2E2;
        color: #991B1B;
    }

    .usr-stat-num {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--text);
        line-height: 1;
    }

    .usr-stat-label {
        font-size: .72rem;
        color: var(--text-muted);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: .05em;
        margin-top: .2rem;
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
        font-size: .78rem;
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
        min-width: 220px;
    }

    .filter-actions {
        display: flex;
        gap: .5rem;
        align-items: flex-end;
        padding-bottom: 1px;
    }

    /* ── TABLE CARD ── */
    .usr-table-card {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
    }

    .usr-table-header {
        padding: 1.2rem 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .usr-table-title {
        display: flex;
        align-items: center;
        gap: .55rem;
        font-size: .95rem;
        font-weight: 700;
        color: var(--text);
    }

    .usr-table-title svg {
        width: 17px;
        height: 17px;
        stroke: var(--primary);
    }

    .usr-table {
        width: 100%;
        border-collapse: collapse;
        font-size: .85rem;
    }

    .usr-table th {
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

    .usr-table td {
        padding: .85rem 1.25rem;
        color: var(--text);
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
    }

    .usr-table tbody tr:last-child td {
        border-bottom: none;
    }

    .usr-table tbody tr {
        transition: background var(--dur) var(--ease);
    }

    .usr-table tbody tr:hover {
        background: var(--bg);
    }

    .usr-table tbody tr.inactive-row {
        opacity: .55;
    }

    /* ── USER CELL ── */
    .user-cell {
        display: flex;
        align-items: center;
        gap: .6rem;
    }

    .user-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--primary-light);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .7rem;
        font-weight: 800;
        text-transform: uppercase;
        flex-shrink: 0;
    }

    .user-name {
        font-weight: 700;
        font-size: .875rem;
    }

    .user-email {
        font-size: .75rem;
        color: var(--text-muted);
        margin-top: .1rem;
    }

    /* ── W/L RECORD ── */
    .wl-record {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        font-size: .8rem;
        font-family: ui-monospace, monospace;
    }

    .wl-win {
        color: #166534;
        font-weight: 700;
    }

    .wl-loss {
        color: #991B1B;
        font-weight: 700;
    }

    .wl-sep {
        color: var(--border);
    }

    .wl-match {
        color: var(--text-muted);
    }

    /* ── STATUS BADGE ── */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        padding: .22rem .65rem;
        border-radius: 999px;
        font-size: .7rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .status-badge svg {
        width: 10px;
        height: 10px;
        stroke: currentColor;
    }

    .badge-active {
        background: #DCFCE7;
        color: #166534;
    }

    .badge-inactive {
        background: #FEE2E2;
        color: #991B1B;
    }

    /* ── REVENUE CELL ── */
    .revenue-cell {
        font-weight: 700;
        color: #166534;
        font-size: .85rem;
    }

    /* ── ACTION BUTTONS ── */
    .action-group {
        display: flex;
        gap: .35rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .btn-toggle-on,
    .btn-toggle-off,
    .btn-resetpw,
    .btn-del {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        font-size: .73rem;
        font-weight: 600;
        padding: .28rem .6rem;
        border-radius: var(--radius-sm);
        cursor: pointer;
        text-decoration: none;
        border: 1px solid transparent;
        transition: all var(--dur) var(--ease);
        white-space: nowrap;
        line-height: 1;
    }

    .btn-toggle-on svg,
    .btn-toggle-off svg,
    .btn-resetpw svg,
    .btn-del svg {
        width: 12px;
        height: 12px;
        stroke: currentColor;
    }

    .btn-toggle-on {
        background: #FEE2E2;
        color: #991B1B;
        border-color: #FECACA;
    }

    .btn-toggle-on:hover {
        background: #FCA5A5;
        border-color: #F87171;
    }

    .btn-toggle-off {
        background: #DCFCE7;
        color: #166534;
        border-color: #BBF7D0;
    }

    .btn-toggle-off:hover {
        background: #86EFAC;
        border-color: #4ADE80;
    }

    .btn-resetpw {
        background: var(--bg);
        color: var(--text-muted);
        border-color: var(--border);
    }

    .btn-resetpw:hover {
        background: #FEF3C7;
        color: #92400E;
        border-color: #FDE68A;
    }

    .btn-del {
        background: #FEF2F2;
        color: #991B1B;
        border-color: #FECACA;
    }

    .btn-del:hover {
        background: #FCA5A5;
        border-color: #F87171;
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
        display: block;
        margin: 0 auto .75rem;
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
        .usr-nav {
            grid-template-columns: repeat(3, 1fr);
        }

        .usr-stats {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 760px) {
        .usr-nav {
            grid-template-columns: repeat(3, 1fr);
        }

        .usr-stats {
            grid-template-columns: 1fr 1fr;
        }

        .usr-table th:nth-child(5),
        .usr-table td:nth-child(5) {
            display: none;
        }
    }

    @media (max-width: 520px) {
        .usr-nav {
            grid-template-columns: repeat(2, 1fr);
        }

        .usr-stats {
            grid-template-columns: 1fr 1fr;
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

<div class="usr-wrapper">
    <div class="container">

        <!-- Page Header -->
        <div class="usr-header">
            <div>
                <h1 class="usr-title">
                    <i data-lucide="users"></i>
                    Kelola Users
                </h1>
                <p class="usr-subtitle">
                    <i data-lucide="clock"></i>
                    <?php echo date('d F Y, H:i'); ?> WIB &nbsp;&middot;&nbsp; <?php echo $totalAll; ?> total user
                    terdaftar
                </p>
            </div>
            <a href="dashboard.php" class="usr-back-btn">
                <i data-lucide="arrow-left"></i>
                Dashboard
            </a>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="usr-alert success">
                <i data-lucide="check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="usr-alert danger">
                <i data-lucide="alert-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Quick Navigation -->
        <div class="usr-nav">
            <a href="manage_bookings.php" class="usr-nav-btn">
                <div class="usr-nav-icon"><i data-lucide="calendar-check"></i></div>
                Kelola Booking
            </a>
            <a href="manage_users.php" class="usr-nav-btn active">
                <div class="usr-nav-icon"><i data-lucide="users"></i></div>
                Kelola Users
            </a>
            <a href="manage_playstation.php" class="usr-nav-btn">
                <div class="usr-nav-icon"><i data-lucide="monitor"></i></div>
                Kelola PS
            </a>
            <a href="manage_tournaments.php" class="usr-nav-btn">
                <div class="usr-nav-icon"><i data-lucide="trophy"></i></div>
                Tournament
            </a>
            <a href="manage_refunds.php" class="usr-nav-btn">
                <div class="usr-nav-icon"><i data-lucide="receipt"></i></div>
                Refund
            </a>
            <a href="reports.php" class="usr-nav-btn">
                <div class="usr-nav-icon"><i data-lucide="bar-chart-2"></i></div>
                Laporan
            </a>
        </div>

        <!-- Mini Stats -->
        <div class="usr-stats">
            <div class="usr-stat">
                <div class="usr-stat-icon blue"><i data-lucide="users"></i></div>
                <div>
                    <div class="usr-stat-num"><?php echo $totalAll; ?></div>
                    <div class="usr-stat-label">Total Users</div>
                </div>
            </div>
            <div class="usr-stat">
                <div class="usr-stat-icon green"><i data-lucide="user-check"></i></div>
                <div>
                    <div class="usr-stat-num"><?php echo $totalActive; ?></div>
                    <div class="usr-stat-label">Users Aktif</div>
                </div>
            </div>
            <div class="usr-stat">
                <div class="usr-stat-icon red"><i data-lucide="user-x"></i></div>
                <div>
                    <div class="usr-stat-num"><?php echo $totalInactive; ?></div>
                    <div class="usr-stat-label">Non-aktif</div>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="filter-card">
            <div class="filter-card-title">
                <i data-lucide="sliders-horizontal"></i>
                Filter Users
            </div>
            <form method="GET">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Cari</label>
                        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>"
                            class="form-control" placeholder="Username / email / phone">
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="filter" class="form-control">
                            <option value="">Semua</option>
                            <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="inactive" <?php echo $filter === 'inactive' ? 'selected' : ''; ?>>Non-aktif
                            </option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary"
                            style="display:inline-flex;align-items:center;gap:.4rem;">
                            <i data-lucide="search" style="width:14px;height:14px;stroke:currentColor;"></i>
                            Filter
                        </button>
                        <a href="manage_users.php" class="btn"
                            style="display:inline-flex;align-items:center;gap:.4rem;">
                            <i data-lucide="x" style="width:14px;height:14px;stroke:currentColor;"></i>
                            Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table Card -->
        <div class="usr-table-card">
            <div class="usr-table-header">
                <div class="usr-table-title">
                    <i data-lucide="list"></i>
                    Daftar Users
                    <span style="font-size:.82rem;color:var(--text-muted);font-weight:400;">&mdash;
                        <?php echo $totalRows; ?> data ditemukan</span>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table class="usr-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Phone</th>
                            <th>W / L / M</th>
                            <th>Booking Paid</th>
                            <th>Total Spent</th>
                            <th>Status</th>
                            <th>Join</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <i data-lucide="users"></i>
                                        <p>Tidak ada user yang ditemukan</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($users as $i => $u):
                            $initial = strtoupper(substr($u['username'], 0, 2));
                            $losses = (int) $u['total_matches'] - (int) $u['total_wins'];
                            ?>
                            <tr class="<?php echo !$u['is_active'] ? 'inactive-row' : ''; ?>">
                                <td style="color:var(--text-muted);font-size:.8rem;"><?php echo $offset + $i + 1; ?></td>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar"><?php echo $initial; ?></div>
                                        <div>
                                            <div class="user-name"><?php echo htmlspecialchars($u['username']); ?></div>
                                            <div class="user-email"><?php echo htmlspecialchars($u['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-size:.83rem;"><?php echo htmlspecialchars($u['phone'] ?? '&mdash;'); ?></td>
                                <td>
                                    <div class="wl-record">
                                        <span class="wl-win"><?php echo $u['total_wins']; ?>W</span>
                                        <span class="wl-sep">/</span>
                                        <span class="wl-loss"><?php echo $losses; ?>L</span>
                                        <span class="wl-sep">/</span>
                                        <span class="wl-match"><?php echo $u['total_matches']; ?>M</span>
                                    </div>
                                </td>
                                <td style="font-weight:600;"><?php echo $u['paid_bookings']; ?>x</td>
                                <td class="revenue-cell">
                                    Rp&nbsp;<?php echo number_format($u['total_spent'], 0, ',', '.'); ?></td>
                                <td>
                                    <span
                                        class="status-badge <?php echo $u['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                        <i data-lucide="<?php echo $u['is_active'] ? 'check-circle' : 'x-circle'; ?>"></i>
                                        <?php echo $u['is_active'] ? 'Aktif' : 'Non-aktif'; ?>
                                    </span>
                                </td>
                                <td style="font-size:.8rem;white-space:nowrap;color:var(--text-muted);">
                                    <?php echo date('d M Y', strtotime($u['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <!-- Toggle aktif/nonaktif -->
                                        <a href="?toggle=<?php echo $u['id']; ?>&q=<?php echo urlencode($search); ?>&filter=<?php echo $filter; ?>&page=<?php echo $page; ?>"
                                            class="<?php echo $u['is_active'] ? 'btn-toggle-on' : 'btn-toggle-off'; ?>"
                                            onclick="return confirm('Ubah status user ini?')">
                                            <i data-lucide="<?php echo $u['is_active'] ? 'user-x' : 'user-check'; ?>"></i>
                                            <?php echo $u['is_active'] ? 'Non-aktifkan' : 'Aktifkan'; ?>
                                        </a>
                                        <!-- Reset password -->
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" name="reset_password" class="btn-resetpw"
                                                onclick="return confirm('Reset password user ini ke: rental123?')">
                                                <i data-lucide="key"></i>
                                                Reset PW
                                            </button>
                                        </form>
                                        <!-- Hapus -->
                                        <a href="?delete=<?php echo $u['id']; ?>" class="btn-del"
                                            onclick="return confirm('Hapus user ini secara permanen?')">
                                            <i data-lucide="trash-2"></i>
                                            Hapus
                                        </a>
                                    </div>
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
                        <a href="?page=<?php echo $i; ?>&q=<?php echo urlencode($search); ?>&filter=<?php echo $filter; ?>"
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
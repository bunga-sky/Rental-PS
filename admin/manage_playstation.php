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

// ---- Tambah PS ----
if (isset($_POST['add_ps'])) {
    $type = in_array($_POST['type'], ['PS4', 'PS5']) ? $_POST['type'] : '';
    $name = trim($_POST['name']);
    $status = in_array($_POST['status'], ['available', 'maintenance']) ? $_POST['status'] : 'available';

    if (!$type || !$name) {
        $error = 'Type dan nama wajib diisi';
    } else {
        $stmt = $conn->prepare("INSERT INTO playstation (type, name, status) VALUES (?,?,?)");
        $stmt->bind_param('sss', $type, $name, $status);
        if ($stmt->execute()) {
            $newId = $conn->insert_id;
            logActivity($conn, $adminId, 'admin.ps_added', 'playstation', $newId, "Tambah $type: $name");
            $success = "PlayStation \"$name\" berhasil ditambahkan!";
        } else {
            $error = 'Gagal menambahkan PlayStation';
        }
    }
}

// ---- Update PS ----
if (isset($_POST['update_ps'])) {
    $psId = (int) $_POST['ps_id'];
    $name = trim($_POST['name']);
    $status = in_array($_POST['status'], ['available', 'booked', 'maintenance']) ? $_POST['status'] : 'available';

    $stmt = $conn->prepare("UPDATE playstation SET name=?, status=? WHERE id=?");
    $stmt->bind_param('ssi', $name, $status, $psId);
    if ($stmt->execute()) {
        logActivity($conn, $adminId, 'admin.ps_updated', 'playstation', $psId, "Update PS #$psId → status: $status");
        $success = "PlayStation #$psId berhasil diupdate!";
    } else {
        $error = 'Gagal update PlayStation';
    }
}

// ---- Hapus PS ----
if (isset($_GET['delete'])) {
    $psId = (int) $_GET['delete'];

    $check = $conn->prepare("
        SELECT COUNT(*) AS total FROM bookings
        WHERE ps_id=? AND status IN ('pending','confirmed')
    ");
    $check->bind_param('i', $psId);
    $check->execute();
    $hasBooking = (int) $check->get_result()->fetch_assoc()['total'];

    if ($hasBooking > 0) {
        $error = 'Tidak bisa hapus PS yang masih ada booking aktif!';
    } else {
        $stmt = $conn->prepare("DELETE FROM playstation WHERE id=?");
        $stmt->bind_param('i', $psId);
        if ($stmt->execute()) {
            logActivity($conn, $adminId, 'admin.ps_deleted', 'playstation', $psId, "Hapus PS #$psId");
            $success = 'PlayStation berhasil dihapus';
        }
    }
}

// ---- Fetch data PS ----
$psUnits = $conn->query("
    SELECT p.*,
           (SELECT COUNT(*) FROM bookings b WHERE b.ps_id=p.id AND b.status IN ('pending','confirmed')) AS active_bookings,
           (SELECT COUNT(*) FROM bookings b WHERE b.ps_id=p.id AND b.payment_status='paid') AS total_paid_bookings,
           (SELECT COALESCE(SUM(b.total_price),0) FROM bookings b WHERE b.ps_id=p.id AND b.payment_status='paid') AS total_revenue
    FROM playstation p
    ORDER BY p.type, p.id
")->fetch_all(MYSQLI_ASSOC);

// Jadwal hari ini per PS
$todaySchedules = [];
$res = $conn->query("
    SELECT ps_sched.ps_id, ps_sched.start_time, ps_sched.end_time, u.username
    FROM playstation_schedules ps_sched
    JOIN bookings b ON ps_sched.booking_id=b.id
    JOIN users u ON b.user_id=u.id
    WHERE ps_sched.schedule_date=CURDATE() AND ps_sched.is_active=1
    ORDER BY ps_sched.start_time
");
while ($row = $res->fetch_assoc()) {
    $todaySchedules[$row['ps_id']][] = $row;
}

// Count stats
$totalPS4 = count(array_filter($psUnits, fn($p) => $p['type'] === 'PS4'));
$totalPS5 = count(array_filter($psUnits, fn($p) => $p['type'] === 'PS5'));
$totalAvailable = count(array_filter($psUnits, fn($p) => $p['status'] === 'available'));
$totalMaintenance = count(array_filter($psUnits, fn($p) => $p['status'] === 'maintenance'));

include '../includes/header.php';
?>

<style>
/* ── MANAGE PLAYSTATION ── */
.ps-wrapper {
    padding: 2.5rem 0 5rem;
}

/* ── PAGE HEADER ── */
.ps-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 2rem;
    padding-bottom: 1.75rem;
    border-bottom: 1px solid var(--border);
}

.ps-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text);
    letter-spacing: -.02em;
    display: flex;
    align-items: center;
    gap: .65rem;
    margin-bottom: .2rem;
}

.ps-title svg { width: 28px; height: 28px; stroke: var(--primary); }

.ps-subtitle {
    font-size: .875rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: .4rem;
}

.ps-subtitle svg { width: 13px; height: 13px; stroke: currentColor; opacity: .6; }

.ps-back-btn {
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

.ps-back-btn svg { width: 14px; height: 14px; stroke: currentColor; }
.ps-back-btn:hover { background: var(--bg); color: var(--text); border-color: var(--text-muted); }

/* ── ALERT ── */
.ps-alert {
    display: flex;
    align-items: center;
    gap: .75rem;
    border-radius: var(--radius-sm);
    padding: .9rem 1.1rem;
    margin-bottom: 1.5rem;
    font-size: .875rem;
}

.ps-alert svg { width: 17px; height: 17px; stroke: currentColor; flex-shrink: 0; }

.ps-alert.success {
    background: #F0FDF4;
    color: #166534;
    border: 1px solid #BBF7D0;
    border-left: 4px solid #22C55E;
}

.ps-alert.danger {
    background: #FEF2F2;
    color: #991B1B;
    border: 1px solid #FECACA;
    border-left: 4px solid var(--danger);
}

/* ── QUICK NAV ── */
.ps-nav {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: .75rem;
    margin-bottom: 2rem;
}

.ps-nav-btn {
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

.ps-nav-btn:hover,
.ps-nav-btn.active {
    background: var(--primary-light);
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.ps-nav-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-sm);
    background: var(--bg);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background var(--dur) var(--ease);
}

.ps-nav-btn:hover .ps-nav-icon,
.ps-nav-btn.active .ps-nav-icon {
    background: var(--primary-light);
}

.ps-nav-icon svg { width: 20px; height: 20px; stroke: var(--primary); }

/* ── MINI STATS ── */
.ps-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

.ps-stat {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1.1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: .9rem;
}

.ps-stat-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.ps-stat-icon svg { width: 19px; height: 19px; stroke: currentColor; }
.ps-stat-icon.blue   { background: var(--primary-light); color: var(--primary); }
.ps-stat-icon.purple { background: #F5F3FF; color: #6D28D9; }
.ps-stat-icon.green  { background: #DCFCE7; color: #166534; }
.ps-stat-icon.red    { background: #FEE2E2; color: #991B1B; }

.ps-stat-body {}
.ps-stat-num   { font-size: 1.5rem; font-weight: 800; color: var(--text); line-height: 1; }
.ps-stat-label { font-size: .72rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: .05em; margin-top: .2rem; }

/* ── TWO COL LAYOUT ── */
.ps-top-grid {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
    align-items: start;
}

/* ── CARDS ── */
.ps-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.ps-card-header {
    padding: 1.1rem 1.4rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: .55rem;
    font-size: .9rem;
    font-weight: 700;
    color: var(--text);
}

.ps-card-header svg { width: 16px; height: 16px; stroke: var(--primary); }

.ps-card-body { padding: 1.4rem; }

/* ── ADD FORM ── */
.ps-form-group {
    margin-bottom: 1rem;
}

.ps-form-group label {
    display: block;
    font-size: .78rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-bottom: .35rem;
}

.ps-form-group .form-control {
    width: 100%;
    font-size: .875rem;
}

.ps-price-hint {
    display: flex;
    align-items: flex-start;
    gap: .55rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: .75rem .9rem;
    margin-bottom: 1.1rem;
    font-size: .82rem;
    color: var(--text-muted);
    line-height: 1.5;
}

.ps-price-hint svg { width: 15px; height: 15px; stroke: var(--primary); flex-shrink: 0; margin-top: 1px; }

/* ── SCHEDULE ── */
.sched-ps-name {
    font-size: .82rem;
    font-weight: 700;
    color: var(--text);
    margin-bottom: .4rem;
    display: flex;
    align-items: center;
    gap: .4rem;
}

.sched-ps-name svg { width: 13px; height: 13px; stroke: var(--primary); }

.sched-slot {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: .4rem .75rem;
    margin-bottom: .3rem;
    font-size: .8rem;
}

.sched-time {
    display: flex;
    align-items: center;
    gap: .35rem;
    font-family: ui-monospace, monospace;
    font-weight: 600;
    color: var(--text);
}

.sched-time svg { width: 12px; height: 12px; stroke: var(--primary); }

.sched-user {
    display: flex;
    align-items: center;
    gap: .35rem;
    color: var(--text-muted);
}

.sched-user svg { width: 12px; height: 12px; stroke: currentColor; }

.sched-ps-group { margin-bottom: .9rem; }
.sched-ps-group:last-child { margin-bottom: 0; }

.sched-empty {
    text-align: center;
    padding: 1.75rem 1rem;
    color: var(--text-muted);
    font-size: .875rem;
}

.sched-empty svg { width: 32px; height: 32px; stroke: var(--border); display: block; margin: 0 auto .75rem; }

/* ── MAIN TABLE ── */
.ps-table-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.ps-table-header {
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.ps-table-title {
    display: flex;
    align-items: center;
    gap: .55rem;
    font-size: .95rem;
    font-weight: 700;
    color: var(--text);
}

.ps-table-title svg { width: 17px; height: 17px; stroke: var(--primary); }

.ps-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .85rem;
}

.ps-table th {
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

.ps-table td {
    padding: .9rem 1.25rem;
    color: var(--text);
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.ps-table tbody tr:last-child td { border-bottom: none; }
.ps-table tbody tr { transition: background var(--dur) var(--ease); }
.ps-table tbody tr:hover { background: var(--bg); }

.ps-unit-name { font-weight: 700; }

.ps-type-badge {
    display: inline-flex;
    align-items: center;
    padding: .22rem .65rem;
    border-radius: var(--radius-sm);
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .04em;
}

.ps-type-ps4 { background: #EEF2FF; color: #4338CA; }
.ps-type-ps5 { background: #F5F3FF; color: #6D28D9; }

/* status badges */
.st-badge {
    display: inline-flex;
    align-items: center;
    padding: .22rem .65rem;
    border-radius: 999px;
    font-size: .7rem;
    font-weight: 700;
    white-space: nowrap;
}

.st-available   { background: #DCFCE7; color: #166534; }
.st-booked      { background: var(--primary-light); color: var(--primary); }
.st-maintenance { background: #FEE2E2; color: #991B1B; }

.active-booking-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--primary-light);
    color: var(--primary);
    font-size: .72rem;
    font-weight: 800;
}

.revenue-cell { font-weight: 700; color: #166534; }

/* inline edit */
.inline-form {
    display: flex;
    align-items: center;
    gap: .4rem;
    flex-wrap: wrap;
}

.inline-form input[type="text"] {
    font-size: .8rem;
    padding: .28rem .6rem;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    width: 130px;
    color: var(--text);
    background: var(--white);
}

.inline-form select {
    font-size: .8rem;
    padding: .28rem .5rem;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text);
    background: var(--white);
}

.btn-save {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    font-size: .75rem;
    font-weight: 600;
    padding: .28rem .65rem;
    background: var(--primary);
    color: #fff;
    border: none;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: background var(--dur) var(--ease);
    white-space: nowrap;
}

.btn-save svg { width: 12px; height: 12px; stroke: currentColor; }
.btn-save:hover { background: var(--primary-dark); }

.btn-delete {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    font-size: .75rem;
    font-weight: 600;
    padding: .28rem .65rem;
    background: #FEE2E2;
    color: #991B1B;
    border: 1px solid #FECACA;
    border-radius: var(--radius-sm);
    cursor: pointer;
    text-decoration: none;
    transition: all var(--dur) var(--ease);
    white-space: nowrap;
}

.btn-delete svg { width: 12px; height: 12px; stroke: currentColor; }
.btn-delete:hover { background: #FCA5A5; border-color: #F87171; }

.action-cell { display: flex; flex-direction: column; gap: .4rem; }

/* ── RESPONSIVE ── */
@media (max-width: 1100px) {
    .ps-nav   { grid-template-columns: repeat(3, 1fr); }
    .ps-stats { grid-template-columns: repeat(2, 1fr); }
    .ps-top-grid { grid-template-columns: 1fr; }
}

@media (max-width: 760px) {
    .ps-nav   { grid-template-columns: repeat(3, 1fr); }
    .ps-stats { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 520px) {
    .ps-nav   { grid-template-columns: repeat(2, 1fr); }
    .ps-stats { grid-template-columns: 1fr 1fr; }
}
</style>

<div class="ps-wrapper">
<div class="container">

    <!-- Page Header -->
    <div class="ps-header">
        <div>
            <h1 class="ps-title">
                <i data-lucide="monitor"></i>
                Kelola PlayStation
            </h1>
            <p class="ps-subtitle">
                <i data-lucide="clock"></i>
                <?php echo date('d F Y, H:i'); ?> WIB &nbsp;&middot;&nbsp; <?php echo count($psUnits); ?> unit terdaftar
            </p>
        </div>
        <a href="dashboard.php" class="ps-back-btn">
            <i data-lucide="arrow-left"></i>
            Dashboard
        </a>
    </div>

    <!-- Alerts -->
    <?php if ($success): ?>
            <div class="ps-alert success">
                <i data-lucide="check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
    <?php endif; ?>
    <?php if ($error): ?>
            <div class="ps-alert danger">
                <i data-lucide="alert-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
    <?php endif; ?>

    <!-- Quick Navigation -->
    <div class="ps-nav">
        <a href="manage_bookings.php" class="ps-nav-btn">
            <div class="ps-nav-icon"><i data-lucide="calendar-check"></i></div>
            Kelola Booking
        </a>
        <a href="manage_users.php" class="ps-nav-btn">
            <div class="ps-nav-icon"><i data-lucide="users"></i></div>
            Kelola Users
        </a>
        <a href="manage_playstation.php" class="ps-nav-btn active">
            <div class="ps-nav-icon"><i data-lucide="monitor"></i></div>
            Kelola PS
        </a>
        <a href="manage_tournaments.php" class="ps-nav-btn">
            <div class="ps-nav-icon"><i data-lucide="trophy"></i></div>
            Tournament
        </a>
        <a href="manage_refunds.php" class="ps-nav-btn">
            <div class="ps-nav-icon"><i data-lucide="receipt"></i></div>
            Refund
        </a>
        <a href="reports.php" class="ps-nav-btn">
            <div class="ps-nav-icon"><i data-lucide="bar-chart-2"></i></div>
            Laporan
        </a>
    </div>

    <!-- Mini Stats -->
    <div class="ps-stats">
        <div class="ps-stat">
            <div class="ps-stat-icon blue"><i data-lucide="monitor"></i></div>
            <div class="ps-stat-body">
                <div class="ps-stat-num"><?php echo count($psUnits); ?></div>
                <div class="ps-stat-label">Total Unit</div>
            </div>
        </div>
        <div class="ps-stat">
            <div class="ps-stat-icon purple"><i data-lucide="layers"></i></div>
            <div class="ps-stat-body">
                <div class="ps-stat-num">PS4: <?php echo $totalPS4; ?> &nbsp;/&nbsp; PS5: <?php echo $totalPS5; ?></div>
                <div class="ps-stat-label">Tipe Unit</div>
            </div>
        </div>
        <div class="ps-stat">
            <div class="ps-stat-icon green"><i data-lucide="check-circle"></i></div>
            <div class="ps-stat-body">
                <div class="ps-stat-num"><?php echo $totalAvailable; ?></div>
                <div class="ps-stat-label">Available</div>
            </div>
        </div>
        <div class="ps-stat">
            <div class="ps-stat-icon red"><i data-lucide="wrench"></i></div>
            <div class="ps-stat-body">
                <div class="ps-stat-num"><?php echo $totalMaintenance; ?></div>
                <div class="ps-stat-label">Maintenance</div>
            </div>
        </div>
    </div>

    <!-- Top Grid: Form + Jadwal -->
    <div class="ps-top-grid">

        <!-- Form Tambah PS -->
        <div class="ps-card">
            <div class="ps-card-header">
                <i data-lucide="plus-circle"></i>
                Tambah Unit Baru
            </div>
            <div class="ps-card-body">
                <form method="POST">
                    <div class="ps-form-group">
                        <label>Tipe PlayStation</label>
                        <select name="type" class="form-control" required>
                            <option value="PS4">PS4</option>
                            <option value="PS5">PS5</option>
                        </select>
                    </div>
                    <div class="ps-form-group">
                        <label>Nama Unit</label>
                        <input type="text" name="name" class="form-control"
                               placeholder="Contoh: PS5 &ndash; Unit 3" required>
                    </div>
                    <div class="ps-form-group">
                        <label>Status Awal</label>
                        <select name="status" class="form-control">
                            <option value="available">Available</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    <div class="ps-price-hint">
                        <i data-lucide="info"></i>
                        Harga otomatis: PS4 = Rp <?php echo number_format(PS4_PRICE_PER_HOUR, 0, ',', '.'); ?>/jam &nbsp;&middot;&nbsp;
                        PS5 = Rp <?php echo number_format(PS5_PRICE_PER_HOUR, 0, ',', '.'); ?>/jam
                    </div>
                    <button type="submit" name="add_ps" class="btn btn-primary" style="width:100%;display:flex;align-items:center;justify-content:center;gap:.4rem;">
                        <i data-lucide="plus" style="width:15px;height:15px;stroke:currentColor;"></i>
                        Tambah PlayStation
                    </button>
                </form>
            </div>
        </div>

        <!-- Jadwal Hari Ini -->
        <div class="ps-card">
            <div class="ps-card-header">
                <i data-lucide="calendar"></i>
                Jadwal Hari Ini &mdash; <?php echo date('d F Y'); ?>
            </div>
            <div class="ps-card-body">
                <?php if (empty($todaySchedules)): ?>
                        <div class="sched-empty">
                            <i data-lucide="calendar-x"></i>
                            Belum ada booking hari ini.
                        </div>
                <?php else: ?>
                        <?php foreach ($psUnits as $ps): ?>
                            <?php if (!empty($todaySchedules[$ps['id']])): ?>
                                <div class="sched-ps-group">
                                    <div class="sched-ps-name">
                                        <i data-lucide="monitor"></i>
                                        <?php echo htmlspecialchars($ps['name']); ?>
                                    </div>
                                    <?php foreach ($todaySchedules[$ps['id']] as $sched): ?>
                                        <div class="sched-slot">
                                            <div class="sched-time">
                                                <i data-lucide="clock"></i>
                                                <?php echo substr($sched['start_time'], 0, 5) . ' &ndash; ' . substr($sched['end_time'], 0, 5); ?>
                                            </div>
                                            <div class="sched-user">
                                                <i data-lucide="user"></i>
                                                <?php echo htmlspecialchars($sched['username']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Daftar Unit PS -->
    <div class="ps-table-card">
        <div class="ps-table-header">
            <div class="ps-table-title">
                <i data-lucide="list"></i>
                Daftar Unit PlayStation
            </div>
            <span style="font-size:.82rem;color:var(--text-muted);"><?php echo count($psUnits); ?> unit</span>
        </div>

        <div style="overflow-x: auto;">
            <table class="ps-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Unit</th>
                        <th>Tipe</th>
                        <th>Status</th>
                        <th>Booking Aktif</th>
                        <th>Total Booking</th>
                        <th>Total Revenue</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($psUnits as $ps):
                    $stClass = match ($ps['status']) {
                        'available' => 'st-available',
                        'booked' => 'st-booked',
                        'maintenance' => 'st-maintenance',
                        default => 'st-available',
                    };
                    $typeClass = $ps['type'] === 'PS5' ? 'ps-type-ps5' : 'ps-type-ps4';
                    ?>
                    <tr>
                        <td style="color:var(--text-muted);font-size:.82rem;"><?php echo $ps['id']; ?></td>
                        <td><span class="ps-unit-name"><?php echo htmlspecialchars($ps['name']); ?></span></td>
                        <td><span class="ps-type-badge <?php echo $typeClass; ?>"><?php echo $ps['type']; ?></span></td>
                        <td><span class="st-badge <?php echo $stClass; ?>"><?php echo ucfirst($ps['status']); ?></span></td>
                        <td>
                            <?php if ($ps['active_bookings'] > 0): ?>
                                    <span class="active-booking-count"><?php echo $ps['active_bookings']; ?></span>
                            <?php else: ?>
                                    <span style="color:var(--text-muted);font-size:.82rem;">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight:600;"><?php echo $ps['total_paid_bookings']; ?></td>
                        <td class="revenue-cell">Rp&nbsp;<?php echo number_format($ps['total_revenue'], 0, ',', '.'); ?></td>
                        <td>
                            <div class="action-cell">
                                <form method="POST">
                                    <input type="hidden" name="ps_id" value="<?php echo $ps['id']; ?>">
                                    <div class="inline-form">
                                        <input type="text" name="name"
                                               value="<?php echo htmlspecialchars($ps['name']); ?>"
                                               placeholder="Nama unit">
                                        <select name="status">
                                            <?php foreach (['available', 'booked', 'maintenance'] as $s): ?>
                                                <option value="<?php echo $s; ?>" <?php echo $ps['status'] === $s ? 'selected' : ''; ?>>
                                                    <?php echo ucfirst($s); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="update_ps" class="btn-save">
                                            <i data-lucide="check"></i>
                                            Simpan
                                        </button>
                                    </div>
                                </form>
                                <a href="?delete=<?php echo $ps['id']; ?>"
                                   class="btn-delete"
                                   onclick="return confirm('Yakin hapus PlayStation ini?')">
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
    </div>

</div>
</div>

<?php include '../includes/footer.php'; ?>
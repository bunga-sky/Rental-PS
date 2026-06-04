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

// Tambah tournament
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tournament'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $max_participants = (int) $_POST['max_participants'];

    $stmt = $conn->prepare("INSERT INTO tournaments (name, description, start_date, end_date, max_participants) VALUES (?,?,?,?,?)");
    $stmt->bind_param('ssssi', $name, $description, $start_date, $end_date, $max_participants);
    if ($stmt->execute()) {
        $success = "Tournament \"$name\" berhasil ditambahkan!";
    } else {
        $error = 'Gagal menambahkan tournament!';
    }
}

// Hapus tournament
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM tournaments WHERE id=?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $success = 'Tournament berhasil dihapus!';
    } else {
        $error = 'Gagal menghapus tournament!';
    }
}

// Fetch tournaments
$tournaments = $conn->query("
    SELECT t.*,
           (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id=t.id) AS participants
    FROM tournaments t
    ORDER BY t.start_date DESC
")->fetch_all(MYSQLI_ASSOC);

// Summary counts
$totalUpcoming = count(array_filter($tournaments, fn($t) => $t['status'] === 'upcoming'));
$totalOngoing = count(array_filter($tournaments, fn($t) => $t['status'] === 'ongoing'));
$totalFinished = count(array_filter($tournaments, fn($t) => $t['status'] === 'finished'));

include '../includes/header.php';
?>

<style>
/* ── MANAGE TOURNAMENTS ── */
.trn-wrapper {
    padding: 2.5rem 0 5rem;
}

/* ── PAGE HEADER ── */
.trn-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 2rem;
    padding-bottom: 1.75rem;
    border-bottom: 1px solid var(--border);
}

.trn-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text);
    letter-spacing: -.02em;
    display: flex;
    align-items: center;
    gap: .65rem;
    margin-bottom: .2rem;
}

.trn-title svg { width: 28px; height: 28px; stroke: var(--primary); }

.trn-subtitle {
    font-size: .875rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: .4rem;
}

.trn-subtitle svg { width: 13px; height: 13px; stroke: currentColor; opacity: .6; }

.trn-back-btn {
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

.trn-back-btn svg { width: 14px; height: 14px; stroke: currentColor; }
.trn-back-btn:hover { background: var(--bg); color: var(--text); border-color: var(--text-muted); }

/* ── ALERT ── */
.trn-alert {
    display: flex;
    align-items: center;
    gap: .75rem;
    border-radius: var(--radius-sm);
    padding: .9rem 1.1rem;
    margin-bottom: 1.5rem;
    font-size: .875rem;
}

.trn-alert svg { width: 17px; height: 17px; stroke: currentColor; flex-shrink: 0; }

.trn-alert.success {
    background: #F0FDF4;
    color: #166534;
    border: 1px solid #BBF7D0;
    border-left: 4px solid #22C55E;
}

.trn-alert.danger {
    background: #FEF2F2;
    color: #991B1B;
    border: 1px solid #FECACA;
    border-left: 4px solid var(--danger);
}

/* ── QUICK NAV ── */
.trn-nav {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: .75rem;
    margin-bottom: 2rem;
}

.trn-nav-btn {
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

.trn-nav-btn:hover,
.trn-nav-btn.active {
    background: var(--primary-light);
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.trn-nav-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-sm);
    background: var(--bg);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background var(--dur) var(--ease);
}

.trn-nav-btn:hover .trn-nav-icon,
.trn-nav-btn.active .trn-nav-icon {
    background: var(--primary-light);
}

.trn-nav-icon svg { width: 20px; height: 20px; stroke: var(--primary); }

/* ── MINI STATS ── */
.trn-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

.trn-stat {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1.1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: .9rem;
    transition: box-shadow var(--dur) var(--ease), transform var(--dur) var(--ease);
}

.trn-stat:hover { box-shadow: var(--shadow); transform: translateY(-2px); }

.trn-stat-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.trn-stat-icon svg { width: 19px; height: 19px; stroke: currentColor; }
.trn-stat-icon.yellow { background: #FEFCE8; color: #A16207; }
.trn-stat-icon.blue   { background: var(--primary-light); color: var(--primary); }
.trn-stat-icon.green  { background: #DCFCE7; color: #166534; }
.trn-stat-icon.purple { background: #F5F3FF; color: #6D28D9; }

.trn-stat-num   { font-size: 1.5rem; font-weight: 800; color: var(--text); line-height: 1; }
.trn-stat-label { font-size: .72rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: .05em; margin-top: .2rem; }

/* ── TWO COL LAYOUT ── */
.trn-top-grid {
    display: grid;
    grid-template-columns: 360px 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
    align-items: start;
}

/* ── FORM CARD ── */
.trn-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.trn-card-header {
    padding: 1.1rem 1.4rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: .55rem;
    font-size: .9rem;
    font-weight: 700;
    color: var(--text);
}

.trn-card-header svg { width: 16px; height: 16px; stroke: var(--primary); }

.trn-card-body { padding: 1.4rem; }

.trn-form-group { margin-bottom: 1rem; }

.trn-form-group label {
    display: block;
    font-size: .78rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-bottom: .35rem;
}

.trn-form-group .form-control { width: 100%; font-size: .875rem; }
.trn-form-group textarea.form-control { resize: vertical; min-height: 72px; }

/* ── TABLE CARD ── */
.trn-table-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.trn-table-header {
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.trn-table-title {
    display: flex;
    align-items: center;
    gap: .55rem;
    font-size: .95rem;
    font-weight: 700;
    color: var(--text);
}

.trn-table-title svg { width: 17px; height: 17px; stroke: var(--primary); }

.trn-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .85rem;
}

.trn-table th {
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

.trn-table td {
    padding: .9rem 1.25rem;
    color: var(--text);
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.trn-table tbody tr:last-child td { border-bottom: none; }
.trn-table tbody tr { transition: background var(--dur) var(--ease); }
.trn-table tbody tr:hover { background: var(--bg); }

/* ── TOURNAMENT NAME CELL ── */
.trn-name-cell { font-weight: 700; }
.trn-desc-cell { font-size: .75rem; color: var(--text-muted); margin-top: .15rem; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* ── PARTICIPANTS BAR ── */
.participant-wrap { display: flex; flex-direction: column; gap: .3rem; min-width: 90px; }
.participant-nums { font-size: .8rem; font-weight: 600; display: flex; justify-content: space-between; }
.participant-bar  { height: 5px; background: var(--border); border-radius: 999px; overflow: hidden; }
.participant-fill { height: 100%; background: var(--primary); border-radius: 999px; transition: width .4s ease; }

/* ── DATE CELL ── */
.date-cell { font-size: .8rem; color: var(--text-muted); white-space: nowrap; }
.date-cell strong { display: block; color: var(--text); font-size: .83rem; margin-bottom: .1rem; }

/* ── STATUS BADGE ── */
.trn-badge {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .22rem .65rem;
    border-radius: 999px;
    font-size: .7rem;
    font-weight: 700;
    white-space: nowrap;
}

.trn-badge svg { width: 10px; height: 10px; stroke: currentColor; }
.trn-upcoming { background: #FEF3C7; color: #92400E; }
.trn-ongoing  { background: var(--primary-light); color: var(--primary); }
.trn-finished { background: #DCFCE7; color: #166534; }
.trn-cancelled { background: #FEE2E2; color: #991B1B; }

/* ── DELETE BTN ── */
.btn-del {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    font-size: .73rem;
    font-weight: 600;
    padding: .28rem .65rem;
    background: #FEF2F2;
    color: #991B1B;
    border: 1px solid #FECACA;
    border-radius: var(--radius-sm);
    cursor: pointer;
    text-decoration: none;
    transition: all var(--dur) var(--ease);
    white-space: nowrap;
}

.btn-del svg { width: 12px; height: 12px; stroke: currentColor; }
.btn-del:hover { background: #FCA5A5; border-color: #F87171; }

/* ── EMPTY STATE ── */
.empty-state {
    text-align: center;
    padding: 3.5rem 2rem;
    color: var(--text-muted);
}

.empty-state svg { width: 44px; height: 44px; stroke: var(--border); display: block; margin: 0 auto .75rem; }
.empty-state p { font-size: .9rem; }

/* ── RESPONSIVE ── */
@media (max-width: 1100px) {
    .trn-nav    { grid-template-columns: repeat(3, 1fr); }
    .trn-stats  { grid-template-columns: repeat(2, 1fr); }
    .trn-top-grid { grid-template-columns: 1fr; }
}

@media (max-width: 760px) {
    .trn-nav   { grid-template-columns: repeat(3, 1fr); }
    .trn-stats { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 520px) {
    .trn-nav   { grid-template-columns: repeat(2, 1fr); }
    .trn-stats { grid-template-columns: 1fr 1fr; }
}
</style>

<div class="trn-wrapper">
<div class="container">

    <!-- Page Header -->
    <div class="trn-header">
        <div>
            <h1 class="trn-title">
                <i data-lucide="trophy"></i>
                Kelola Tournament
            </h1>
            <p class="trn-subtitle">
                <i data-lucide="clock"></i>
                <?php echo date('d F Y, H:i'); ?> WIB &nbsp;&middot;&nbsp; <?php echo count($tournaments); ?> tournament terdaftar
            </p>
        </div>
        <a href="dashboard.php" class="trn-back-btn">
            <i data-lucide="arrow-left"></i>
            Dashboard
        </a>
    </div>

    <!-- Alerts -->
    <?php if ($success): ?>
            <div class="trn-alert success">
                <i data-lucide="check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
    <?php endif; ?>
    <?php if ($error): ?>
            <div class="trn-alert danger">
                <i data-lucide="alert-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
    <?php endif; ?>

    <!-- Quick Navigation -->
    <div class="trn-nav">
        <a href="manage_bookings.php" class="trn-nav-btn">
            <div class="trn-nav-icon"><i data-lucide="calendar-check"></i></div>
            Kelola Booking
        </a>
        <a href="manage_users.php" class="trn-nav-btn">
            <div class="trn-nav-icon"><i data-lucide="users"></i></div>
            Kelola Users
        </a>
        <a href="manage_playstation.php" class="trn-nav-btn">
            <div class="trn-nav-icon"><i data-lucide="monitor"></i></div>
            Kelola PS
        </a>
        <a href="manage_tournaments.php" class="trn-nav-btn active">
            <div class="trn-nav-icon"><i data-lucide="trophy"></i></div>
            Tournament
        </a>
        <a href="manage_refunds.php" class="trn-nav-btn">
            <div class="trn-nav-icon"><i data-lucide="receipt"></i></div>
            Refund
        </a>
        <a href="reports.php" class="trn-nav-btn">
            <div class="trn-nav-icon"><i data-lucide="bar-chart-2"></i></div>
            Laporan
        </a>
    </div>

    <!-- Mini Stats -->
    <div class="trn-stats">
        <div class="trn-stat">
            <div class="trn-stat-icon yellow"><i data-lucide="trophy"></i></div>
            <div>
                <div class="trn-stat-num"><?php echo count($tournaments); ?></div>
                <div class="trn-stat-label">Total Tournament</div>
            </div>
        </div>
        <div class="trn-stat">
            <div class="trn-stat-icon blue"><i data-lucide="clock"></i></div>
            <div>
                <div class="trn-stat-num"><?php echo $totalUpcoming; ?></div>
                <div class="trn-stat-label">Upcoming</div>
            </div>
        </div>
        <div class="trn-stat">
            <div class="trn-stat-icon purple"><i data-lucide="zap"></i></div>
            <div>
                <div class="trn-stat-num"><?php echo $totalOngoing; ?></div>
                <div class="trn-stat-label">Sedang Berjalan</div>
            </div>
        </div>
        <div class="trn-stat">
            <div class="trn-stat-icon green"><i data-lucide="check-circle"></i></div>
            <div>
                <div class="trn-stat-num"><?php echo $totalFinished; ?></div>
                <div class="trn-stat-label">Selesai</div>
            </div>
        </div>
    </div>

    <!-- Top Grid: Form + Table -->
    <div class="trn-top-grid">

        <!-- Form Tambah Tournament -->
        <div class="trn-card">
            <div class="trn-card-header">
                <i data-lucide="plus-circle"></i>
                Tambah Tournament Baru
            </div>
            <div class="trn-card-body">
                <form method="POST">
                    <div class="trn-form-group">
                        <label>Nama Tournament</label>
                        <input type="text" name="name" class="form-control" placeholder="Nama tournament" required>
                    </div>
                    <div class="trn-form-group">
                        <label>Deskripsi</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Deskripsi singkat..." required></textarea>
                    </div>
                    <div class="trn-form-group">
                        <label>Tanggal Mulai</label>
                        <input type="datetime-local" name="start_date" class="form-control" required>
                    </div>
                    <div class="trn-form-group">
                        <label>Tanggal Selesai</label>
                        <input type="datetime-local" name="end_date" class="form-control" required>
                    </div>
                    <div class="trn-form-group">
                        <label>Max Peserta</label>
                        <input type="number" name="max_participants" class="form-control" min="2" value="16" required>
                    </div>
                    <button type="submit" name="add_tournament" class="btn btn-primary"
                            style="width:100%;display:flex;align-items:center;justify-content:center;gap:.4rem;">
                        <i data-lucide="plus" style="width:15px;height:15px;stroke:currentColor;"></i>
                        Tambah Tournament
                    </button>
                </form>
            </div>
        </div>

        <!-- Daftar Tournament -->
        <div class="trn-table-card">
            <div class="trn-table-header">
                <div class="trn-table-title">
                    <i data-lucide="list"></i>
                    Daftar Tournament
                </div>
                <span style="font-size:.82rem;color:var(--text-muted);"><?php echo count($tournaments); ?> data</span>
            </div>

            <div style="overflow-x: auto;">
                <table class="trn-table">
                    <thead>
                        <tr>
                            <th>Tournament</th>
                            <th>Peserta</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($tournaments)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i data-lucide="trophy"></i>
                                        <p>Belum ada tournament yang dibuat</p>
                                    </div>
                                </td>
                            </tr>
                    <?php endif; ?>
                    <?php foreach ($tournaments as $t):
                        $statusClass = match ($t['status'] ?? 'upcoming') {
                            'upcoming' => 'trn-upcoming',
                            'ongoing' => 'trn-ongoing',
                            'finished' => 'trn-finished',
                            'cancelled' => 'trn-cancelled',
                            default => 'trn-upcoming',
                        };
                        $statusIcon = match ($t['status'] ?? 'upcoming') {
                            'upcoming' => 'clock',
                            'ongoing' => 'zap',
                            'finished' => 'check-circle',
                            'cancelled' => 'x-circle',
                            default => 'clock',
                        };
                        $fillPct = $t['max_participants'] > 0
                            ? min(100, round(($t['participants'] / $t['max_participants']) * 100))
                            : 0;
                        ?>
                            <tr>
                                <td>
                                    <div class="trn-name-cell"><?php echo htmlspecialchars($t['name']); ?></div>
                                    <?php if (!empty($t['description'])): ?>
                                        <div class="trn-desc-cell"><?php echo htmlspecialchars($t['description']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="participant-wrap">
                                        <div class="participant-nums">
                                            <span><?php echo $t['participants']; ?> / <?php echo $t['max_participants']; ?></span>
                                            <span style="color:var(--text-muted);font-size:.72rem;"><?php echo $fillPct; ?>%</span>
                                        </div>
                                        <div class="participant-bar">
                                            <div class="participant-fill" style="width:<?php echo $fillPct; ?>%;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="date-cell">
                                        <strong><?php echo date('d M Y', strtotime($t['start_date'])); ?></strong>
                                        s/d <?php echo date('d M Y', strtotime($t['end_date'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="trn-badge <?php echo $statusClass; ?>">
                                        <i data-lucide="<?php echo $statusIcon; ?>"></i>
                                        <?php echo ucfirst($t['status'] ?? 'upcoming'); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?delete=<?php echo $t['id']; ?>"
                                       class="btn-del"
                                       onclick="return confirm('Yakin hapus tournament ini?')">
                                        <i data-lucide="trash-2"></i>
                                        Hapus
                                    </a>
                                </td>
                            </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>
</div>

<?php include '../includes/footer.php'; ?>
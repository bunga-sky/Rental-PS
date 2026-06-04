<?php
require_once '../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Daftar tournament
if (isset($_POST['join_tournament'])) {
    $tournament_id = (int) $_POST['tournament_id'];

    $check_stmt = $conn->prepare("SELECT * FROM tournament_participants WHERE tournament_id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $tournament_id, $user_id);
    $check_stmt->execute();

    if ($check_stmt->get_result()->num_rows > 0) {
        $error = "Kamu sudah terdaftar di tournament ini.";
    } else {
        $t_stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
        $t_stmt->bind_param("i", $tournament_id);
        $t_stmt->execute();
        $tournament = $t_stmt->get_result()->fetch_assoc();

        $c_stmt = $conn->prepare("SELECT COUNT(*) as total FROM tournament_participants WHERE tournament_id = ?");
        $c_stmt->bind_param("i", $tournament_id);
        $c_stmt->execute();
        $count = $c_stmt->get_result()->fetch_assoc();

        if ($count['total'] >= $tournament['max_participants']) {
            $error = "Maaf, kuota tournament sudah penuh.";
        } else {
            $ins_stmt = $conn->prepare("INSERT INTO tournament_participants (tournament_id, user_id) VALUES (?, ?)");
            $ins_stmt->bind_param("ii", $tournament_id, $user_id);
            if ($ins_stmt->execute()) {
                $success = "Berhasil mendaftar tournament!";
            } else {
                $error = "Pendaftaran gagal. Silakan coba lagi.";
            }
        }
    }
}

// Ambil data tournament aktif
$active_query = "SELECT t.*,
                 (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.id) as participants
                 FROM tournaments t
                 WHERE t.status = 'ongoing' OR t.status = 'upcoming'
                 ORDER BY t.start_date ASC";
$active_result = $conn->query($active_query);

// Ambil tournament user
$my_stmt = $conn->prepare("
    SELECT t.*, tp.registration_date
    FROM tournaments t
    JOIN tournament_participants tp ON t.id = tp.tournament_id
    WHERE tp.user_id = ?
    ORDER BY t.start_date DESC
");
$my_stmt->bind_param("i", $user_id);
$my_stmt->execute();
$my_result = $my_stmt->get_result();

// Hitung stats
$total_joined = $my_result->num_rows;
$total_active = $active_result->num_rows;

// Reset pointer
$my_result->data_seek(0);
$active_result->data_seek(0);

include '../includes/header.php';
?>

<style>
    /* ── TOURNAMENT PAGE ── */
    .tournament-wrapper {
        padding: 2.5rem 0 5rem;
    }

    /* ── PAGE HEADER ── */
    .tournament-page-header {
        margin-bottom: 2rem;
        padding-bottom: 1.75rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .tournament-page-title {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--text);
        letter-spacing: -.02em;
        display: flex;
        align-items: center;
        gap: .65rem;
        margin-bottom: .25rem;
    }

    .tournament-page-title svg {
        width: 28px;
        height: 28px;
        stroke: var(--primary);
    }

    .tournament-page-desc {
        font-size: .9rem;
        color: var(--text-muted);
    }

    /* ── STATS ROW ── */
    .tournament-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .t-stat-card {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: box-shadow var(--dur) var(--ease), transform var(--dur) var(--ease);
    }

    .t-stat-card:hover {
        box-shadow: var(--shadow);
        transform: translateY(-2px);
    }

    .t-stat-icon {
        width: 44px;
        height: 44px;
        border-radius: var(--radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .t-stat-icon svg {
        width: 22px;
        height: 22px;
        stroke: currentColor;
    }

    .t-stat-icon.orange {
        background: #FEF3C7;
        color: #92400E;
    }

    .t-stat-icon.blue {
        background: var(--primary-light);
        color: var(--primary);
    }

    .t-stat-icon.green {
        background: #DCFCE7;
        color: #166534;
    }

    .t-stat-num {
        font-size: 1.6rem;
        font-weight: 800;
        color: var(--text);
        line-height: 1;
        margin-bottom: .15rem;
    }

    .t-stat-label {
        font-size: .75rem;
        color: var(--text-muted);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    /* ── MAIN GRID ── */
    .tournament-grid {
        display: grid;
        grid-template-columns: 1fr 360px;
        gap: 1.5rem;
        align-items: start;
    }

    /* ── SECTION CARD ── */
    .section-card {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
    }

    .section-card-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .section-card-title {
        display: flex;
        align-items: center;
        gap: .6rem;
        font-size: .95rem;
        font-weight: 700;
        color: var(--text);
    }

    .section-card-title svg {
        width: 18px;
        height: 18px;
        stroke: var(--primary);
    }

    .section-card-count {
        font-size: .75rem;
        font-weight: 700;
        color: var(--text-muted);
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 999px;
        padding: .2rem .65rem;
    }

    /* ── TOURNAMENT ITEM (Active) ── */
    .t-item {
        padding: 1.5rem;
        transition: background var(--dur) var(--ease);
    }

    .t-item:hover {
        background: var(--bg);
    }

    .t-item+.t-item {
        border-top: 1px solid var(--border);
    }

    .t-item-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: .75rem;
    }

    .t-item-name {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text);
        letter-spacing: -.01em;
        line-height: 1.3;
    }

    .t-item-desc {
        font-size: .83rem;
        color: var(--text-muted);
        line-height: 1.55;
        margin-bottom: 1rem;
    }

    .t-item-meta {
        display: flex;
        align-items: center;
        gap: 1.25rem;
        flex-wrap: wrap;
        margin-bottom: 1.1rem;
    }

    .t-meta-item {
        display: flex;
        align-items: center;
        gap: .35rem;
        font-size: .8rem;
        color: var(--text-muted);
    }

    .t-meta-item svg {
        width: 13px;
        height: 13px;
        stroke: currentColor;
        opacity: .7;
        flex-shrink: 0;
    }

    .t-meta-item strong {
        color: var(--text);
        font-weight: 600;
    }

    /* Quota bar */
    .quota-wrap {
        margin-bottom: 1.1rem;
    }

    .quota-label {
        display: flex;
        justify-content: space-between;
        font-size: .75rem;
        color: var(--text-muted);
        margin-bottom: .35rem;
    }

    .quota-label strong {
        color: var(--text);
        font-weight: 600;
    }

    .quota-bar {
        height: 6px;
        background: var(--card-bg);
        border-radius: 999px;
        overflow: hidden;
    }

    .quota-fill {
        height: 100%;
        border-radius: 999px;
        background: var(--primary);
        transition: width .4s var(--ease);
    }

    .quota-fill.full {
        background: var(--danger);
    }

    .quota-fill.near {
        background: var(--warning);
    }

    /* Status badge overrides */
    .badge-ongoing {
        background: var(--primary);
        color: var(--white);
    }

    .badge-upcoming {
        background: #FEF3C7;
        color: #92400E;
        border: 1px solid #FDE68A;
    }

    .badge-finished {
        background: var(--card-bg);
        color: var(--text-muted);
        border: 1px solid var(--border);
    }

    /* Join form */
    .t-join-form {
        display: flex;
        align-items: center;
        justify-content: flex-end;
    }

    /* ── MY TOURNAMENT ITEM ── */
    .my-t-item {
        padding: 1.1rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: background var(--dur) var(--ease);
    }

    .my-t-item:hover {
        background: var(--bg);
    }

    .my-t-item+.my-t-item {
        border-top: 1px solid var(--border);
    }

    .my-t-icon {
        width: 36px;
        height: 36px;
        border-radius: var(--radius-sm);
        background: var(--primary-light);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .my-t-icon svg {
        width: 16px;
        height: 16px;
        stroke: currentColor;
    }

    .my-t-info {
        flex: 1;
        min-width: 0;
    }

    .my-t-name {
        font-size: .875rem;
        font-weight: 700;
        color: var(--text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: .15rem;
    }

    .my-t-date {
        font-size: .75rem;
        color: var(--text-muted);
    }

    /* Empty state */
    .t-empty {
        padding: 3rem 1.5rem;
        text-align: center;
        color: var(--text-muted);
    }

    .t-empty svg {
        width: 40px;
        height: 40px;
        stroke: var(--border);
        margin: 0 auto .75rem;
        display: block;
    }

    .t-empty p {
        font-size: .875rem;
        line-height: 1.6;
    }

    /* ── ALERTS ── */
    .alert-row {
        margin-bottom: 1.25rem;
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 900px) {
        .tournament-grid {
            grid-template-columns: 1fr;
        }

        .tournament-stats {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 540px) {
        .tournament-stats {
            grid-template-columns: 1fr 1fr;
        }

        .tournament-page-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .t-item {
            padding: 1.25rem;
        }

        .my-t-item {
            padding: .9rem 1.25rem;
        }

        .section-card-header {
            padding: 1rem 1.25rem;
        }
    }
</style>

<div class="tournament-wrapper">
    <div class="container">

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert-row">
                <div class="alert alert-success">
                    <i data-lucide="check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert-row">
                <div class="alert alert-danger">
                    <i data-lucide="alert-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="tournament-page-header">
            <div>
                <h1 class="tournament-page-title">
                    <i data-lucide="trophy"></i>
                    Tournament
                </h1>
                <p class="tournament-page-desc">Ikuti tournament resmi dan buktikan siapa yang terbaik.</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="tournament-stats">
            <div class="t-stat-card">
                <div class="t-stat-icon orange">
                    <i data-lucide="trophy"></i>
                </div>
                <div>
                    <div class="t-stat-num"><?php echo $total_active; ?></div>
                    <div class="t-stat-label">Tournament Aktif</div>
                </div>
            </div>
            <div class="t-stat-card">
                <div class="t-stat-icon blue">
                    <i data-lucide="user-check"></i>
                </div>
                <div>
                    <div class="t-stat-num"><?php echo $total_joined; ?></div>
                    <div class="t-stat-label">Saya Ikuti</div>
                </div>
            </div>
            <div class="t-stat-card">
                <div class="t-stat-icon green">
                    <i data-lucide="calendar-check"></i>
                </div>
                <div>
                    <div class="t-stat-num">7</div>
                    <div class="t-stat-label">Hari Operasional</div>
                </div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="tournament-grid">

            <!-- LEFT: Tournament Aktif -->
            <div class="section-card">
                <div class="section-card-header">
                    <div class="section-card-title">
                        <i data-lucide="zap"></i>
                        Tournament Aktif
                    </div>
                    <span class="section-card-count"><?php echo $total_active; ?> tersedia</span>
                </div>

                <?php
                $active_result->data_seek(0);
                if ($active_result->num_rows === 0):
                    ?>
                    <div class="t-empty">
                        <i data-lucide="calendar-x"></i>
                        <p>Belum ada tournament yang tersedia saat ini.<br>Pantau terus untuk info terbaru.</p>
                    </div>
                <?php else:
                    while ($row = $active_result->fetch_assoc()):
                        $pct = $row['max_participants'] > 0 ? ($row['participants'] / $row['max_participants']) * 100 : 0;
                        $fillCls = $pct >= 100 ? 'full' : ($pct >= 75 ? 'near' : '');
                        $isFull = $row['participants'] >= $row['max_participants'];
                        $badgeCls = $row['status'] === 'ongoing' ? 'badge-ongoing' : 'badge-upcoming';
                        $badgeTxt = $row['status'] === 'ongoing' ? 'Berlangsung' : 'Segera';
                        ?>
                        <div class="t-item">
                            <div class="t-item-top">
                                <div class="t-item-name"><?php echo htmlspecialchars($row['name']); ?></div>
                                <span class="badge <?php echo $badgeCls; ?>"><?php echo $badgeTxt; ?></span>
                            </div>

                            <?php if (!empty($row['description'])): ?>
                                <p class="t-item-desc"><?php echo htmlspecialchars($row['description']); ?></p>
                            <?php endif; ?>

                            <div class="t-item-meta">
                                <span class="t-meta-item">
                                    <i data-lucide="calendar"></i>
                                    <strong><?php echo date('d M Y, H:i', strtotime($row['start_date'])); ?></strong>
                                </span>
                                <span class="t-meta-item">
                                    <i data-lucide="users"></i>
                                    <?php echo $row['participants']; ?> / <?php echo $row['max_participants']; ?> peserta
                                </span>
                            </div>

                            <!-- Quota bar -->
                            <div class="quota-wrap">
                                <div class="quota-label">
                                    <span>Kuota tersisa</span>
                                    <strong><?php echo $row['max_participants'] - $row['participants']; ?> slot</strong>
                                </div>
                                <div class="quota-bar">
                                    <div class="quota-fill <?php echo $fillCls; ?>"
                                        style="width: <?php echo min(100, $pct); ?>%"></div>
                                </div>
                            </div>

                            <?php if ($row['status'] === 'upcoming' && !$isFull): ?>
                                <div class="t-join-form">
                                    <form method="POST">
                                        <input type="hidden" name="tournament_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="join_tournament" class="btn btn-primary btn-sm">
                                            <i data-lucide="user-plus"></i>
                                            Daftar Sekarang
                                        </button>
                                    </form>
                                </div>
                            <?php elseif ($isFull): ?>
                                <div class="t-join-form">
                                    <span class="btn btn-secondary btn-sm" style="cursor:default;opacity:.6;">
                                        <i data-lucide="x-circle"></i>
                                        Kuota Penuh
                                    </span>
                                </div>
                            <?php elseif ($row['status'] === 'ongoing'): ?>
                                <div class="t-join-form">
                                    <span class="btn btn-secondary btn-sm" style="cursor:default;opacity:.6;">
                                        <i data-lucide="lock"></i>
                                        Sedang Berlangsung
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; endif; ?>
            </div>

            <!-- RIGHT: Tournament Saya -->
            <div class="section-card">
                <div class="section-card-header">
                    <div class="section-card-title">
                        <i data-lucide="user-check"></i>
                        Tournament Saya
                    </div>
                    <span class="section-card-count"><?php echo $total_joined; ?> diikuti</span>
                </div>

                <?php if ($my_result->num_rows === 0): ?>
                    <div class="t-empty">
                        <i data-lucide="inbox"></i>
                        <p>Kamu belum mengikuti tournament apapun.</p>
                    </div>
                <?php else:
                    while ($row = $my_result->fetch_assoc()):
                        $badgeMap = [
                            'ongoing' => ['cls' => 'badge-ongoing', 'txt' => 'Berlangsung'],
                            'upcoming' => ['cls' => 'badge-upcoming', 'txt' => 'Segera'],
                            'finished' => ['cls' => 'badge-finished', 'txt' => 'Selesai'],
                        ];
                        $b = $badgeMap[$row['status']] ?? ['cls' => 'badge-secondary', 'txt' => ucfirst($row['status'])];
                        ?>
                        <div class="my-t-item">
                            <div class="my-t-icon">
                                <i data-lucide="trophy"></i>
                            </div>
                            <div class="my-t-info">
                                <div class="my-t-name"><?php echo htmlspecialchars($row['name']); ?></div>
                                <div class="my-t-date">
                                    Daftar <?php echo date('d M Y', strtotime($row['registration_date'])); ?>
                                </div>
                            </div>
                            <span class="badge <?php echo $b['cls']; ?>"><?php echo $b['txt']; ?></span>
                        </div>
                    <?php endwhile; endif; ?>
            </div>

        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
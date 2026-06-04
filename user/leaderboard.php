<?php
require_once '../config.php';
session_start();

// Leaderboard data
$query = "SELECT username, total_wins, total_matches,
          CASE
              WHEN total_matches >= 50 THEN 'Pro Player'
              WHEN total_matches >= 20 THEN 'Veteran'
              WHEN total_matches >= 10 THEN 'Regular'
              ELSE 'Rookie'
          END as rank_title
          FROM users
          WHERE role = 'customer'
          ORDER BY total_wins DESC, total_matches DESC";
$result = $conn->query($query);
$players = [];
while ($row = $result->fetch_assoc()) {
    $row['win_rate'] = $row['total_matches'] > 0
        ? round(($row['total_wins'] / $row['total_matches']) * 100)
        : 0;
    $players[] = $row;
}

// Statistik minggu ini
$week_number = date('W');
$w_stmt = $conn->prepare("SELECT COUNT(*) as total_duels FROM duel_matches WHERE week_number = ?");
$w_stmt->bind_param("i", $week_number);
$w_stmt->execute();
$total_duels_week = $w_stmt->get_result()->fetch_assoc()['total_duels'] ?? 0;

// Pemain paling aktif
$active_result = $conn->query("SELECT username, total_matches FROM users WHERE role = 'customer' ORDER BY total_matches DESC LIMIT 5");
$active_players = $active_result->fetch_all(MYSQLI_ASSOC);
$max_matches = !empty($active_players) ? (int) $active_players[0]['total_matches'] : 1;

include '../includes/header.php';
?>

<style>
/* ── LEADERBOARD PAGE ── */
.lb-wrapper {
    padding: 2.5rem 0 5rem;
}

/* ── PAGE HEADER ── */
.lb-page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 2rem;
    padding-bottom: 1.75rem;
    border-bottom: 1px solid var(--border);
}

.lb-page-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text);
    letter-spacing: -.02em;
    display: flex;
    align-items: center;
    gap: .65rem;
    margin-bottom: .25rem;
}

.lb-page-title svg {
    width: 28px;
    height: 28px;
    stroke: var(--primary);
}

.lb-page-desc {
    font-size: .9rem;
    color: var(--text-muted);
}

/* ── STATS ROW ── */
.lb-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

.lb-stat-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1.2rem 1.4rem;
    display: flex;
    align-items: center;
    gap: .9rem;
    transition: box-shadow var(--dur) var(--ease), transform var(--dur) var(--ease);
}

.lb-stat-card:hover {
    box-shadow: var(--shadow);
    transform: translateY(-2px);
}

.lb-stat-icon {
    width: 42px;
    height: 42px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.lb-stat-icon svg { width: 20px; height: 20px; stroke: currentColor; }
.lb-stat-icon.gold   { background: #FEF3C7; color: #92400E; }
.lb-stat-icon.blue   { background: var(--primary-light); color: var(--primary); }
.lb-stat-icon.green  { background: #DCFCE7; color: #166534; }
.lb-stat-icon.purple { background: #F5F3FF; color: #6D28D9; }

.lb-stat-num   { font-size: 1.5rem; font-weight: 800; color: var(--text); line-height: 1; margin-bottom: .1rem; }
.lb-stat-label { font-size: .72rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: .05em; }

/* ── MAIN GRID ── */
.lb-main-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 1.5rem;
    align-items: start;
}

/* ── SECTION CARD ── */
.lb-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.lb-card-header {
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.lb-card-title {
    display: flex;
    align-items: center;
    gap: .55rem;
    font-size: .95rem;
    font-weight: 700;
    color: var(--text);
}

.lb-card-title svg { width: 17px; height: 17px; stroke: var(--primary); }

.lb-card-sub {
    font-size: .75rem;
    color: var(--text-muted);
}

/* ── LEADERBOARD TABLE ── */
.lb-table {
    width: 100%;
    border-collapse: collapse;
}

.lb-table th {
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

.lb-table th:not(:first-child) { text-align: right; }

.lb-table td {
    padding: .9rem 1.25rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    font-size: .875rem;
    color: var(--text);
}

.lb-table td:not(:first-child):not(:nth-child(2)) { text-align: right; }

.lb-table tbody tr:last-child td { border-bottom: none; }

.lb-table tbody tr {
    transition: background var(--dur) var(--ease);
}

.lb-table tbody tr:hover { background: var(--bg); }

/* Top 3 highlight */
.lb-table tbody tr.rank-1 { background: #FFFBEB; }
.lb-table tbody tr.rank-1:hover { background: #FEF3C7; }
.lb-table tbody tr.rank-2 { background: #F8FAFC; }
.lb-table tbody tr.rank-3 { background: #FFF7F5; }

/* Rank cell */
.rank-cell {
    display: flex;
    align-items: center;
    gap: .6rem;
}

.rank-num {
    font-size: .8rem;
    font-weight: 700;
    color: var(--text-muted);
    width: 20px;
    text-align: center;
    flex-shrink: 0;
}

.rank-medal {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.rank-medal svg { width: 14px; height: 14px; stroke: currentColor; }
.rank-medal.gold   { background: #FEF3C7; color: #B45309; }
.rank-medal.silver { background: #F1F5F9; color: #475569; }
.rank-medal.bronze { background: #FFF7ED; color: #9A3412; }

/* Username cell */
.username-cell {
    display: flex;
    align-items: center;
    gap: .65rem;
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
    font-size: .75rem;
    font-weight: 800;
    flex-shrink: 0;
    text-transform: uppercase;
}

.username-name { font-weight: 600; color: var(--text); }

/* Win rate bar */
.wr-cell { display: flex; align-items: center; gap: .6rem; justify-content: flex-end; }
.wr-num  { font-weight: 700; font-size: .875rem; min-width: 36px; text-align: right; }
.wr-bar  { width: 52px; height: 4px; background: var(--card-bg); border-radius: 999px; overflow: hidden; }
.wr-fill { height: 100%; border-radius: 999px; background: var(--primary); }
.wr-fill.high   { background: #22C55E; }
.wr-fill.medium { background: var(--warning); }
.wr-fill.low    { background: var(--danger); }

/* Rank title badges */
.rank-badge {
    display: inline-flex;
    align-items: center;
    padding: .2rem .6rem;
    border-radius: 999px;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .04em;
}

.rank-badge.pro     { background: var(--primary-light); color: var(--primary); border: 1px solid rgba(59,91,219,.2); }
.rank-badge.veteran { background: #F5F3FF; color: #6D28D9; border: 1px solid rgba(109,40,217,.2); }
.rank-badge.regular { background: #DCFCE7; color: #166534; border: 1px solid rgba(46,204,113,.2); }
.rank-badge.rookie  { background: var(--card-bg); color: var(--text-muted); border: 1px solid var(--border); }

/* ── SIDEBAR CARDS ── */
.lb-side-stack { display: flex; flex-direction: column; gap: 1rem; }

/* Weekly stat */
.week-stat-wrap {
    padding: 1.5rem;
}

.week-stat-big {
    font-size: 3rem;
    font-weight: 800;
    color: var(--text);
    letter-spacing: -.04em;
    line-height: 1;
    margin-bottom: .25rem;
}

.week-stat-label {
    font-size: .8rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: .35rem;
}

.week-stat-label svg { width: 13px; height: 13px; stroke: currentColor; opacity: .6; }

/* Active players list */
.active-player-item {
    padding: .9rem 1.25rem;
    display: flex;
    align-items: center;
    gap: .85rem;
    transition: background var(--dur) var(--ease);
}

.active-player-item:hover { background: var(--bg); }
.active-player-item + .active-player-item { border-top: 1px solid var(--border); }

.active-player-rank {
    font-size: .75rem;
    font-weight: 700;
    color: var(--text-muted);
    width: 18px;
    text-align: center;
    flex-shrink: 0;
}

.active-player-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--primary-light);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .7rem;
    font-weight: 800;
    flex-shrink: 0;
    text-transform: uppercase;
}

.active-player-info { flex: 1; min-width: 0; }
.active-player-name { font-size: .85rem; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: .25rem; }

.active-player-bar { height: 3px; background: var(--card-bg); border-radius: 999px; overflow: hidden; }
.active-player-fill { height: 100%; border-radius: 999px; background: var(--primary); }

.active-player-count { font-size: .75rem; font-weight: 700; color: var(--text-muted); white-space: nowrap; flex-shrink: 0; }

/* empty */
.lb-empty { padding: 2.5rem 1.5rem; text-align: center; color: var(--text-muted); }
.lb-empty svg { width: 36px; height: 36px; stroke: var(--border); margin: 0 auto .65rem; display: block; }
.lb-empty p { font-size: .875rem; }

/* ── RESPONSIVE ── */
@media (max-width: 960px) {
    .lb-main-grid { grid-template-columns: 1fr; }
    .lb-stats     { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 560px) {
    .lb-stats { grid-template-columns: 1fr 1fr; }
    .lb-table th, .lb-table td { padding: .75rem .9rem; }
    .wr-bar { display: none; }
}
</style>

<div class="lb-wrapper">
<div class="container">

    <!-- Page Header -->
    <div class="lb-page-header">
        <div>
            <h1 class="lb-page-title">
                <i data-lucide="bar-chart-2"></i>
                Leaderboard
            </h1>
            <p class="lb-page-desc">Peringkat pemain berdasarkan total kemenangan duel PvP.</p>
        </div>
    </div>

    <!-- Stats -->
    <?php
    $total_players = count($players);
    $total_wins_all = array_sum(array_column($players, 'total_wins'));
    $avg_wr = $total_players > 0
        ? round(array_sum(array_column($players, 'win_rate')) / $total_players)
        : 0;
    ?>
    <div class="lb-stats">
        <div class="lb-stat-card">
            <div class="lb-stat-icon gold"><i data-lucide="users"></i></div>
            <div>
                <div class="lb-stat-num"><?php echo $total_players; ?></div>
                <div class="lb-stat-label">Total Pemain</div>
            </div>
        </div>
        <div class="lb-stat-card">
            <div class="lb-stat-icon blue"><i data-lucide="swords"></i></div>
            <div>
                <div class="lb-stat-num"><?php echo $total_duels_week; ?></div>
                <div class="lb-stat-label">Duel Minggu Ini</div>
            </div>
        </div>
        <div class="lb-stat-card">
            <div class="lb-stat-icon green"><i data-lucide="trophy"></i></div>
            <div>
                <div class="lb-stat-num"><?php echo $total_wins_all; ?></div>
                <div class="lb-stat-label">Total Kemenangan</div>
            </div>
        </div>
        <div class="lb-stat-card">
            <div class="lb-stat-icon purple"><i data-lucide="percent"></i></div>
            <div>
                <div class="lb-stat-num"><?php echo $avg_wr; ?>%</div>
                <div class="lb-stat-label">Rata-rata Win Rate</div>
            </div>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="lb-main-grid">

        <!-- LEFT: Ranking table -->
        <div class="lb-card">
            <div class="lb-card-header">
                <div class="lb-card-title">
                    <i data-lucide="trophy"></i>
                    Peringkat Pemain
                </div>
                <span class="lb-card-sub"><?php echo $total_players; ?> pemain terdaftar</span>
            </div>

            <?php if (empty($players)): ?>
                <div class="lb-empty">
                    <i data-lucide="inbox"></i>
                    <p>Belum ada data pemain.</p>
                </div>
            <?php else: ?>
                <table class="lb-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Pemain</th>
                            <th>Menang</th>
                            <th>Main</th>
                            <th>Win Rate</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($players as $i => $p):
                        $rank = $i + 1;
                        $rowCls = $rank <= 3 ? "rank-$rank" : '';
                        $wr = (int) $p['win_rate'];
                        $wrCls = $wr >= 60 ? 'high' : ($wr >= 40 ? 'medium' : 'low');
                        $rtKey = strtolower(str_replace(' ', '', $p['rank_title']));
                        $rtCls = ['proplayer' => 'pro', 'veteran' => 'veteran', 'regular' => 'regular', 'rookie' => 'rookie'][$rtKey] ?? 'rookie';
                        $initial = strtoupper(substr($p['username'], 0, 2));
                        ?>
                        <tr class="<?php echo $rowCls; ?>">
                            <td>
                                <div class="rank-cell">
                                    <?php if ($rank === 1): ?>
                                            <div class="rank-medal gold"><i data-lucide="crown"></i></div>
                                    <?php elseif ($rank === 2): ?>
                                            <div class="rank-medal silver"><i data-lucide="medal"></i></div>
                                    <?php elseif ($rank === 3): ?>
                                            <div class="rank-medal bronze"><i data-lucide="medal"></i></div>
                                    <?php else: ?>
                                            <span class="rank-num"><?php echo $rank; ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="username-cell">
                                    <div class="user-avatar"><?php echo $initial; ?></div>
                                    <span class="username-name"><?php echo htmlspecialchars($p['username']); ?></span>
                                </div>
                            </td>
                            <td><strong><?php echo $p['total_wins']; ?></strong></td>
                            <td><?php echo $p['total_matches']; ?></td>
                            <td>
                                <div class="wr-cell">
                                    <span class="wr-num"><?php echo $wr; ?>%</span>
                                    <div class="wr-bar">
                                        <div class="wr-fill <?php echo $wrCls; ?>" style="width:<?php echo $wr; ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="rank-badge <?php echo $rtCls; ?>"><?php echo htmlspecialchars($p['rank_title']); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- RIGHT: Sidebar -->
        <div class="lb-side-stack">

            <!-- Duel Minggu Ini -->
            <div class="lb-card">
                <div class="lb-card-header">
                    <div class="lb-card-title">
                        <i data-lucide="calendar"></i>
                        Minggu Ini
                    </div>
                    <span class="lb-card-sub">Week <?php echo $week_number; ?></span>
                </div>
                <div class="week-stat-wrap">
                    <div class="week-stat-big"><?php echo $total_duels_week; ?></div>
                    <div class="week-stat-label">
                        <i data-lucide="swords"></i>
                        Total duel berlangsung
                    </div>
                </div>
            </div>

            <!-- Pemain Paling Aktif -->
            <div class="lb-card">
                <div class="lb-card-header">
                    <div class="lb-card-title">
                        <i data-lucide="zap"></i>
                        Paling Aktif
                    </div>
                    <span class="lb-card-sub">Top 5</span>
                </div>

                <?php if (empty($active_players)): ?>
                    <div class="lb-empty">
                        <i data-lucide="inbox"></i>
                        <p>Belum ada data.</p>
                    </div>
                <?php else:
                    foreach ($active_players as $ai => $ap):
                        $pct = $max_matches > 0 ? round(($ap['total_matches'] / $max_matches) * 100) : 0;
                        $initial = strtoupper(substr($ap['username'], 0, 2));
                        ?>
                        <div class="active-player-item">
                            <span class="active-player-rank"><?php echo $ai + 1; ?></span>
                            <div class="active-player-avatar"><?php echo $initial; ?></div>
                            <div class="active-player-info">
                                <div class="active-player-name"><?php echo htmlspecialchars($ap['username']); ?></div>
                                <div class="active-player-bar">
                                    <div class="active-player-fill" style="width:<?php echo $pct; ?>%"></div>
                                </div>
                            </div>
                            <span class="active-player-count"><?php echo $ap['total_matches']; ?> main</span>
                        </div>
                    <?php endforeach; endif; ?>
            </div>

        </div>
    </div>

</div>
</div>

<?php include '../includes/footer.php'; ?>
<?php
require_once '../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$week_number = date('W');

$success = $_SESSION['duel_success'] ?? '';
$error = $_SESSION['duel_error'] ?? '';
unset($_SESSION['duel_success'], $_SESSION['duel_error']);

// Proses challenge duel
if (isset($_POST['challenge'])) {
    $opponent_id = (int) $_POST['opponent_id'];

    $check = $conn->prepare("SELECT match_count FROM duel_history
                             WHERE ((user1_id=? AND user2_id=?) OR (user1_id=? AND user2_id=?))
                             AND week_number=?");
    $check->bind_param("iiiii", $user_id, $opponent_id, $opponent_id, $user_id, $week_number);
    $check->execute();
    $row_check = $check->get_result()->fetch_assoc();

    if ($row_check && $row_check['match_count'] >= 3) {
        $error = "Kalian sudah duel 3x minggu ini! Tidak bisa duel lagi dengan pemain yang sama.";
    } else {
        $ins = $conn->prepare("INSERT INTO duel_matches (challenger_id, opponent_id, match_date, week_number)
                               VALUES (?, ?, CURDATE(), ?)");
        $ins->bind_param("iii", $user_id, $opponent_id, $week_number);
        if ($ins->execute()) {
            $upsert = $conn->prepare("INSERT INTO duel_history (user1_id, user2_id, week_number, match_count)
                                      VALUES (?, ?, ?, 1)
                                      ON DUPLICATE KEY UPDATE match_count = match_count + 1");
            $upsert->bind_param("iii", $user_id, $opponent_id, $week_number);
            $upsert->execute();
            $success = "Challenge berhasil dikirim!";
        } else {
            $error = "Gagal mengirim challenge. Coba lagi.";
        }
    }
}

// Proses hasil duel
if (isset($_POST['submit_result'])) {
    $match_id = (int) $_POST['match_id'];
    $winner_id = (int) $_POST['winner_id'];
    $lose_id = ($winner_id == $user_id) ? (int) $_POST['opponent_id'] : $user_id;

    $upd = $conn->prepare("UPDATE duel_matches SET status='completed', winner_id=? WHERE id=?");
    $upd->bind_param("ii", $winner_id, $match_id);
    if ($upd->execute()) {
        $conn->prepare("UPDATE users SET total_wins=total_wins+1, total_matches=total_matches+1 WHERE id=?")
            ->bind_param("i", $winner_id) && $conn->prepare("...")->execute();

        $w = $conn->prepare("UPDATE users SET total_wins=total_wins+1, total_matches=total_matches+1 WHERE id=?");
        $w->bind_param("i", $winner_id);
        $w->execute();

        $l = $conn->prepare("UPDATE users SET total_matches=total_matches+1 WHERE id=?");
        $l->bind_param("i", $lose_id);
        $l->execute();

        $success = "Hasil duel berhasil disimpan!";
    }
}

// Ambil stat user
$stat = $conn->query("SELECT total_matches, total_wins FROM users WHERE id=$user_id")->fetch_assoc();
$total_matches = (int) ($stat['total_matches'] ?? 0);
$total_wins = (int) ($stat['total_wins'] ?? 0);
$win_rate = $total_matches > 0 ? round(($total_wins / $total_matches) * 100) : 0;

include '../includes/header.php';
?>

<style>
/* ── DUEL PAGE STYLES ── */

.page-header {
    background: var(--white);
    border-bottom: 1px solid var(--border);
    padding: 1.75rem 0;
}

.page-header-inner {
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-icon {
    width: 44px;
    height: 44px;
    border-radius: var(--radius-sm);
    background: #FEE2E2;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #B91C1C;
    flex-shrink: 0;
}

.header-icon svg { width: 22px; height: 22px; stroke: currentColor; }

.page-header h1 {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--text);
    letter-spacing: -.02em;
    line-height: 1.2;
}

.page-header .subtitle {
    font-size: .8rem;
    color: var(--text-muted);
    font-weight: 500;
    margin-top: 2px;
}

.week-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 999px;
    font-size: .75rem;
    font-weight: 700;
    background: var(--primary-light);
    color: var(--primary);
    border: 1px solid rgba(59,91,219,.2);
}

.week-pill svg { width: 14px; height: 14px; stroke: currentColor; }

/* ── LAYOUT ── */
.duel-wrapper {
    max-width: 1100px;
    margin: 0 auto;
    padding: 28px 24px;
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 24px;
    align-items: start;
}

/* ── CARDS ── */
.dk-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.dk-card-header {
    padding: 18px 22px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
}

.dk-icon {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.dk-icon svg { width: 17px; height: 17px; stroke: currentColor; }
.dk-icon.red   { background: #FEE2E2; color: #B91C1C; }
.dk-icon.blue  { background: var(--primary-light); color: var(--primary); }
.dk-icon.green { background: #DCFCE7; color: #15803D; }

.dk-card-header h2 { font-size: .95rem; font-weight: 700; color: var(--text); }
.dk-card-header p  { font-size: .75rem; color: var(--text-muted); margin-top: 1px; }

.dk-card-body { padding: 22px; }

/* ── FORM ── */
.form-group { margin-bottom: 16px; }

.form-group label {
    display: block;
    font-size: .8rem;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 7px;
}

.form-group label .req { color: var(--primary); margin-left: 2px; }

.input-wrap { position: relative; }

.input-icon {
    position: absolute;
    left: 11px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    pointer-events: none;
    display: flex;
    align-items: center;
}

.input-icon svg { width: 16px; height: 16px; stroke: currentColor; }

.sel-arrow {
    position: absolute;
    right: 11px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: var(--text-muted);
    display: flex;
    align-items: center;
}

.sel-arrow svg { width: 14px; height: 14px; stroke: currentColor; }

.form-control {
    width: 100%;
    padding: 10px 12px 10px 36px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    font-family: inherit;
    font-size: .875rem;
    color: var(--text);
    background: var(--white);
    appearance: none;
    outline: none;
    transition: border-color .18s, box-shadow .18s;
}

.form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59,91,219,.1);
}

.limit-notice {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    background: #FEF9C3;
    border: 1px solid #FDE68A;
    border-radius: var(--radius-sm);
    font-size: .8rem;
    color: #A16207;
    font-weight: 500;
    margin: 16px 0 20px;
}

.limit-notice svg { width: 16px; height: 16px; stroke: currentColor; flex-shrink: 0; }

.btn-challenge {
    width: 100%;
    padding: 13px;
    background: #DC2626;
    color: #fff;
    border: none;
    border-radius: var(--radius-sm);
    font-family: inherit;
    font-size: .9rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: background .18s, transform .15s, box-shadow .18s;
}

.btn-challenge svg { width: 18px; height: 18px; stroke: currentColor; }

.btn-challenge:hover {
    background: #B91C1C;
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(185,28,28,.3);
}

.btn-challenge:active { transform: translateY(0); }

/* ── RIGHT COL ── */
.right-col { display: flex; flex-direction: column; gap: 20px; }

/* ── STAT CARDS ── */
.stat-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.stat-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px;
}

.stat-icon {
    width: 34px;
    height: 34px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 8px;
}

.stat-icon svg { width: 17px; height: 17px; stroke: currentColor; }
.stat-icon.red   { background: #FEE2E2; color: #B91C1C; }
.stat-icon.green { background: #DCFCE7; color: #15803D; }
.stat-icon.blue  { background: var(--primary-light); color: var(--primary); }

.stat-val {
    font-size: 1.3rem;
    font-weight: 800;
    color: var(--text);
    letter-spacing: -.02em;
    line-height: 1;
}

.stat-lbl {
    font-size: .72rem;
    color: var(--text-muted);
    font-weight: 500;
    margin-top: 4px;
}

/* ── TABLE ── */
.section-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex: 1;
}

.view-all {
    font-size: .78rem;
    font-weight: 600;
    color: var(--primary);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}

.view-all svg { width: 13px; height: 13px; stroke: currentColor; }
.view-all:hover { text-decoration: underline; }

.table-wrap { overflow-x: auto; }

table.dk-table { width: 100%; border-collapse: collapse; }
.dk-table thead tr { border-bottom: 1.5px solid var(--border); }
.dk-table thead th {
    padding: 10px 16px;
    font-size: .7rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .06em;
    text-align: left;
    white-space: nowrap;
}

.dk-table tbody tr { border-bottom: 1px solid var(--border); transition: background .12s; }
.dk-table tbody tr:last-child { border-bottom: none; }
.dk-table tbody tr:hover { background: var(--bg); }
.dk-table tbody td { padding: 13px 16px; font-size: .85rem; vertical-align: middle; }

.td-date-main { font-weight: 600; font-size: .85rem; }
.td-date-sub  { font-size: .73rem; color: var(--text-muted); }

/* Player match cell */
.match-cell {
    display: flex;
    align-items: center;
    gap: 7px;
}

.avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .68rem;
    font-weight: 800;
    flex-shrink: 0;
    border: 1.5px solid transparent;
}

.avatar.me   { background: var(--primary-light); color: var(--primary); border-color: rgba(59,91,219,.2); }
.avatar.opp  { background: #FEE2E2; color: #B91C1C; border-color: rgba(185,28,28,.18); }
.avatar.opp2 { background: #DCFCE7; color: #15803D; border-color: rgba(21,128,61,.18); }
.avatar.opp3 { background: #FEF9C3; color: #A16207; border-color: rgba(161,98,7,.18); }
.avatar.opp4 { background: #DBEAFE; color: #1D4ED8; border-color: rgba(29,78,216,.18); }

.vs-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 999px;
    background: #F3F4F6;
    font-size: .63rem;
    font-weight: 800;
    color: #6B7280;
    flex-shrink: 0;
}

.player-name { font-size: .82rem; white-space: nowrap; }
.player-name.me { font-weight: 700; }

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: 999px;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .03em;
    text-transform: uppercase;
    white-space: nowrap;
}

.badge svg { width: 11px; height: 11px; stroke: currentColor; }

.badge-pending   { background: #FEF9C3; color: #A16207; }
.badge-accepted  { background: #DBEAFE; color: #1D4ED8; }
.badge-completed { background: #DCFCE7; color: #15803D; }
.badge-rejected  { background: #FEE2E2; color: #B91C1C; }

/* Result */
.result-win  { display: flex; align-items: center; gap: 4px; font-size: .82rem; font-weight: 700; color: #15803D; }
.result-lose { display: flex; align-items: center; gap: 4px; font-size: .82rem; font-weight: 700; color: #B91C1C; }
.result-win svg, .result-lose svg { width: 13px; height: 13px; stroke: currentColor; }

/* Action buttons */
.action-btns { display: flex; gap: 6px; flex-wrap: wrap; }

.btn-sm {
    padding: 5px 11px;
    border-radius: var(--radius-sm);
    font-size: .75rem;
    font-weight: 700;
    cursor: pointer;
    border: none;
    font-family: inherit;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: opacity .15s, transform .12s;
    text-decoration: none;
}

.btn-sm svg { width: 13px; height: 13px; stroke: currentColor; }
.btn-sm:hover { opacity: .85; transform: translateY(-1px); }

.btn-accept { background: #DCFCE7; color: #15803D; }
.btn-reject { background: #FEE2E2; color: #B91C1C; }
.btn-input  { background: var(--primary-light); color: var(--primary); }

/* Alerts */
.dk-alert {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 13px 22px;
    font-size: .85rem;
    font-weight: 500;
    margin-top: 14px;
}

.dk-alert svg { width: 17px; height: 17px; stroke: currentColor; flex-shrink: 0; margin-top: 1px; }

.dk-alert-success {
    background: #DCFCE7;
    color: #15803D;
    border-top: 1px solid #BBF7D0;
    border-bottom: 1px solid #BBF7D0;
}

.dk-alert-danger {
    background: #FEE2E2;
    color: #B91C1C;
    border-top: 1px solid #FECACA;
    border-bottom: 1px solid #FECACA;
}

/* Empty */
.empty-state { padding: 36px 24px; text-align: center; color: var(--text-muted); }
.empty-state svg { width: 34px; height: 34px; stroke: currentColor; opacity: .3; margin: 0 auto 10px; display: block; }
.empty-state p { font-size: .85rem; }

/* Responsive */
@media (max-width: 860px) {
    .duel-wrapper { grid-template-columns: 1fr; }
    .stat-cards { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 520px) {
    .stat-cards { grid-template-columns: 1fr; }
    .duel-wrapper { padding: 20px 16px; }
}
</style>

<?php
// SVG icon helpers
function ico($path, $extra = '')
{
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ' . $extra . '>' . $path . '</svg>';
}

$ico_sword = ico('<path d="M14.5 17.5 3 6V3h3l11.5 11.5M13 19l2-2M4 13l2-2M10 7l2-2M15 6l3-3 3 3-7 7M4 20l2-2"/>');
$ico_calendar = ico('<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>');
$ico_user = ico('<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>');
$ico_chevdown = ico('<path d="m6 9 6 6 6-6"/>', 'style="width:14px;height:14px"');
$ico_info = ico('<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>');
$ico_pay = ico('<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>');
$ico_trophy = ico('<path d="M6 9H4a2 2 0 0 0-2 2v1a6 6 0 0 0 6 6h8a6 6 0 0 0 6-6v-1a2 2 0 0 0-2-2h-2M9 3h6v7a3 3 0 0 1-6 0V3zM12 19v3M8 22h8"/>');
$ico_pct = ico('<line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>');
$ico_history = ico('<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>');
$ico_arrow = ico('<path d="M5 12h14M12 5l7 7-7 7"/>', 'style="width:13px;height:13px"');
$ico_check = ico('<path d="m9 12 2 2 4-4"/>');
$ico_x = ico('<path d="m15 9-6 6M9 9l6 6"/>');
$ico_edit = ico('<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>');
$ico_ok_circ = ico('<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>');
$ico_sad = ico('<circle cx="12" cy="12" r="10"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/>');
$ico_clock = ico('<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>');
$ico_alert = ico('<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>');
?>

<!-- ── PAGE HEADER ── -->
<div class="page-header">
    <div class="page-header-inner">
        <div class="header-left">
            <div class="header-icon"><?= $ico_sword ?></div>
            <div>
                <h1>Arena Duel</h1>
                <p class="subtitle">Tantang gamer lain dalam duel 1v1 — maks. 3x per pasangan per minggu</p>
            </div>
        </div>
        <div class="week-pill">
            <?= $ico_calendar ?>
            Minggu ke-<?= (int) $week_number ?>
        </div>
    </div>
</div>

<!-- ── MAIN WRAP ── -->
<div class="duel-wrapper">

    <!-- LEFT: FORM -->
    <div>
        <div class="dk-card">
            <div class="dk-card-header">
                <div class="dk-icon red"><?= $ico_sword ?></div>
                <div>
                    <h2>Kirim Challenge</h2>
                    <p>Pilih lawan dan mulai duel</p>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="dk-alert dk-alert-success">
                    <?= $ico_ok_circ ?>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="dk-alert dk-alert-danger">
                    <?= $ico_alert ?>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="dk-card-body" style="padding-top:<?= ($success || $error) ? '14px' : '22px' ?>">
                <form method="POST">
                    <div class="form-group">
                        <label>Pilih Lawan <span class="req">*</span></label>
                        <div class="input-wrap">
                            <span class="input-icon"><?= $ico_user ?></span>
                            <select name="opponent_id" class="form-control" required>
                                <option value="">— Pilih Lawan —</option>
                                <?php
                                $opp_q = $conn->prepare("SELECT id, username FROM users WHERE id != ? AND role = 'customer' ORDER BY username ASC");
                                $opp_q->bind_param("i", $user_id);
                                $opp_q->execute();
                                $opp_r = $opp_q->get_result();
                                while ($opp = $opp_r->fetch_assoc()):
                                    ?>
                                    <option value="<?= $opp['id'] ?>"><?= htmlspecialchars($opp['username']) ?></option>
                                <?php endwhile; ?>
                            </select>
                            <span class="sel-arrow"><?= $ico_chevdown ?></span>
                        </div>
                    </div>

                    <div class="limit-notice">
                        <?= $ico_info ?>
                        <span>Duel dengan pemain yang sama dibatasi <strong>3x per minggu</strong></span>
                    </div>

                    <button type="submit" name="challenge" class="btn-challenge">
                        <?= $ico_sword ?>
                        Kirim Challenge
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- RIGHT COL -->
    <div class="right-col">

        <!-- STAT CARDS -->
        <div class="stat-cards">
            <div class="stat-card">
                <div class="stat-icon red"><?= $ico_sword ?></div>
                <div class="stat-val"><?= $total_matches ?></div>
                <div class="stat-lbl">Total duel</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><?= $ico_trophy ?></div>
                <div class="stat-val"><?= $total_wins ?></div>
                <div class="stat-lbl">Kemenangan</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><?= $ico_pct ?></div>
                <div class="stat-val"><?= $win_rate ?>%</div>
                <div class="stat-lbl">Win rate</div>
            </div>
        </div>

        <!-- HISTORY TABLE -->
        <div class="dk-card">
            <div class="dk-card-header">
                <div class="dk-icon blue"><?= $ico_history ?></div>
                <div class="section-row">
                    <div>
                        <h2>Riwayat Duel</h2>
                        <p>Semua pertandingan kamu</p>
                    </div>
                    <a href="duel_history.php" class="view-all">
                        Lihat semua <?= $ico_arrow ?>
                    </a>
                </div>
            </div>

            <div class="table-wrap">
                <?php
                $hist_q = $conn->prepare(
                    "SELECT dm.*,
                            c.username AS challenger_name,
                            o.username AS opponent_name
                     FROM duel_matches dm
                     JOIN users c ON dm.challenger_id = c.id
                     JOIN users o ON dm.opponent_id   = o.id
                     WHERE dm.challenger_id = ? OR dm.opponent_id = ?
                     ORDER BY dm.created_at DESC
                     LIMIT 10"
                );
                $hist_q->bind_param("ii", $user_id, $user_id);
                $hist_q->execute();
                $hist_r = $hist_q->get_result();
                $has_rows = $hist_r->num_rows > 0;

                $days_id = [
                    'Sunday' => 'Minggu',
                    'Monday' => 'Senin',
                    'Tuesday' => 'Selasa',
                    'Wednesday' => 'Rabu',
                    'Thursday' => 'Kamis',
                    'Friday' => 'Jumat',
                    'Saturday' => 'Sabtu'
                ];
                ?>

                <?php if ($has_rows): ?>
                    <table class="dk-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Pertandingan</th>
                                <th>Status</th>
                                <th>Hasil</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = $hist_r->fetch_assoc()):
                            $is_challenger = ($row['challenger_id'] == $user_id);
                            $opp_name = $is_challenger ? $row['opponent_name'] : $row['challenger_name'];
                            $opp_id_row = $is_challenger ? $row['opponent_id'] : $row['challenger_id'];
                            $opp_initials = mb_strtoupper(mb_substr($opp_name, 0, 2));
                            $day_en = date('l', strtotime($row['match_date']));
                            $day_id = $days_id[$day_en] ?? $day_en;
                            $opp_classes = ['opp', 'opp2', 'opp3', 'opp4'];
                            $opp_class = $opp_classes[$opp_id_row % 4];

                            // Status badge
                            switch ($row['status']) {
                                case 'pending':
                                    $badge = '<span class="badge badge-pending">' . $ico_clock . 'Pending</span>';
                                    break;
                                case 'accepted':
                                    $badge = '<span class="badge badge-accepted">' . $ico_check . 'Diterima</span>';
                                    break;
                                case 'completed':
                                    $badge = '<span class="badge badge-completed">' . $ico_ok_circ . 'Selesai</span>';
                                    break;
                                case 'rejected':
                                    $badge = '<span class="badge badge-rejected">' . $ico_x . 'Ditolak</span>';
                                    break;
                                default:
                                    $badge = '<span class="badge badge-pending">' . ucfirst($row['status']) . '</span>';
                            }

                            // Hasil
                            if ($row['winner_id']) {
                                if ($row['winner_id'] == $user_id)
                                    $hasil = '<span class="result-win">' . $ico_trophy . 'Menang</span>';
                                else
                                    $hasil = '<span class="result-lose">' . $ico_sad . 'Kalah</span>';
                            } else {
                                $hasil = '<span style="color:var(--text-muted)">—</span>';
                            }

                            // Aksi
                            $aksi = '—';
                            if ($row['status'] === 'pending' && $row['opponent_id'] == $user_id) {
                                $aksi = '<div class="action-btns">
                                <a href="process_duel.php?action=accept&id=' . $row['id'] . '" class="btn-sm btn-accept"
                                   onclick="return confirm(\'Terima duel ini?\')">' . $ico_check . 'Terima</a>
                                <a href="process_duel.php?action=reject&id=' . $row['id'] . '" class="btn-sm btn-reject"
                                   onclick="return confirm(\'Tolak duel ini?\')">' . $ico_x . 'Tolak</a>
                            </div>';
                            } elseif ($row['status'] === 'accepted' && $row['challenger_id'] == $user_id) {
                                $aksi = '<div class="action-btns">
                                <button class="btn-sm btn-input"
                                    onclick="inputResult(' . $row['id'] . ',' . $opp_id_row . ')">'
                                    . $ico_edit . 'Input Hasil</button>
                            </div>';
                            }
                            ?>
                                <tr>
                                    <td>
                                        <div class="td-date-main"><?= date('d M Y', strtotime($row['match_date'])) ?></div>
                                        <div class="td-date-sub"><?= $day_id ?></div>
                                    </td>
                                    <td>
                                        <div class="match-cell">
                                            <?php if ($is_challenger): ?>
                                                    <span class="avatar me">Sy</span>
                                                    <span class="player-name me">Kamu</span>
                                                    <span class="vs-badge">VS</span>
                                                    <span class="avatar <?= $opp_class ?>"><?= $opp_initials ?></span>
                                                    <span class="player-name"><?= htmlspecialchars($opp_name) ?></span>
                                            <?php else: ?>
                                                    <span class="avatar <?= $opp_class ?>"><?= $opp_initials ?></span>
                                                    <span class="player-name"><?= htmlspecialchars($opp_name) ?></span>
                                                    <span class="vs-badge">VS</span>
                                                    <span class="avatar me">Sy</span>
                                                    <span class="player-name me">Kamu</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= $badge ?></td>
                                    <td><?= $hasil ?></td>
                                    <td><?= $aksi ?></td>
                                </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <?= $ico_sword ?>
                        <p>Belum ada riwayat duel. Kirim challenge pertamamu!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
function inputResult(matchId, opponentId) {
    if (!confirm('Konfirmasi hasil duel.\n\nTekan OK jika kamu menang, Cancel jika lawan yang menang.')) {
        var winnerId = opponentId;
        if (!confirm('Konfirmasi: lawan menang?')) return;
    } else {
        var winnerId = <?php echo (int) $user_id; ?>;
        if (!confirm('Konfirmasi: kamu menang?')) return;
    }
    window.location.href = 'process_duel.php?action=result&id=' + matchId + '&winner=' + winnerId;
}
</script>

<?php include '../includes/footer.php'; ?>
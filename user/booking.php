<?php
require_once '../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

function generateOrderId($user_id)
{
    return "RENTAL-" . $user_id . "-" . time() . "-" . rand(1000, 9999);
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_now'])) {
    $ps_id = $_POST['ps_id'];
    $booking_date = $_POST['booking_date'];
    $start_time = $_POST['start_time'];
    $duration = $_POST['duration'];
    $end_time = date('H:i:s', strtotime($start_time . ' + ' . $duration . ' hours'));

    $ps_query = "SELECT type FROM playstation WHERE id = ?";
    $stmt = $conn->prepare($ps_query);
    $stmt->bind_param("i", $ps_id);
    $stmt->execute();
    $ps = $stmt->get_result()->fetch_assoc();

    if (!$ps) {
        $error = "Unit PlayStation tidak ditemukan.";
    } else {
        $price_per_hour = ($ps['type'] == 'PS4') ? 20000 : 30000;
        $total_price = $price_per_hour * $duration;
        $orderId = generateOrderId($user_id);

        $sql_sp = "CALL sp_create_booking(?, ?, ?, ?, ?, ?, ?, @booking_id, @result_code, @result_msg)";
        $stmt_sp = $conn->prepare($sql_sp);
        $stmt_sp->bind_param('iisssds', $user_id, $ps_id, $booking_date, $start_time, $end_time, $total_price, $orderId);

        if ($stmt_sp->execute()) {
            $out = $conn->query("SELECT @booking_id AS bid, @result_code AS code, @result_msg AS msg")->fetch_assoc();
            $resultCode = (int) $out['code'];
            $newId = (int) $out['bid'];

            if ($resultCode === 0 || $resultCode === 2) {
                header('Location: ../payment/process.php?booking_id=' . $newId);
                exit;
            } else {
                $error = $out['msg'];
            }
        } else {
            $error = "Terjadi kesalahan pada sistem database.";
        }
    }
}

include '../includes/header.php';
?>

<style>
/* ── BOOKING PAGE STYLES ── */

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

.page-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-icon {
    width: 44px;
    height: 44px;
    border-radius: var(--radius-sm);
    background: var(--primary-light);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
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

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 999px;
    font-size: .75rem;
    font-weight: 700;
    background: #DCFCE7;
    color: #15803D;
    border: 1px solid #BBF7D0;
    letter-spacing: .03em;
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #16A34A;
    animation: blink 2s infinite;
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50%       { opacity: .3; }
}

/* ── MAIN LAYOUT ── */
.booking-wrapper {
    max-width: 1100px;
    margin: 0 auto;
    padding: 28px 24px;
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 24px;
    align-items: start;
}

/* ── CARDS ── */
.bk-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.bk-card-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
}

.bk-card-header-icon {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-sm);
    background: var(--primary-light);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    flex-shrink: 0;
}

.bk-card-header-icon svg { width: 17px; height: 17px; stroke: currentColor; }

.bk-card-header-icon.green { background: #DCFCE7; color: #15803D; }

.bk-card-header h2 {
    font-size: .95rem;
    font-weight: 700;
    color: var(--text);
}

.bk-card-header p {
    font-size: .75rem;
    color: var(--text-muted);
    margin-top: 1px;
}

.bk-card-body { padding: 24px; }

/* ── FORM ── */
.form-group { margin-bottom: 18px; }

.form-group label {
    display: block;
    font-size: .8rem;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 7px;
    letter-spacing: .01em;
}

.form-group label .req { color: var(--primary); margin-left: 2px; }

.input-wrap { position: relative; }

.input-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    pointer-events: none;
    display: flex;
    align-items: center;
}

.input-icon svg { width: 16px; height: 16px; stroke: currentColor; }

.select-arrow {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: var(--text-muted);
}

.select-arrow svg { width: 14px; height: 14px; stroke: currentColor; }

.form-control {
    width: 100%;
    padding: 10px 12px 10px 38px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    font-family: inherit;
    font-size: .875rem;
    color: var(--text);
    background: var(--white);
    transition: border-color .18s, box-shadow .18s;
    appearance: none;
    outline: none;
}

.form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59,91,219,.1);
}

select.form-control { cursor: pointer; }

/* ── PRICE PREVIEW ── */
.price-preview {
    background: var(--primary-light);
    border: 1.5px solid rgba(59,91,219,.18);
    border-radius: var(--radius);
    padding: 16px;
    margin: 20px 0;
}

.price-row {
    display: flex;
    justify-content: space-between;
    font-size: .8rem;
    color: var(--text-muted);
    padding: 3px 0;
}

.price-row.total {
    border-top: 1px solid rgba(59,91,219,.2);
    margin-top: 8px;
    padding-top: 10px;
    font-weight: 700;
    font-size: .95rem;
    color: var(--primary);
}

.price-row.total .val {
    font-size: 1.1rem;
    font-weight: 800;
}

/* ── BUTTON ── */
.btn-book {
    width: 100%;
    padding: 13px;
    background: var(--primary);
    color: var(--white);
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
    letter-spacing: .01em;
}

.btn-book svg { width: 18px; height: 18px; stroke: currentColor; }

.btn-book:hover {
    background: var(--primary-dark, #2f4bbf);
    box-shadow: 0 4px 14px rgba(59,91,219,.35);
    transform: translateY(-1px);
}

.btn-book:active { transform: translateY(0); }

/* ── RIGHT COL ── */
.right-col { display: flex; flex-direction: column; gap: 20px; }

/* ── INFO CARDS ── */
.info-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.info-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.info-card-icon {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 4px;
    flex-shrink: 0;
}

.info-card-icon svg { width: 18px; height: 18px; stroke: currentColor; }
.info-card-icon.blue   { background: var(--primary-light); color: var(--primary); }
.info-card-icon.green  { background: #DCFCE7; color: #15803D; }
.info-card-icon.amber  { background: #FEF3C7; color: #D97706; }

.info-card-val {
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--text);
    line-height: 1;
    letter-spacing: -.02em;
}

.info-card-lbl {
    font-size: .73rem;
    color: var(--text-muted);
    font-weight: 500;
}

/* ── HISTORY TABLE ── */
.section-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex: 1;
}

.view-all-link {
    font-size: .78rem;
    font-weight: 600;
    color: var(--primary);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}

.view-all-link svg { width: 13px; height: 13px; stroke: currentColor; }
.view-all-link:hover { text-decoration: underline; }

.table-wrap { overflow-x: auto; }

table.bk-table { width: 100%; border-collapse: collapse; }

.bk-table thead tr { border-bottom: 1.5px solid var(--border); }

.bk-table thead th {
    padding: 10px 16px;
    font-size: .72rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .06em;
    text-align: left;
    white-space: nowrap;
}

.bk-table tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background .12s;
}

.bk-table tbody tr:last-child { border-bottom: none; }
.bk-table tbody tr:hover { background: var(--bg); }

.bk-table tbody td {
    padding: 13px 16px;
    font-size: .85rem;
    vertical-align: middle;
}

.td-date-main { font-weight: 600; font-size: .85rem; }
.td-date-sub  { font-size: .75rem; color: var(--text-muted); }
.td-time-main { font-weight: 600; }
.td-time-sub  { font-size: .75rem; color: var(--text-muted); }
.td-price     { font-weight: 700; }

.ps-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: var(--radius-sm);
    font-size: .72rem;
    font-weight: 700;
    background: var(--primary-light);
    color: var(--primary);
    border: 1px solid rgba(59,91,219,.15);
}

.ps-tag.ps4 {
    background: #F0FDF4;
    color: #15803D;
    border-color: rgba(22,163,74,.15);
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: 999px;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
}

.badge-paid      { background: #DCFCE7; color: #15803D; }
.badge-pending   { background: #FEF9C3; color: #A16207; }
.badge-cancelled { background: #FEE2E2; color: #B91C1C; }
.badge-expired   { background: #F3F4F6; color: #6B7280; }

.badge svg { width: 11px; height: 11px; stroke: currentColor; }

/* ── EMPTY STATE ── */
.empty-state {
    padding: 40px 24px;
    text-align: center;
    color: var(--text-muted);
}

.empty-state svg { width: 36px; height: 36px; stroke: currentColor; opacity: .3; margin: 0 auto 10px; display: block; }
.empty-state p { font-size: .85rem; }

/* ── ALERTS ── */
.bk-alert {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 14px 16px;
    border-radius: var(--radius-sm);
    font-size: .85rem;
    font-weight: 500;
    margin-bottom: 20px;
}

.bk-alert svg { width: 18px; height: 18px; stroke: currentColor; flex-shrink: 0; margin-top: 1px; }
.bk-alert-success { background: #DCFCE7; color: #15803D; border: 1px solid #BBF7D0; }
.bk-alert-danger  { background: #FEE2E2; color: #B91C1C; border: 1px solid #FECACA; }

/* ── RESPONSIVE ── */
@media (max-width: 860px) {
    .booking-wrapper { grid-template-columns: 1fr; }
    .info-cards { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 520px) {
    .info-cards { grid-template-columns: 1fr; }
    .booking-wrapper { padding: 20px 16px; }
    .page-header-inner { flex-wrap: wrap; gap: 12px; }
}
</style>

<!-- ── PAGE HEADER ── -->
<div class="page-header">
    <div class="page-header-inner">
        <div class="page-header-left">
            <div class="header-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M8 12h4m-2-2v4"/><circle cx="17" cy="11" r=".5" fill="currentColor"/><circle cx="17" cy="13" r=".5" fill="currentColor"/></svg>
            </div>
            <div>
                <h1>Booking PlayStation</h1>
                <p class="subtitle">Pilih unit, tentukan waktu, dan lanjut ke pembayaran</p>
            </div>
        </div>
        <div class="status-pill">
            <span class="status-dot"></span>
            Unit Tersedia
        </div>
    </div>
</div>

<!-- ── MAIN ── -->
<div class="booking-wrapper">

    <!-- LEFT: FORM -->
    <div class="left-col">
        <div class="bk-card">
            <div class="bk-card-header">
                <div class="bk-card-header-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01"/></svg>
                </div>
                <div>
                    <h2>Form Booking</h2>
                    <p>Isi detail sesi bermain kamu</p>
                </div>
            </div>
            <div class="bk-card-body">

                <?php if ($success): ?>
                    <div class="bk-alert bk-alert-success">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bk-alert bk-alert-danger">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="bookingForm">
                    <div class="form-group">
                        <label>Pilih PlayStation <span class="req">*</span></label>
                        <div class="input-wrap">
                            <span class="input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                            </span>
                            <select name="ps_id" id="psSelect" class="form-control" required onchange="updatePrice()">
                                <option value="">— Pilih Unit —</option>
                                <?php
                                $res_ps = $conn->query("SELECT * FROM playstation WHERE status = 'available' ORDER BY type DESC, name ASC");
                                while ($row = $res_ps->fetch_assoc()):
                                    $price = ($row['type'] == 'PS4') ? 20000 : 30000;
                                    ?>
                                    <option value="<?= $row['id'] ?>" data-type="<?= $row['type'] ?>" data-price="<?= $price ?>">
                                        <?= htmlspecialchars($row['name']) ?> (<?= $row['type'] ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <span class="select-arrow">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                            </span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Tanggal Main <span class="req">*</span></label>
                        <div class="input-wrap">
                            <span class="input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                            </span>
                            <input type="date" name="booking_date" id="bookingDate" class="form-control"
                                   min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required />
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Jam Mulai <span class="req">*</span></label>
                        <div class="input-wrap">
                            <span class="input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                            </span>
                            <input type="time" name="start_time" id="startTime" class="form-control"
                                   min="09:00" max="23:00" required onchange="updatePrice()" />
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:0">
                        <label>Durasi Bermain <span class="req">*</span></label>
                        <div class="input-wrap">
                            <span class="input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                            </span>
                            <select name="duration" id="durSelect" class="form-control" required onchange="updatePrice()">
                                <option value="1">1 Jam</option>
                                <option value="2">2 Jam</option>
                                <option value="3">3 Jam</option>
                                <option value="4">4 Jam</option>
                            </select>
                            <span class="select-arrow">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                            </span>
                        </div>
                    </div>

                    <div class="price-preview">
                        <div class="price-row">
                            <span>Harga per jam</span>
                            <span id="pricePerHour">—</span>
                        </div>
                        <div class="price-row">
                            <span>Durasi</span>
                            <span id="durationLabel">—</span>
                        </div>
                        <div class="price-row">
                            <span>Jam selesai</span>
                            <span id="endTimeLabel">—</span>
                        </div>
                        <div class="price-row total">
                            <span>Total Pembayaran</span>
                            <span class="val" id="totalPrice">—</span>
                        </div>
                    </div>

                    <button type="submit" name="book_now" class="btn-book">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                        Lanjut ke Pembayaran
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- RIGHT COL -->
    <div class="right-col">

        <!-- INFO CARDS -->
        <div class="info-cards">
            <?php
            $avail = $conn->query("SELECT COUNT(*) FROM playstation WHERE status='available'")->fetch_row()[0] ?? 0;
            $my_bookings = $conn->query("SELECT COUNT(*) FROM bookings WHERE user_id = $user_id")->fetch_row()[0] ?? 0;
            ?>
            <div class="info-card">
                <div class="info-card-icon blue">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                </div>
                <div class="info-card-val"><?= $avail ?></div>
                <div class="info-card-lbl">Unit tersedia</div>
            </div>
            <div class="info-card">
                <div class="info-card-icon green">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                </div>
                <div class="info-card-val">09–23</div>
                <div class="info-card-lbl">Jam operasional</div>
            </div>
            <div class="info-card">
                <div class="info-card-icon amber">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                </div>
                <div class="info-card-val"><?= $my_bookings ?>×</div>
                <div class="info-card-lbl">Total booking saya</div>
            </div>
        </div>

        <!-- HISTORY TABLE -->
        <div class="bk-card">
            <div class="bk-card-header">
                <div class="bk-card-header-icon green">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                </div>
                <div class="section-title-row">
                    <div>
                        <h2>Riwayat Booking</h2>
                        <p>5 booking terakhir kamu</p>
                    </div>
                    
                </div>
            </div>

            <div class="table-wrap">
                <?php
                $query_h = "SELECT b.*, p.name as ps_name, p.type as ps_type
                            FROM bookings b
                            JOIN playstation p ON b.ps_id = p.id
                            WHERE b.user_id = ?
                            ORDER BY b.created_at DESC LIMIT 5";
                $stmt_h = $conn->prepare($query_h);
                $stmt_h->bind_param("i", $user_id);
                $stmt_h->execute();
                $res_h = $stmt_h->get_result();
                $has_data = $res_h->num_rows > 0;
                ?>

                <?php if ($has_data): ?>
                    <table class="bk-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Unit</th>
                                <th>Waktu</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($h = $res_h->fetch_assoc()):
                                $dur_hours = round((strtotime($h['end_time']) - strtotime($h['start_time'])) / 3600);
                                $ps_class = ($h['ps_type'] == 'PS4') ? 'ps4' : '';

                                switch ($h['payment_status']) {
                                    case 'paid':
                                        $badge_class = 'badge-paid';
                                        $badge_icon = '<path d="m9 12 2 2 4-4"/>';
                                        $badge_label = 'Paid';
                                        break;
                                    case 'pending':
                                        $badge_class = 'badge-pending';
                                        $badge_icon = '<path d="M12 6v6l4 2"/>';
                                        $badge_label = 'Pending';
                                        break;
                                    case 'cancelled':
                                        $badge_class = 'badge-cancelled';
                                        $badge_icon = '<path d="m15 9-6 6M9 9l6 6"/>';
                                        $badge_label = 'Cancelled';
                                        break;
                                    default:
                                        $badge_class = 'badge-expired';
                                        $badge_icon = '<circle cx="12" cy="12" r="10"/>';
                                        $badge_label = ucfirst($h['payment_status']);
                                        break;
                                }

                                $days_id = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
                                $day_en = date('l', strtotime($h['booking_date']));
                                $day_id = $days_id[$day_en] ?? $day_en;
                                ?>
                                <tr>
                                    <td>
                                        <div class="td-date-main"><?= date('d M Y', strtotime($h['booking_date'])) ?></div>
                                        <div class="td-date-sub"><?= $day_id ?></div>
                                    </td>
                                    <td>
                                        <span class="ps-tag <?= $ps_class ?>">
                                            <?= htmlspecialchars($h['ps_name']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="td-time-main"><?= substr($h['start_time'], 0, 5) ?></div>
                                        <div class="td-time-sub"><?= $dur_hours ?> jam</div>
                                    </td>
                                    <td class="td-price">Rp <?= number_format($h['total_price'], 0, ',', '.') ?></td>
                                    <td>
                                        <span class="badge <?= $badge_class ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="10"/>
                                                <?= $badge_icon ?>
                                            </svg>
                                            <?= $badge_label ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg>
                        <p>Belum ada riwayat booking.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
(function() {
    function rupiah(n) {
        return 'Rp ' + n.toLocaleString('id-ID');
    }

    function addHours(timeStr, hours) {
        if (!timeStr) return '—';
        var parts = timeStr.split(':');
        var h = parseInt(parts[0]) + hours;
        var m = parts[1] || '00';
        if (h > 23) return 'Melebihi jam operasional';
        return String(h).padStart(2, '0') + ':' + m;
    }

    window.updatePrice = function() {
        var sel = document.getElementById('psSelect');
        var opt = sel.options[sel.selectedIndex];
        var dur = parseInt(document.getElementById('durSelect').value);
        var start = document.getElementById('startTime').value;

        if (!opt || !opt.value) {
            ['pricePerHour','durationLabel','endTimeLabel','totalPrice'].forEach(function(id) {
                document.getElementById(id).textContent = '—';
            });
            return;
        }

        var price = parseInt(opt.getAttribute('data-price'));
        document.getElementById('pricePerHour').textContent = rupiah(price);
        document.getElementById('durationLabel').textContent = dur + ' jam';
        document.getElementById('endTimeLabel').textContent = start ? addHours(start, dur) : '—';
        document.getElementById('totalPrice').textContent = rupiah(price * dur);
    };

    updatePrice();
})();
</script>

<?php include '../includes/footer.php'; ?>
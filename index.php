<?php
require_once 'config.php';

// Ambil statistik dari DB
$total_users = $conn->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetch_row()[0] ?? 0;
$total_bookings = $conn->query("SELECT COUNT(*) FROM bookings")->fetch_row()[0] ?? 0;
$total_duels = $conn->query("SELECT COUNT(*) FROM duel_matches")->fetch_row()[0] ?? 0;
$total_units = $conn->query("SELECT COUNT(*) FROM playstation WHERE status='available'")->fetch_row()[0] ?? 0;

include 'includes/header.php';
?>

<style>
    /* ── INDEX PAGE STYLES ── */

    /* Hero */
    .hero {
        padding: 5rem 0 4rem;
        background: var(--white);
        border-bottom: 1px solid var(--border);
        position: relative;
        overflow: hidden;
    }

    .hero::before {
        content: '';
        position: absolute;
        top: -120px;
        right: -120px;
        width: 480px;
        height: 480px;
        background: radial-gradient(circle, rgba(59, 91, 219, .08) 0%, transparent 70%);
        pointer-events: none;
    }

    .hero::after {
        content: '';
        position: absolute;
        bottom: -80px;
        left: -80px;
        width: 320px;
        height: 320px;
        background: radial-gradient(circle, rgba(59, 91, 219, .06) 0%, transparent 70%);
        pointer-events: none;
    }

    .hero-inner {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4rem;
        align-items: center;
        max-width: 1160px;
        margin: 0 auto;
        padding: 0 20px;
        position: relative;
        z-index: 1;
    }

    .hero-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        background: var(--primary-light);
        color: var(--primary);
        font-size: .8rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        padding: .35rem .85rem;
        border-radius: 999px;
        margin-bottom: 1.25rem;
        border: 1px solid rgba(59, 91, 219, .18);
    }

    .hero-eyebrow svg {
        width: 14px;
        height: 14px;
        stroke: currentColor;
    }

    .hero h1 {
        font-size: clamp(2rem, 4vw, 3rem);
        font-weight: 800;
        color: var(--text);
        line-height: 1.18;
        margin-bottom: 1.25rem;
        letter-spacing: -.02em;
    }

    .hero h1 .accent {
        color: var(--primary);
    }

    .hero-desc {
        font-size: 1.05rem;
        color: var(--text-muted);
        line-height: 1.75;
        margin-bottom: 2rem;
        max-width: 480px;
    }

    .hero-actions {
        display: flex;
        gap: .75rem;
        flex-wrap: wrap;
    }

    /* Hero visual */
    .hero-visual {
        display: grid;
        grid-template-columns: 1fr 1fr;
        grid-template-rows: auto auto;
        gap: 1rem;
    }

    .hero-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: .5rem;
        transition: box-shadow var(--dur) var(--ease), transform var(--dur) var(--ease);
    }

    .hero-card:hover {
        box-shadow: var(--shadow);
        transform: translateY(-3px);
    }

    .hero-card.span-2 {
        grid-column: span 2;
    }

    .hero-card-icon {
        width: 40px;
        height: 40px;
        border-radius: var(--radius-sm);
        background: var(--primary-light);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        margin-bottom: .25rem;
    }

    .hero-card-icon svg {
        width: 20px;
        height: 20px;
        stroke: currentColor;
    }

    .hero-card-label {
        font-size: .75rem;
        color: var(--text-muted);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .hero-card-value {
        font-size: 1.6rem;
        font-weight: 800;
        color: var(--text);
        line-height: 1;
    }

    .hero-card-sub {
        font-size: .8rem;
        color: var(--text-muted);
    }

    /* PS Unit card khusus */
    .unit-list {
        display: flex;
        gap: .5rem;
        flex-wrap: wrap;
        margin-top: .25rem;
    }

    .unit-chip {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        padding: .3rem .7rem;
        border-radius: 999px;
        font-size: .75rem;
        font-weight: 600;
        border: 1px solid var(--border);
        background: var(--white);
        color: var(--text);
    }

    .unit-chip.ps5 {
        background: var(--primary-light);
        color: var(--primary);
        border-color: rgba(59, 91, 219, .2);
    }

    .unit-chip.ps4 {
        background: #F0FDF4;
        color: #166534;
        border-color: rgba(46, 204, 113, .2);
    }

    .unit-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
        flex-shrink: 0;
    }

    /* ── STATS BAR ── */
    .stats-bar {
        background: var(--primary);
        padding: 1.25rem 0;
    }

    .stats-bar-inner {
        max-width: 1160px;
        margin: 0 auto;
        padding: 0 20px;
        display: flex;
        align-items: center;
        justify-content: space-around;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .stat-bar-item {
        display: flex;
        align-items: center;
        gap: .75rem;
        color: var(--white);
    }

    .stat-bar-icon {
        width: 38px;
        height: 38px;
        border-radius: var(--radius-sm);
        background: rgba(255, 255, 255, .15);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .stat-bar-icon svg {
        width: 18px;
        height: 18px;
        stroke: var(--white);
    }

    .stat-bar-num {
        font-size: 1.4rem;
        font-weight: 800;
        line-height: 1;
    }

    .stat-bar-lbl {
        font-size: .78rem;
        opacity: .8;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .stat-bar-divider {
        width: 1px;
        height: 36px;
        background: rgba(255, 255, 255, .2);
    }

    /* ── FEATURES ── */
    .features {
        padding: 5rem 0;
        background: var(--bg);
    }

    .features-header {
        text-align: center;
        margin-bottom: 3rem;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.25rem;
    }

    .feature-card {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 2rem 1.75rem;
        transition: box-shadow var(--dur) var(--ease), transform var(--dur) var(--ease);
    }

    .feature-card:hover {
        box-shadow: var(--shadow);
        transform: translateY(-3px);
    }

    .feature-icon {
        width: 48px;
        height: 48px;
        border-radius: var(--radius);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.25rem;
    }

    .feature-icon svg {
        width: 24px;
        height: 24px;
        stroke: currentColor;
    }

    .feature-icon.blue {
        background: var(--primary-light);
        color: var(--primary);
    }

    .feature-icon.green {
        background: #DCFCE7;
        color: #166534;
    }

    .feature-icon.orange {
        background: #FEF3C7;
        color: #92400E;
    }

    .feature-icon.red {
        background: #FEE2E2;
        color: #991B1B;
    }

    .feature-icon.purple {
        background: #F5F3FF;
        color: #6D28D9;
    }

    .feature-icon.teal {
        background: #F0FDFA;
        color: #0F766E;
    }

    .feature-card h3 {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text);
        margin-bottom: .5rem;
    }

    .feature-card p {
        font-size: .875rem;
        color: var(--text-muted);
        line-height: 1.65;
    }

    /* ── HOW IT WORKS ── */
    .how-it-works {
        padding: 5rem 0;
        background: var(--white);
        border-top: 1px solid var(--border);
        border-bottom: 1px solid var(--border);
    }

    .steps {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.5rem;
        position: relative;
    }

    .steps::before {
        content: '';
        position: absolute;
        top: 28px;
        left: calc(12.5% + 28px);
        right: calc(12.5% + 28px);
        height: 2px;
        background: var(--border);
        z-index: 0;
    }

    .step {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: .75rem;
        position: relative;
        z-index: 1;
    }

    .step-num {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: var(--white);
        border: 2px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        font-weight: 800;
        color: var(--text-muted);
        flex-shrink: 0;
        transition: all var(--dur) var(--ease);
    }

    .step-num.active {
        background: var(--primary);
        border-color: var(--primary);
        color: var(--white);
        box-shadow: 0 4px 14px rgba(59, 91, 219, .35);
    }

    .step h4 {
        font-size: .9rem;
        font-weight: 700;
        color: var(--text);
    }

    .step p {
        font-size: .8rem;
        color: var(--text-muted);
        line-height: 1.6;
    }

    /* ── PRICING ── */
    .pricing {
        padding: 5rem 0;
        background: var(--bg);
    }

    .pricing-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        max-width: 680px;
        margin: 0 auto;
    }

    .pricing-card {
        background: var(--white);
        border: 1.5px solid var(--border);
        border-radius: var(--radius-xl);
        padding: 2.25rem;
        text-align: center;
        position: relative;
        transition: box-shadow var(--dur) var(--ease), transform var(--dur) var(--ease);
    }

    .pricing-card:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-4px);
    }

    .pricing-card.featured {
        border-color: var(--primary);
        box-shadow: var(--shadow);
    }

    .pricing-badge {
        position: absolute;
        top: -12px;
        left: 50%;
        transform: translateX(-50%);
        background: var(--primary);
        color: var(--white);
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        padding: .25rem .85rem;
        border-radius: 999px;
        white-space: nowrap;
    }

    .pricing-icon {
        width: 56px;
        height: 56px;
        border-radius: var(--radius);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
    }

    .pricing-icon svg {
        width: 28px;
        height: 28px;
        stroke: currentColor;
    }

    .pricing-icon.ps4 {
        background: #DCFCE7;
        color: #166534;
    }

    .pricing-icon.ps5 {
        background: var(--primary-light);
        color: var(--primary);
    }

    .pricing-card h3 {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--text);
        margin-bottom: .5rem;
    }

    .pricing-price {
        font-size: 2.2rem;
        font-weight: 800;
        color: var(--primary);
        line-height: 1;
        margin-bottom: .25rem;
    }

    .pricing-unit {
        font-size: .85rem;
        color: var(--text-muted);
        margin-bottom: 1.5rem;
    }

    .pricing-features {
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: .6rem;
        margin-bottom: 1.75rem;
        text-align: left;
    }

    .pricing-features li {
        font-size: .875rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: .5rem;
    }

    .pricing-features li svg {
        width: 15px;
        height: 15px;
        stroke: var(--success);
        flex-shrink: 0;
    }

    /* ── CTA BANNER ── */
    .cta-banner {
        padding: 4.5rem 0;
        background: var(--primary);
        position: relative;
        overflow: hidden;
    }

    .cta-banner::before {
        content: '';
        position: absolute;
        top: -60px;
        right: -60px;
        width: 320px;
        height: 320px;
        background: rgba(255, 255, 255, .06);
        border-radius: 50%;
    }

    .cta-banner::after {
        content: '';
        position: absolute;
        bottom: -80px;
        left: 10%;
        width: 240px;
        height: 240px;
        background: rgba(255, 255, 255, .04);
        border-radius: 50%;
    }

    .cta-inner {
        max-width: 640px;
        margin: 0 auto;
        text-align: center;
        position: relative;
        z-index: 1;
        padding: 0 20px;
    }

    .cta-banner h2 {
        font-size: clamp(1.6rem, 3vw, 2.2rem);
        font-weight: 800;
        color: var(--white);
        margin-bottom: .75rem;
        line-height: 1.25;
    }

    .cta-banner p {
        color: rgba(255, 255, 255, .8);
        font-size: 1rem;
        margin-bottom: 2rem;
        line-height: 1.7;
    }

    .cta-buttons {
        display: flex;
        gap: .75rem;
        justify-content: center;
        flex-wrap: wrap;
    }

    .btn-white {
        background: var(--white);
        color: var(--primary);
        font-weight: 700;
        padding: .7rem 1.75rem;
        border-radius: var(--radius-sm);
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        transition: all var(--dur) var(--ease);
        font-size: .9rem;
        text-decoration: none;
    }

    .btn-white svg {
        width: 16px;
        height: 16px;
        stroke: currentColor;
    }

    .btn-white:hover {
        background: var(--card-bg);
        color: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(0, 0, 0, .15);
    }

    .btn-ghost-white {
        background: rgba(255, 255, 255, .12);
        color: var(--white);
        font-weight: 600;
        padding: .7rem 1.75rem;
        border-radius: var(--radius-sm);
        border: 1.5px solid rgba(255, 255, 255, .3);
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        transition: all var(--dur) var(--ease);
        font-size: .9rem;
        text-decoration: none;
    }

    .btn-ghost-white svg {
        width: 16px;
        height: 16px;
        stroke: currentColor;
    }

    .btn-ghost-white:hover {
        background: rgba(255, 255, 255, .2);
        border-color: rgba(255, 255, 255, .6);
        color: var(--white);
        transform: translateY(-1px);
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 900px) {
        .hero-inner {
            grid-template-columns: 1fr;
            gap: 2.5rem;
        }

        .hero h1 {
            font-size: 2.2rem;
        }

        .hero-desc {
            max-width: 100%;
        }

        .features-grid {
            grid-template-columns: 1fr 1fr;
        }

        .steps {
            grid-template-columns: 1fr 1fr;
            row-gap: 2.5rem;
        }

        .steps::before {
            display: none;
        }
    }

    @media (max-width: 600px) {
        .hero {
            padding: 3rem 0;
        }

        .hero-visual {
            grid-template-columns: 1fr;
        }

        .hero-card.span-2 {
            grid-column: span 1;
        }

        .stats-bar-divider {
            display: none;
        }

        .stats-bar-inner {
            gap: 1.5rem;
            justify-content: flex-start;
        }

        .features-grid {
            grid-template-columns: 1fr;
        }

        .steps {
            grid-template-columns: 1fr;
        }

        .pricing-grid {
            grid-template-columns: 1fr;
            max-width: 340px;
        }

        .cta-buttons {
            flex-direction: column;
            align-items: center;
        }
    }
</style>

<!-- ── HERO ── -->
<section class="hero">
    <div class="hero-inner">
        <!-- Left: copy -->
        <div class="hero-text">
            <div class="hero-eyebrow">
                <i data-lucide="gamepad-2"></i>
                PlayStation Rental
            </div>
            <h1>
                Booking PS4 & PS5<br>
                <span class="accent">Kapan Saja,</span><br>
                Dari Mana Saja
            </h1>
            <p class="hero-desc">
                Sewa PlayStation dengan sistem booking online yang mudah. Pilih unit, tentukan jam main, bayar — dan
                langsung main tanpa antri.
            </p>
            <div class="hero-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="user/booking.php" class="btn btn-primary btn-lg">
                        <i data-lucide="calendar-check"></i>
                        Booking Sekarang
                    </a>
                    <a href="user/leaderboard.php" class="btn btn-secondary btn-lg">
                        <i data-lucide="bar-chart-2"></i>
                        Leaderboard
                    </a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-primary btn-lg">
                        <i data-lucide="user-plus"></i>
                        Daftar Gratis
                    </a>
                    <a href="login.php" class="btn btn-secondary btn-lg">
                        <i data-lucide="log-in"></i>
                        Masuk
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: visual cards -->
        <div class="hero-visual">
            <div class="hero-card">
                <div class="hero-card-icon">
                    <i data-lucide="monitor"></i>
                </div>
                <div class="hero-card-label">Unit Tersedia</div>
                <div class="hero-card-value"><?= $total_units ?></div>
                <div class="hero-card-sub">Unit siap dimainkan</div>
                <div class="unit-list">
                    <span class="unit-chip ps5"><span class="unit-dot"></span>PS5</span>
                    <span class="unit-chip ps4"><span class="unit-dot"></span>PS4</span>
                </div>
            </div>

            <div class="hero-card">
                <div class="hero-card-icon">
                    <i data-lucide="clock"></i>
                </div>
                <div class="hero-card-label">Jam Operasional</div>
                <div class="hero-card-value">09.00</div>
                <div class="hero-card-sub">s/d 23.00 WIB setiap hari</div>
            </div>

            <div class="hero-card span-2">
                <div class="hero-card-icon">
                    <i data-lucide="zap"></i>
                </div>
                <div class="hero-card-label">Booking online — bayar via Midtrans</div>
                <div class="hero-card-value" style="font-size:1.1rem;font-weight:600;color:var(--text-muted);">
                    GoPay · Transfer Bank · QRIS · Kartu Kredit
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── STATS BAR ── -->
<div class="stats-bar">
    <div class="stats-bar-inner">
        <div class="stat-bar-item">
            <div class="stat-bar-icon"><i data-lucide="users"></i></div>
            <div>
                <div class="stat-bar-num"><?= number_format($total_users) ?>+</div>
                <div class="stat-bar-lbl">Gamers</div>
            </div>
        </div>
        <div class="stat-bar-divider"></div>
        <div class="stat-bar-item">
            <div class="stat-bar-icon"><i data-lucide="calendar-check"></i></div>
            <div>
                <div class="stat-bar-num"><?= number_format($total_bookings) ?>+</div>
                <div class="stat-bar-lbl">Booking</div>
            </div>
        </div>
        <div class="stat-bar-divider"></div>
        <div class="stat-bar-item">
            <div class="stat-bar-icon"><i data-lucide="swords"></i></div>
            <div>
                <div class="stat-bar-num"><?= number_format($total_duels) ?>+</div>
                <div class="stat-bar-lbl">Duel PvP</div>
            </div>
        </div>
        <div class="stat-bar-divider"></div>
        <div class="stat-bar-item">
            <div class="stat-bar-icon"><i data-lucide="trophy"></i></div>
            <div>
                <div class="stat-bar-num">24/7</div>
                <div class="stat-bar-lbl">Support</div>
            </div>
        </div>
    </div>
</div>

<!-- ── FEATURES ── -->
<section class="features">
    <div class="container">
        <div class="features-header">
            <h2 class="section-title">Semua yang Kamu Butuhkan</h2>
            <p class="section-subtitle">Lebih dari sekadar rental — ini ekosistem gaming lengkap</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon blue"><i data-lucide="calendar-check"></i></div>
                <h3>Booking Online</h3>
                <p>Pilih unit PS4 atau PS5, tentukan tanggal & jam, konfirmasi — selesai. Tidak perlu datang dulu hanya
                    untuk booking.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon green"><i data-lucide="credit-card"></i></div>
                <h3>Pembayaran Aman</h3>
                <p>Terintegrasi dengan Midtrans. Bayar via GoPay, OVO, Transfer Bank, QRIS, atau Kartu Kredit.
                    Auto-expire 15 menit jika tidak bayar.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon orange"><i data-lucide="trophy"></i></div>
                <h3>Tournament</h3>
                <p>Ikuti tournament resmi yang diadakan setiap minggu. Daftar, bayar entry fee, dan buktikan siapa yang
                    terbaik.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon red"><i data-lucide="swords"></i></div>
                <h3>Duel PvP</h3>
                <p>Tantang gamer lain dalam duel 1v1. Maksimal 3x duel per pasangan per minggu. Menang = poin naik,
                    kalah = belajar.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon purple"><i data-lucide="bar-chart-2"></i></div>
                <h3>Leaderboard</h3>
                <p>Semua kemenangan tercatat. Lihat ranking kamu dibanding gamer lain — dari Rookie sampai Pro Player.
                </p>
            </div>
            <div class="feature-card">
                <div class="feature-icon teal"><i data-lucide="shield-check"></i></div>
                <h3>Anti Race Condition</h3>
                <p>Sistem booking atomic berbasis stored procedure — dijamin tidak ada double booking meskipun ada
                    banyak user yang booking bersamaan.</p>
            </div>
        </div>
    </div>
</section>

<!-- ── HOW IT WORKS ── -->
<section class="how-it-works">
    <div class="container">
        <div class="features-header">
            <h2 class="section-title">Cara Kerja</h2>
            <p class="section-subtitle">4 langkah mudah untuk mulai main</p>
        </div>
        <div class="steps">
            <div class="step">
                <div class="step-num active">1</div>
                <h4>Daftar Akun</h4>
                <p>Buat akun gratis dengan username, email, dan nomor HP</p>
            </div>
            <div class="step">
                <div class="step-num active">2</div>
                <h4>Pilih Unit & Waktu</h4>
                <p>Cek unit yang tersedia, pilih PS4 atau PS5, tentukan jam main</p>
            </div>
            <div class="step">
                <div class="step-num active">3</div>
                <h4>Bayar Online</h4>
                <p>Bayar via Midtrans — GoPay, Transfer Bank, QRIS, dan lainnya</p>
            </div>
            <div class="step">
                <div class="step-num active">4</div>
                <h4>Langsung Main</h4>
                <p>Booking terkonfirmasi otomatis setelah pembayaran berhasil</p>
            </div>
        </div>
    </div>
</section>

<!-- ── PRICING ── -->
<section class="pricing">
    <div class="container">
        <div class="features-header">
            <h2 class="section-title">Harga Sewa</h2>
            <p class="section-subtitle">Transparan, tidak ada biaya tersembunyi</p>
        </div>
        <div class="pricing-grid">
            <!-- PS4 -->
            <div class="pricing-card">
                <div class="pricing-icon ps4"><i data-lucide="monitor"></i></div>
                <h3>PlayStation 4</h3>
                <div class="pricing-price">Rp 20.000</div>
                <div class="pricing-unit">per jam</div>
                <ul class="pricing-features">
                    <li><i data-lucide="check"></i> Durasi 1–4 jam</li>
                    <li><i data-lucide="check"></i> 2 unit tersedia</li>
                    <li><i data-lucide="check"></i> DualShock 4 Controller</li>
                    <li><i data-lucide="check"></i> Semua game PS4</li>
                </ul>
                <a href="<?= isset($_SESSION['user_id']) ? 'user/booking.php' : 'register.php' ?>"
                    class="btn btn-secondary" style="width:100%;justify-content:center;">
                    <i data-lucide="calendar-check"></i>
                    Booking PS4
                </a>
            </div>

            <!-- PS5 -->
            <div class="pricing-card featured">
                <div class="pricing-badge">Paling Populer</div>
                <div class="pricing-icon ps5"><i data-lucide="monitor"></i></div>
                <h3>PlayStation 5</h3>
                <div class="pricing-price">Rp 30.000</div>
                <div class="pricing-unit">per jam</div>
                <ul class="pricing-features">
                    <li><i data-lucide="check"></i> Durasi 1–4 jam</li>
                    <li><i data-lucide="check"></i> 2 unit tersedia</li>
                    <li><i data-lucide="check"></i> DualSense Controller</li>
                    <li><i data-lucide="check"></i> Semua game PS5 + PS4</li>
                </ul>
                <a href="<?= isset($_SESSION['user_id']) ? 'user/booking.php' : 'register.php' ?>"
                    class="btn btn-primary" style="width:100%;justify-content:center;">
                    <i data-lucide="calendar-check"></i>
                    Booking PS5
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ── CTA BANNER ── -->
<section class="cta-banner">
    <div class="cta-inner">
        <?php if (isset($_SESSION['user_id'])): ?>
            <h2>Siap Main? Booking Sekarang</h2>
            <p>Unit tersedia setiap hari mulai pukul 09.00–23.00 WIB. Cek jadwal dan pilih slot favoritmu.</p>
            <div class="cta-buttons">
                <a href="user/booking.php" class="btn-white">
                    <i data-lucide="calendar-check"></i>
                    Lihat Jadwal
                </a>
                <a href="user/tournament.php" class="btn-ghost-white">
                    <i data-lucide="trophy"></i>
                    Ikut Tournament
                </a>
            </div>
        <?php else: ?>
            <h2>Gabung Sekarang — Gratis</h2>
            <p>Daftar dalam 30 detik. Booking PS4 atau PS5, ikuti tournament, dan tantang gamer lain via duel PvP.</p>
            <div class="cta-buttons">
                <a href="register.php" class="btn-white">
                    <i data-lucide="user-plus"></i>
                    Buat Akun Gratis
                </a>
                <a href="login.php" class="btn-ghost-white">
                    <i data-lucide="log-in"></i>
                    Sudah punya akun?
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
</script>

<?php include 'includes/footer.php'; ?>
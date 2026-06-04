<?php // Footer ?>
</main>

<footer class="site-footer">
    <div class="container">

        <!-- Top: Brand + Nav Columns -->
        <div class="footer-top">

            <!-- Brand col -->
            <div class="footer-brand-col">
                <a class="footer-brand" href="<?php echo base_url('index.php'); ?>">
                    <span class="brand-icon">
                        <i data-lucide="gamepad-2"></i>
                    </span>
                    <span class="brand-text">PS<span class="brand-accent">Rental</span></span>
                </a>
                <p class="footer-desc">
                    Sistem booking PlayStation berbasis web. Nikmati sesi PS4 &amp; PS5, ikuti turnamen, dan tantang
                    pemain lain lewat duel PvP kapan saja.
                </p>

                <!-- Social -->
                <div class="footer-socials">
                    <a href="#" class="footer-social-btn" aria-label="Facebook">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />
                        </svg>
                    </a>
                    <a href="#" class="footer-social-btn" aria-label="Instagram">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="20" height="20" x="2" y="2" rx="5" ry="5" />
                            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" />
                            <line x1="17.5" x2="17.51" y1="6.5" y2="6.5" />
                        </svg>
                    </a>
                    <a href="#" class="footer-social-btn" aria-label="Twitter / X">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path
                                d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z" />
                        </svg>
                    </a>
                    <a href="#" class="footer-social-btn" aria-label="YouTube">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path
                                d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.55 49.55 0 0 1-16.2 0A2 2 0 0 1 2.5 17" />
                            <path d="m10 15 5-3-5-3z" />
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Nav columns -->
            <div class="footer-nav-cols">

                <div class="footer-nav-group">
                    <p class="footer-nav-heading">Menu</p>
                    <ul class="footer-nav-list">
                        <li>
                            <a href="<?php echo base_url('index.php'); ?>">
                                <i data-lucide="home"></i> Home
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo base_url('user/booking.php'); ?>">
                                <i data-lucide="calendar-check"></i> Booking
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo base_url('user/tournament.php'); ?>">
                                <i data-lucide="trophy"></i> Tournament
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo base_url('user/leaderboard.php'); ?>">
                                <i data-lucide="bar-chart-2"></i> Leaderboard
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo base_url('user/duel.php'); ?>">
                                <i data-lucide="swords"></i> Duel
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="footer-nav-group">
                    <p class="footer-nav-heading">Akun</p>
                    <ul class="footer-nav-list">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li>
                                <a href="<?php echo base_url('user/profile.php'); ?>">
                                    <i data-lucide="user-circle"></i> Profile
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo base_url('user/booking.php'); ?>">
                                    <i data-lucide="clock"></i> Riwayat Booking
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo base_url('logout.php'); ?>" class="footer-nav-logout">
                                    <i data-lucide="log-out"></i> Logout
                                </a>
                            </li>
                        <?php else: ?>
                            <li>
                                <a href="<?php echo base_url('login.php'); ?>">
                                    <i data-lucide="log-in"></i> Login
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo base_url('register.php'); ?>">
                                    <i data-lucide="user-plus"></i> Register
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="footer-nav-group">
                    <p class="footer-nav-heading">Kontak</p>
                    <ul class="footer-nav-list footer-nav-list--contact">
                        <li>
                            <i data-lucide="map-pin"></i>
                            <span>Jl. Gaming No. 123, Kota</span>
                        </li>
                        <li>
                            <a href="tel:081234567890">
                                <i data-lucide="phone"></i> 0812-3456-7890
                            </a>
                        </li>
                        <li>
                            <a href="mailto:info@psrental.com">
                                <i data-lucide="mail"></i> info@psrental.com
                            </a>
                        </li>
                        <li>
                            <i data-lucide="clock"></i>
                            <span>Buka: 10.00 – 23.00 WIB</span>
                        </li>
                    </ul>
                </div>

            </div><!-- /footer-nav-cols -->
        </div><!-- /footer-top -->

        <!-- Bottom bar -->
        <div class="footer-bottom">
            <span class="footer-copy">
                &copy; <?php echo date('Y'); ?> PSRental. All rights reserved.
            </span>
            <div class="footer-bottom-links">
                <a href="#">Syarat &amp; Ketentuan</a>
                <span class="footer-bottom-dot"></span>
                <a href="#">Kebijakan Privasi</a>
            </div>
        </div>

    </div>
</footer>

<style>
    /* ── FOOTER REBUILT ── */
    .site-footer {
        background: var(--white);
        border-top: 1px solid var(--border);
        padding: 3rem 0 0;
        margin-top: auto;
    }

    /* Top layout */
    .footer-top {
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: 3rem;
        padding-bottom: 2.5rem;
        border-bottom: 1px solid var(--border);
    }

    /* Brand column */
    .footer-brand-col {}

    .footer-brand {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--text) !important;
        text-decoration: none;
        letter-spacing: -.02em;
        margin-bottom: .85rem;
    }

    .footer-brand .brand-icon {
        width: 32px;
        height: 32px;
        background: var(--primary);
        border-radius: var(--radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .footer-brand .brand-icon svg {
        width: 17px;
        height: 17px;
        stroke: #fff;
    }

    .footer-desc {
        font-size: .855rem;
        color: var(--text-muted);
        line-height: 1.75;
        max-width: 255px;
        margin-bottom: 1.5rem;
    }

    /* Social buttons */
    .footer-socials {
        display: flex;
        gap: .45rem;
    }

    .footer-social-btn {
        width: 34px;
        height: 34px;
        border-radius: var(--radius-sm);
        background: var(--bg);
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-muted);
        transition: all var(--dur) var(--ease);
        text-decoration: none;
    }

    .footer-social-btn svg {
        width: 15px;
        height: 15px;
        stroke: currentColor;
    }

    .footer-social-btn:hover {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
        transform: translateY(-2px);
    }

    /* Nav columns */
    .footer-nav-cols {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 2rem;
    }

    .footer-nav-heading {
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .09em;
        color: var(--text);
        margin-bottom: 1rem;
    }

    .footer-nav-list {
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: .55rem;
    }

    .footer-nav-list li a,
    .footer-nav-list li span {
        font-size: .855rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: .45rem;
        transition: color var(--dur) var(--ease);
        text-decoration: none;
    }

    .footer-nav-list li a svg,
    .footer-nav-list li span svg,
    .footer-nav-list li i[data-lucide] {
        width: 14px;
        height: 14px;
        stroke: currentColor;
        flex-shrink: 0;
    }

    /* Lucide icon inside list items */
    .footer-nav-list li>i[data-lucide] {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 14px;
        height: 14px;
        flex-shrink: 0;
        stroke: var(--text-muted);
    }

    .footer-nav-list--contact li {
        display: flex;
        align-items: flex-start;
        gap: .45rem;
        font-size: .855rem;
        color: var(--text-muted);
        line-height: 1.5;
    }

    .footer-nav-list--contact li i[data-lucide] {
        margin-top: 2px;
    }

    .footer-nav-list a:hover {
        color: var(--primary);
    }

    .footer-nav-logout {
        color: var(--danger) !important;
    }

    .footer-nav-logout:hover {
        color: #CC2222 !important;
    }

    /* Bottom bar */
    .footer-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.1rem 0;
        flex-wrap: wrap;
    }

    .footer-copy {
        font-size: .8rem;
        color: var(--text-muted);
    }

    .footer-bottom-links {
        display: flex;
        align-items: center;
        gap: .6rem;
    }

    .footer-bottom-links a {
        font-size: .8rem;
        color: var(--text-muted);
        transition: color var(--dur) var(--ease);
    }

    .footer-bottom-links a:hover {
        color: var(--primary);
    }

    .footer-bottom-dot {
        width: 3px;
        height: 3px;
        border-radius: 50%;
        background: var(--border);
        flex-shrink: 0;
    }

    /* Responsive */
    @media (max-width: 960px) {
        .footer-top {
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        .footer-desc {
            max-width: 100%;
        }
    }

    @media (max-width: 640px) {
        .footer-nav-cols {
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
    }

    @media (max-width: 420px) {
        .footer-nav-cols {
            grid-template-columns: 1fr;
        }

        .footer-bottom {
            flex-direction: column;
            text-align: center;
        }
    }

    /* Print */
    @media print {
        .site-footer {
            display: none;
        }
    }
</style>

<script>
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });

    if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
</body>

</html>
<?php
// CEGAH ERROR SESSION
if (session_status() === PHP_SESSION_ACTIVE) {
    // Session sudah aktif
} else {
    session_start();
}

// Fungsi untuk menentukan base path
function base_url($path = '')
{
    $self = $_SERVER['PHP_SELF'];
    $root = (
        strpos($self, '/admin/') !== false ||
        strpos($self, '/user/') !== false ||
        strpos($self, '/payment/') !== false
    ) ? '../' : '';
    return $root . $path;
}

// Fungsi untuk menentukan path CSS
function css_url()
{
    $self = $_SERVER['PHP_SELF'];
    if (
        strpos($self, '/admin/') !== false ||
        strpos($self, '/user/') !== false ||
        strpos($self, '/payment/') !== false
    ) {
        return '../assets/css/style.css';
    }
    return 'assets/css/style.css';
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PS Rental</title>

    <!-- CSS Utama -->
    <link rel="stylesheet" href="<?php echo css_url(); ?>">

    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>

<body>

    <nav class="navbar" id="navbar">
        <div class="navbar-inner">

            <!-- Brand -->
            <a class="nav-brand" href="<?php echo base_url('index.php'); ?>">
                <span class="brand-icon">
                    <i data-lucide="gamepad-2"></i>
                </span>
                <span class="brand-text">PS<span class="brand-accent">Rental</span></span>
            </a>

            <!-- Menu Desktop -->
            <ul class="nav-menu" id="navMenu">
                <?php
                $is_active = function ($page) use ($current_page) {
                    return $current_page == $page ? 'active' : '';
                };
                ?>

                <li>
                    <a href="<?php echo base_url('index.php'); ?>"
                        class="nav-link <?php echo $is_active('index.php'); ?>">
                        <i data-lucide="home"></i>
                        <span>Home</span>
                    </a>
                </li>

                <?php if (isset($_SESSION['user_id'])): ?>

                    <li>
                        <a href="<?php echo base_url('user/booking.php'); ?>"
                            class="nav-link <?php echo $is_active('booking.php'); ?>">
                            <i data-lucide="calendar-check"></i>
                            <span>Booking</span>
                        </a>
                    </li>

                    <li>
                        <a href="<?php echo base_url('user/tournament.php'); ?>"
                            class="nav-link <?php echo $is_active('tournament.php'); ?>">
                            <i data-lucide="trophy"></i>
                            <span>Tournament</span>
                        </a>
                    </li>

                    <li>
                        <a href="<?php echo base_url('user/duel.php'); ?>"
                            class="nav-link <?php echo $is_active('duel.php'); ?>">
                            <i data-lucide="swords"></i>
                            <span>Duel</span>
                        </a>
                    </li>

                    <li>
                        <a href="<?php echo base_url('user/leaderboard.php'); ?>"
                            class="nav-link <?php echo $is_active('leaderboard.php'); ?>">
                            <i data-lucide="bar-chart-2"></i>
                            <span>Leaderboard</span>
                        </a>
                    </li>

                    <!-- Divider -->
                    <li class="nav-divider"></li>

                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                        <li>
                            <a href="<?php echo base_url('admin/dashboard.php'); ?>"
                                class="nav-link nav-link--admin <?php echo $is_active('dashboard.php'); ?>">
                                <i data-lucide="shield-check"></i>
                                <span>Admin</span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <li>
                        <a href="<?php echo base_url('user/profile.php'); ?>"
                            class="nav-link nav-link--profile <?php echo $is_active('profile.php'); ?>">
                            <i data-lucide="user-circle"></i>
                            <span><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Profile'; ?></span>
                        </a>
                    </li>

                    <li>
                        <a href="<?php echo base_url('logout.php'); ?>" class="nav-link nav-link--logout">
                            <i data-lucide="log-out"></i>
                            <span>Logout</span>
                        </a>
                    </li>

                <?php else: ?>

                    <li>
                        <a href="<?php echo base_url('login.php'); ?>"
                            class="nav-link <?php echo $is_active('login.php'); ?>">
                            <i data-lucide="log-in"></i>
                            <span>Login</span>
                        </a>
                    </li>

                    <li>
                        <a href="<?php echo base_url('register.php'); ?>"
                            class="nav-link nav-link--register <?php echo $is_active('register.php'); ?>">
                            <i data-lucide="user-plus"></i>
                            <span>Register</span>
                        </a>
                    </li>

                <?php endif; ?>
            </ul>

            <!-- Hamburger Mobile -->
            <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>

        </div>
    </nav>

    <!-- Overlay mobile -->
    <div class="nav-overlay" id="navOverlay"></div>

    <main>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                lucide.createIcons();

                // Sticky navbar shadow on scroll
                const navbar = document.getElementById('navbar');
                window.addEventListener('scroll', function () {
                    navbar.classList.toggle('scrolled', window.scrollY > 10);
                });

                // Mobile toggle
                const navToggle = document.getElementById('navToggle');
                const navMenu = document.getElementById('navMenu');
                const overlay = document.getElementById('navOverlay');

                function openMenu() {
                    navMenu.classList.add('open');
                    navToggle.classList.add('open');
                    overlay.classList.add('show');
                    document.body.style.overflow = 'hidden';
                }

                function closeMenu() {
                    navMenu.classList.remove('open');
                    navToggle.classList.remove('open');
                    overlay.classList.remove('show');
                    document.body.style.overflow = '';
                }

                navToggle.addEventListener('click', function () {
                    navMenu.classList.contains('open') ? closeMenu() : openMenu();
                });

                overlay.addEventListener('click', closeMenu);

                document.querySelectorAll('.nav-menu .nav-link').forEach(link => {
                    link.addEventListener('click', closeMenu);
                });
            });
        </script>
<?php
if (!function_exists('env')) {
    function env($key, $default = null)
    {
        $v = getenv($key);
        if ($v === false || $v === '') $v = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        return ($v === null || $v === '') ? $default : $v;
    }
}

$host = env('DB_HOST', env('MYSQLHOST', 'localhost'));
$username = env('DB_USER', env('MYSQLUSER', 'root'));
$password = env('DB_PASS', env('MYSQLPASSWORD', ''));
$database = env('DB_NAME', env('MYSQLDATABASE', 'rental_ps'));
$port = (int) env('DB_PORT', env('MYSQLPORT', 3306));

$conn = new mysqli($host, $username, $password, $database, $port);

if ($conn->connect_error) {
    die(json_encode([
        'status' => 'error',
        'message' => 'Koneksi database gagal: ' . $conn->connect_error
    ]));
}

$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+07:00'");
date_default_timezone_set('Asia/Jakarta');

define('APP_NAME', 'Rental PS');
define('APP_URL', env('APP_URL', 'http://localhost/rental_ps'));
define('APP_VERSION', '2.0.0');

define('BOOKING_EXPIRE_MINUTES', 15);
define('PS4_PRICE_PER_HOUR', 20000);
define('PS5_PRICE_PER_HOUR', 30000);

define('MIDTRANS_FINISH_URL', APP_URL . '/payment/finish.php');
define('MIDTRANS_PENDING_URL', APP_URL . '/payment/pending.php');
define('MIDTRANS_ERROR_URL', APP_URL . '/payment/error.php');
define('MIDTRANS_SNAP_URL', (env('MIDTRANS_ENV') === 'production')
    ? 'https://app.midtrans.com/snap/snap.js'
    : 'https://app.sandbox.midtrans.com/snap/snap.js');

if (!function_exists('logActivity')) {
    function logActivity($conn, $userId, $action, $entityType = null, $entityId = null, $description = '')
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('isisiss', $userId, $action, $entityType, $entityId, $description, $ipAddress, $userAgent);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('generateOrderId')) {
    function generateOrderId($user_id)
    {
        return "RENTAL-" . $user_id . "-" . time() . "-" . rand(1000, 9999);
    }
}

if (!function_exists('getMidtransTransactionStatus')) {
    function getMidtransTransactionStatus($orderId)
    {
        try {
            $status = \Midtrans\Transaction::status($orderId);
            return json_decode(json_encode($status), true);
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (file_exists(__DIR__ . '/config_midtrans.php')) {
    require_once __DIR__ . '/config_midtrans.php';
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
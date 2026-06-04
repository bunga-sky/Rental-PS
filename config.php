<?php
/**
 * ============================================================
 * GLOBAL CONFIGURATION - RENTAL PS
 * ============================================================
 */

// 1. DATABASE CONNECTION
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'rental_ps';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode([
        'status' => 'error',
        'message' => 'Koneksi database gagal: ' . $conn->connect_error
    ]));
}

// Set charset & timezone database
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+07:00'");

// Set timezone PHP
date_default_timezone_set('Asia/Jakarta');


// 2. APP CONSTANTS
define('APP_NAME', 'Rental PS');
define('APP_URL', 'http://localhost/rental_ps'); // Ganti ke URL production saat live
define('APP_VERSION', '2.0.0');

// Booking Rules
define('BOOKING_EXPIRE_MINUTES', 15);
define('PS4_PRICE_PER_HOUR', 20000);
define('PS5_PRICE_PER_HOUR', 30000);


// 3. MIDTRANS CALLBACK URLS
define('MIDTRANS_FINISH_URL', APP_URL . '/payment/finish.php');
define('MIDTRANS_PENDING_URL', APP_URL . '/payment/pending.php');
define('MIDTRANS_ERROR_URL', APP_URL . '/payment/error.php');
define('MIDTRANS_SNAP_URL', 'https://app.sandbox.midtrans.com/snap/snap.js');


// 4. GLOBAL HELPER FUNCTIONS
/**
 * logActivity — Catat log ke database
 */
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

/**
 * generateOrderId — Generate ID Pesanan Unik
 */
if (!function_exists('generateOrderId')) {
    function generateOrderId($user_id)
    {
        return "RENTAL-" . $user_id . "-" . time() . "-" . rand(1000, 9999);
    }
}

/**
 * getMidtransTransactionStatus — Cek status langsung ke API Midtrans
 * Diperlukan oleh payment/finish.php
 */
if (!function_exists('getMidtransTransactionStatus')) {
    function getMidtransTransactionStatus($orderId)
    {
        try {
            // Memanggil class SDK Midtrans
            $status = \Midtrans\Transaction::status($orderId);
            // Mengubah object ke array agar sesuai dengan logika di finish.php kamu
            return json_decode(json_encode($status), true);
        } catch (\Exception $e) {
            return false;
        }
    }
}


// 5. LOAD MIDTRANS CONFIG & SDK
// Pastikan file config_midtrans.php ada di folder yang sama
if (file_exists(__DIR__ . '/config_midtrans.php')) {
    require_once __DIR__ . '/config_midtrans.php';
}

// Load Composer Autoload (WAJIB agar class \Midtrans\Transaction ditemukan)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
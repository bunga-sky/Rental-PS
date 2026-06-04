<?php
// ============================================================
// API MIDDLEWARE
// Semua API endpoint include file ini di awal
// ============================================================

require_once __DIR__ . '/../config.php';

// Selalu return JSON
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// CORS — sesuaikan jika pakai frontend terpisah
header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Wajib login untuk semua API (kecuali yang override)
session_start();
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
}

$currentUserId = (int) $_SESSION['user_id'];
$currentRole   = $_SESSION['role'] ?? 'customer';

// Helper: pastikan method yang dipakai benar
function requireMethod(string ...$methods): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods, true)) {
        jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
    }
}

// Helper: pastikan hanya admin yang akses
function requireAdmin(): void
{
    global $currentRole;
    if ($currentRole !== 'admin') {
        jsonResponse(['status' => 'error', 'message' => 'Forbidden'], 403);
    }
}

// Helper: ambil JSON body dari request
function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// Helper: validasi field wajib
function requireFields(array $data, array $fields): void
{
    foreach ($fields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            jsonResponse([
                'status'  => 'error',
                'message' => "Field '$field' wajib diisi"
            ], 422);
        }
    }
}

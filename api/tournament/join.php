<?php
// ============================================================
// API: Join Tournament
// POST /api/tournament/join.php
// Body: { tournament_id }
// ============================================================

require_once __DIR__ . '/../../api/middleware.php';
requireMethod('POST');

$body = getJsonBody();
requireFields($body, ['tournament_id']);

$tournamentId = (int) $body['tournament_id'];

// Ambil data tournament
$stmt = $conn->prepare("
    SELECT t.*,
           (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.id) AS current_participants
    FROM tournaments t
    WHERE t.id = ?
");
$stmt->bind_param('i', $tournamentId);
$stmt->execute();
$tournament = $stmt->get_result()->fetch_assoc();

if (!$tournament) {
    jsonResponse(['status' => 'error', 'message' => 'Tournament tidak ditemukan'], 404);
}
if ($tournament['status'] !== 'upcoming') {
    jsonResponse(['status' => 'error', 'message' => 'Tournament tidak bisa diikuti (status: ' . $tournament['status'] . ')'], 400);
}
if ($tournament['current_participants'] >= $tournament['max_participants']) {
    jsonResponse(['status' => 'error', 'message' => 'Kuota tournament sudah penuh'], 400);
}

// Cek sudah terdaftar
$stmtCheck = $conn->prepare("
    SELECT id FROM tournament_participants WHERE tournament_id = ? AND user_id = ?
");
$stmtCheck->bind_param('ii', $tournamentId, $currentUserId);
$stmtCheck->execute();
if ($stmtCheck->get_result()->num_rows > 0) {
    jsonResponse(['status' => 'error', 'message' => 'Kamu sudah terdaftar di tournament ini'], 400);
}

// Jika ada entry fee → arahkan ke payment
if ((float) $tournament['entry_fee'] > 0) {
    $orderId = 'TOURNEY-' . $currentUserId . '-' . $tournamentId . '-' . time();

    // Cek apakah sudah pernah buat payment untuk tournament ini (idempotency)
    $stmtPayCheck = $conn->prepare("
        SELECT id, order_id, snap_token, status FROM tournament_payments
        WHERE tournament_id = ? AND user_id = ?
    ");
    $stmtPayCheck->bind_param('ii', $tournamentId, $currentUserId);
    $stmtPayCheck->execute();
    $existingPay = $stmtPayCheck->get_result()->fetch_assoc();

    if ($existingPay && in_array($existingPay['status'], ['pending', 'unpaid'], true)) {
        // Idempotent: return payment yang sudah ada
        jsonResponse([
            'status'       => 'payment_required',
            'message'      => 'Selesaikan pembayaran entry fee',
            'entry_fee'    => $tournament['entry_fee'],
            'formatted'    => 'Rp ' . number_format($tournament['entry_fee'], 0, ',', '.'),
            'order_id'     => $existingPay['order_id'],
            'snap_token'   => $existingPay['snap_token'],
            'payment_url'  => APP_URL . '/payment/tournament.php?order_id=' . $existingPay['order_id'],
        ]);
    }

    // Buat payment record baru
    $stmtPayInsert = $conn->prepare("
        INSERT INTO tournament_payments (order_id, tournament_id, user_id, amount, status)
        VALUES (?, ?, ?, ?, 'unpaid')
    ");
    $stmtPayInsert->bind_param('siid',
        $orderId, $tournamentId, $currentUserId, $tournament['entry_fee']
    );
    $stmtPayInsert->execute();

    jsonResponse([
        'status'      => 'payment_required',
        'message'     => 'Lakukan pembayaran entry fee untuk mendaftar',
        'entry_fee'   => $tournament['entry_fee'],
        'formatted'   => 'Rp ' . number_format($tournament['entry_fee'], 0, ',', '.'),
        'order_id'    => $orderId,
        'payment_url' => APP_URL . '/payment/tournament.php?order_id=' . $orderId,
    ]);
}

// Gratis — langsung daftarkan
$stmtJoin = $conn->prepare("
    INSERT INTO tournament_participants (tournament_id, user_id) VALUES (?, ?)
");
$stmtJoin->bind_param('ii', $tournamentId, $currentUserId);

if (!$stmtJoin->execute()) {
    jsonResponse(['status' => 'error', 'message' => 'Gagal mendaftar tournament'], 500);
}

logActivity($conn, $currentUserId, 'tournament.joined', 'tournaments', $tournamentId,
    "Join tournament: {$tournament['name']}");

jsonResponse([
    'status'  => 'success',
    'message' => 'Berhasil mendaftar tournament ' . $tournament['name'] . '!',
    'tournament' => [
        'id'   => $tournamentId,
        'name' => $tournament['name'],
        'date' => $tournament['start_date'],
    ],
]);

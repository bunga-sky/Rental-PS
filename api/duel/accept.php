<?php
// ============================================================
// API: Terima Duel Challenge
// POST /api/duel/accept.php
// Body: { match_id }
// ============================================================

require_once __DIR__ . '/../../api/middleware.php';
requireMethod('POST');

$body = getJsonBody();
requireFields($body, ['match_id']);

$matchId = (int) $body['match_id'];

// Ambil match — pastikan user ini adalah opponent
$stmt = $conn->prepare("
    SELECT dm.*, u.username AS challenger_name
    FROM duel_matches dm
    JOIN users u ON dm.challenger_id = u.id
    WHERE dm.id = ? AND dm.opponent_id = ? AND dm.status = 'pending'
");
$stmt->bind_param('ii', $matchId, $currentUserId);
$stmt->execute();
$match = $stmt->get_result()->fetch_assoc();

if (!$match) {
    jsonResponse(['status' => 'error', 'message' => 'Challenge tidak ditemukan atau sudah tidak aktif'], 404);
}

// Update status jadi accepted
$stmtUpdate = $conn->prepare("
    UPDATE duel_matches SET status = 'accepted', updated_at = NOW() WHERE id = ?
");
$stmtUpdate->bind_param('i', $matchId);
$stmtUpdate->execute();

logActivity($conn, $currentUserId, 'duel.accepted', 'duel_matches', $matchId,
    "Terima duel dari {$match['challenger_name']}");

jsonResponse([
    'status'  => 'success',
    'message' => 'Duel diterima! Selamat bertanding.',
    'match'   => [
        'id'              => $matchId,
        'challenger_name' => $match['challenger_name'],
        'match_date'      => $match['match_date'],
        'status'          => 'accepted',
    ],
]);

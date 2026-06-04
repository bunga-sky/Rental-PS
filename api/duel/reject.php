<?php
// ============================================================
// API: Tolak Duel Challenge
// POST /api/duel/reject.php
// Body: { match_id }
// ============================================================

require_once __DIR__ . '/../../api/middleware.php';
requireMethod('POST');

$body = getJsonBody();
requireFields($body, ['match_id']);

$matchId = (int) $body['match_id'];

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

$stmtUpdate = $conn->prepare("
    UPDATE duel_matches SET status = 'rejected', updated_at = NOW() WHERE id = ?
");
$stmtUpdate->bind_param('i', $matchId);
$stmtUpdate->execute();

// Kurangi match_count di duel_history (karena ditolak)
$weekNumber = (int) date('W');
$stmtHistory = $conn->prepare("
    UPDATE duel_history
    SET match_count = GREATEST(match_count - 1, 0)
    WHERE ((user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?))
      AND week_number = ?
");
$stmtHistory->bind_param('iiiii',
    $currentUserId, $match['challenger_id'],
    $match['challenger_id'], $currentUserId,
    $weekNumber
);
$stmtHistory->execute();

logActivity($conn, $currentUserId, 'duel.rejected', 'duel_matches', $matchId,
    "Tolak duel dari {$match['challenger_name']}");

jsonResponse([
    'status'  => 'success',
    'message' => 'Challenge ditolak.',
]);

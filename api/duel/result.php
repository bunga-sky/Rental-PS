<?php
// ============================================================
// API: Input Hasil Duel
// POST /api/duel/result.php
// Body: { match_id, winner_id }
// ============================================================

require_once __DIR__ . '/../../api/middleware.php';
requireMethod('POST');

$body = getJsonBody();
requireFields($body, ['match_id', 'winner_id']);

$matchId  = (int) $body['match_id'];
$winnerId = (int) $body['winner_id'];

// Ambil match — hanya challenger yang bisa input hasil
$stmt = $conn->prepare("
    SELECT dm.*,
           c.username AS challenger_name,
           o.username AS opponent_name
    FROM duel_matches dm
    JOIN users c ON dm.challenger_id = c.id
    JOIN users o ON dm.opponent_id   = o.id
    WHERE dm.id = ?
      AND dm.challenger_id = ?
      AND dm.status = 'accepted'
");
$stmt->bind_param('ii', $matchId, $currentUserId);
$stmt->execute();
$match = $stmt->get_result()->fetch_assoc();

if (!$match) {
    jsonResponse(['status' => 'error', 'message' => 'Match tidak ditemukan atau belum diterima'], 404);
}

// winner_id harus salah satu dari dua pemain
if ($winnerId !== (int) $match['challenger_id'] && $winnerId !== (int) $match['opponent_id']) {
    jsonResponse(['status' => 'error', 'message' => 'Winner ID tidak valid'], 422);
}

$loserId = ($winnerId === (int) $match['challenger_id'])
    ? (int) $match['opponent_id']
    : (int) $match['challenger_id'];

$conn->begin_transaction();
try {
    // Update match status
    $stmtMatch = $conn->prepare("
        UPDATE duel_matches
        SET status = 'completed', winner_id = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmtMatch->bind_param('ii', $winnerId, $matchId);
    $stmtMatch->execute();

    // Update stats pemenang
    $stmtWin = $conn->prepare("
        UPDATE users SET total_wins = total_wins + 1, total_matches = total_matches + 1
        WHERE id = ?
    ");
    $stmtWin->bind_param('i', $winnerId);
    $stmtWin->execute();

    // Update stats yang kalah
    $stmtLose = $conn->prepare("
        UPDATE users SET total_matches = total_matches + 1 WHERE id = ?
    ");
    $stmtLose->bind_param('i', $loserId);
    $stmtLose->execute();

    $winnerName = ($winnerId === (int) $match['challenger_id'])
        ? $match['challenger_name']
        : $match['opponent_name'];

    logActivity($conn, $currentUserId, 'duel.completed', 'duel_matches', $matchId,
        "Hasil duel: {$winnerName} menang");

    $conn->commit();
    jsonResponse([
        'status'  => 'success',
        'message' => 'Hasil duel berhasil disimpan!',
        'winner'  => [
            'id'       => $winnerId,
            'username' => $winnerName,
        ],
    ]);
} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(['status' => 'error', 'message' => 'Gagal menyimpan hasil duel'], 500);
}

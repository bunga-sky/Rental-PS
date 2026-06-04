<?php
// ============================================================
// USER: Process Duel
// Dipanggil dari duel.php via redirect
// GET /user/process_duel.php?action=accept&id=xxx
// GET /user/process_duel.php?action=reject&id=xxx
// GET /user/process_duel.php?action=result&id=xxx&winner=xxx
// ============================================================

require_once '../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$currentUserId = (int) $_SESSION['user_id'];
$action        = isset($_GET['action']) ? trim($_GET['action']) : '';
$matchId       = isset($_GET['id'])     ? (int) $_GET['id']    : 0;

if (!$matchId || !in_array($action, ['accept', 'reject', 'result'], true)) {
    $_SESSION['duel_error'] = 'Aksi tidak valid';
    header('Location: duel.php');
    exit;
}

// Panggil API endpoint yang sesuai (reuse logic dari api/duel/)
$apiBase = __DIR__ . '/../api/duel/';

switch ($action) {

    // ---- ACCEPT ----
    case 'accept':
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
            $_SESSION['duel_error'] = 'Challenge tidak ditemukan atau sudah tidak aktif';
            break;
        }

        $stmtUp = $conn->prepare("UPDATE duel_matches SET status='accepted', updated_at=NOW() WHERE id=?");
        $stmtUp->bind_param('i', $matchId);
        $stmtUp->execute();

        logActivity($conn, $currentUserId, 'duel.accepted', 'duel_matches', $matchId,
            "Terima duel dari {$match['challenger_name']}");

        $_SESSION['duel_success'] = "Duel diterima! Selamat bertanding lawan {$match['challenger_name']} 🎮";
        break;

    // ---- REJECT ----
    case 'reject':
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
            $_SESSION['duel_error'] = 'Challenge tidak ditemukan';
            break;
        }

        $conn->begin_transaction();
        try {
            $conn->query("UPDATE duel_matches SET status='rejected', updated_at=NOW() WHERE id=$matchId");

            // Rollback match_count di duel_history
            $weekNumber = (int) date('W');
            $stmtHist = $conn->prepare("
                UPDATE duel_history
                SET match_count = GREATEST(match_count - 1, 0)
                WHERE ((user1_id=? AND user2_id=?) OR (user1_id=? AND user2_id=?))
                  AND week_number=?
            ");
            $stmtHist->bind_param('iiiii',
                $currentUserId, $match['challenger_id'],
                $match['challenger_id'], $currentUserId,
                $weekNumber
            );
            $stmtHist->execute();

            logActivity($conn, $currentUserId, 'duel.rejected', 'duel_matches', $matchId,
                "Tolak duel dari {$match['challenger_name']}");

            $conn->commit();
            $_SESSION['duel_success'] = 'Challenge berhasil ditolak.';
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['duel_error'] = 'Gagal menolak challenge';
        }
        break;

    // ---- INPUT RESULT ----
    case 'result':
        $winnerId = isset($_GET['winner']) ? (int) $_GET['winner'] : 0;

        if (!$winnerId) {
            $_SESSION['duel_error'] = 'Winner ID tidak valid';
            break;
        }

        // Ambil match — hanya challenger yang bisa input hasil
        $stmt = $conn->prepare("
            SELECT dm.*,
                   c.username AS challenger_name,
                   o.username AS opponent_name
            FROM duel_matches dm
            JOIN users c ON dm.challenger_id = c.id
            JOIN users o ON dm.opponent_id   = o.id
            WHERE dm.id = ? AND dm.challenger_id = ? AND dm.status = 'accepted'
        ");
        $stmt->bind_param('ii', $matchId, $currentUserId);
        $stmt->execute();
        $match = $stmt->get_result()->fetch_assoc();

        if (!$match) {
            $_SESSION['duel_error'] = 'Match tidak ditemukan atau belum diterima';
            break;
        }

        // Validasi winner harus salah satu pemain
        if ($winnerId !== (int)$match['challenger_id'] && $winnerId !== (int)$match['opponent_id']) {
            $_SESSION['duel_error'] = 'Winner ID tidak valid';
            break;
        }

        $loserId = ($winnerId === (int)$match['challenger_id'])
            ? (int)$match['opponent_id']
            : (int)$match['challenger_id'];

        $winnerName = ($winnerId === (int)$match['challenger_id'])
            ? $match['challenger_name']
            : $match['opponent_name'];

        $conn->begin_transaction();
        try {
            // Update match
            $stmtMatch = $conn->prepare("
                UPDATE duel_matches SET status='completed', winner_id=?, updated_at=NOW() WHERE id=?
            ");
            $stmtMatch->bind_param('ii', $winnerId, $matchId);
            $stmtMatch->execute();

            // Update stats winner
            $stmtWin = $conn->prepare("
                UPDATE users SET total_wins=total_wins+1, total_matches=total_matches+1 WHERE id=?
            ");
            $stmtWin->bind_param('i', $winnerId);
            $stmtWin->execute();

            // Update stats loser
            $stmtLose = $conn->prepare("UPDATE users SET total_matches=total_matches+1 WHERE id=?");
            $stmtLose->bind_param('i', $loserId);
            $stmtLose->execute();

            logActivity($conn, $currentUserId, 'duel.completed', 'duel_matches', $matchId,
                "Hasil duel: $winnerName menang");

            $conn->commit();
            $_SESSION['duel_success'] = "Hasil disimpan! 🏆 <strong>$winnerName</strong> menang!";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['duel_error'] = 'Gagal menyimpan hasil duel';
        }
        break;
}

header('Location: duel.php');
exit;

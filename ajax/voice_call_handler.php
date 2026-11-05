<?php
/**
 * Voice call signaling API: create call, accept/reject, exchange SDP and ICE
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}
$user_id = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOut($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($action) {
        case 'create':
            $callee_id = (int)($_POST['callee_id'] ?? 0);
            if (!$callee_id) { jsonOut(['error' => 'Missing callee user ID']); }
            $room_id = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare("INSERT INTO voice_calls (room_id, caller_id, callee_id, status) VALUES (?, ?, ?, 'pending')");
            $stmt->execute([$room_id, $user_id, $callee_id]);
            jsonOut(['success' => true, 'room_id' => $room_id]);
            break;

        case 'incoming':
            $stmt = $pdo->prepare("SELECT c.room_id, c.caller_id, c.created_at, u.username, u.full_name 
                FROM voice_calls c JOIN users u ON c.caller_id = u.id 
                WHERE c.callee_id = ? AND c.status = 'pending' ORDER BY c.created_at DESC LIMIT 1");
            $stmt->execute([$user_id]);
            $row = $stmt->fetch();
            if (!$row) { jsonOut(['success' => true, 'call' => null]); }
            jsonOut(['success' => true, 'call' => [
                'room_id' => $row['room_id'],
                'caller_id' => $row['caller_id'],
                'caller_name' => $row['full_name'] ?: $row['username'],
                'created_at' => $row['created_at']
            ]]);
            break;

        case 'accept':
            $room_id = trim($_POST['room_id'] ?? '');
            if (!$room_id) { jsonOut(['error' => 'Missing room_id']); }
            $stmt = $pdo->prepare("UPDATE voice_calls SET status = 'accepted' WHERE room_id = ? AND callee_id = ? AND status = 'pending'");
            $stmt->execute([$room_id, $user_id]);
            if ($stmt->rowCount() === 0) { jsonOut(['error' => 'Call does not exist or has expired']); }
            jsonOut(['success' => true]);
            break;

        case 'reject':
            $room_id = trim($_POST['room_id'] ?? '');
            if (!$room_id) { jsonOut(['error' => 'Missing room_id']); }
            $pdo->prepare("UPDATE voice_calls SET status = 'rejected' WHERE room_id = ? AND callee_id = ?")->execute([$room_id, $user_id]);
            jsonOut(['success' => true]);
            break;

        case 'set_offer':
            $room_id = trim($_POST['room_id'] ?? '');
            $sdp = $_POST['sdp'] ?? '';
            if (!$room_id || !$sdp) { jsonOut(['error' => 'Missing room_id or sdp']); }
            $pdo->prepare("UPDATE voice_calls SET offer_sdp = ? WHERE room_id = ? AND caller_id = ?")->execute([$sdp, $room_id, $user_id]);
            jsonOut(['success' => true]);
            break;

        case 'get_offer':
            $room_id = trim($_GET['room_id'] ?? '');
            if (!$room_id) { jsonOut(['error' => 'Missing room_id']); }
            $stmt = $pdo->prepare("SELECT offer_sdp FROM voice_calls WHERE room_id = ? AND callee_id = ? AND status IN ('pending','accepted')");
            $stmt->execute([$room_id, $user_id]);
            $row = $stmt->fetch();
            jsonOut(['success' => true, 'offer' => $row ? $row['offer_sdp'] : null]);
            break;

        case 'set_answer':
            $room_id = trim($_POST['room_id'] ?? '');
            $sdp = $_POST['sdp'] ?? '';
            if (!$room_id || !$sdp) { jsonOut(['error' => 'Missing room_id or sdp']); }
            $pdo->prepare("UPDATE voice_calls SET answer_sdp = ? WHERE room_id = ? AND callee_id = ?")->execute([$sdp, $room_id, $user_id]);
            jsonOut(['success' => true]);
            break;

        case 'get_answer':
            $room_id = trim($_GET['room_id'] ?? '');
            if (!$room_id) { jsonOut(['error' => 'Missing room_id']); }
            $stmt = $pdo->prepare("SELECT answer_sdp FROM voice_calls WHERE room_id = ? AND caller_id = ?");
            $stmt->execute([$room_id, $user_id]);
            $row = $stmt->fetch();
            jsonOut(['success' => true, 'answer' => $row ? $row['answer_sdp'] : null]);
            break;

        case 'add_ice':
            $room_id = trim($_POST['room_id'] ?? '');
            $candidate = $_POST['candidate'] ?? '';
            if (!$room_id || $candidate === '') { jsonOut(['error' => 'Missing room_id or candidate']); }
            $stmt = $pdo->prepare("INSERT INTO voice_call_ice (room_id, user_id, candidate) VALUES (?, ?, ?)");
            $stmt->execute([$room_id, $user_id, $candidate]);
            jsonOut(['success' => true]);
            break;

        case 'get_ice':
            $room_id = trim($_GET['room_id'] ?? '');
            $after_id = (int)($_GET['after_id'] ?? 0);
            if (!$room_id) { jsonOut(['error' => 'Missing room_id']); }
            $stmt = $pdo->prepare("SELECT id, user_id, candidate FROM voice_call_ice WHERE room_id = ? AND user_id != ? AND id > ? ORDER BY id");
            $stmt->execute([$room_id, $user_id, $after_id]);
            $rows = $stmt->fetchAll();
            jsonOut(['success' => true, 'candidates' => $rows]);
            break;

        case 'end':
            $room_id = trim($_POST['room_id'] ?? '');
            if (!$room_id) { jsonOut(['error' => 'Missing room_id']); }
            $pdo->prepare("UPDATE voice_calls SET status = 'ended' WHERE room_id = ? AND (caller_id = ? OR callee_id = ?)")->execute([$room_id, $user_id, $user_id]);
            jsonOut(['success' => true]);
            break;

        case 'get_status':
            $room_id = trim($_GET['room_id'] ?? '');
            if (!$room_id) { jsonOut(['error' => 'Missing room_id']); }
            $stmt = $pdo->prepare("SELECT status FROM voice_calls WHERE room_id = ? AND (caller_id = ? OR callee_id = ?)");
            $stmt->execute([$room_id, $user_id, $user_id]);
            $row = $stmt->fetch();
            jsonOut(['success' => true, 'status' => $row ? $row['status'] : null]);
            break;

        default:
            jsonOut(['error' => 'Unknown action']);
    }
} catch (Exception $e) {
    jsonOut(['error' => $e->getMessage()]);
}

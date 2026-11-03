<?php
// Set UTF-8 encoding
header('Content-Type: application/json; charset=UTF-8');
mb_internal_encoding('UTF-8');

error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/language.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'send_request':
            handleSendFriendRequest($pdo, $user_id);
            break;
        case 'accept_request':
            handleAcceptFriendRequest($pdo, $user_id);
            break;
        case 'reject_request':
            handleRejectFriendRequest($pdo, $user_id);
            break;
        case 'delete_friend':
            handleDeleteFriend($pdo, $user_id);
            break;
        case 'search_friends':
            handleSearchFriends($pdo, $user_id);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

function handleSendFriendRequest($pdo, $user_id) {
    $input = file_get_contents('php://input');
    if (empty($input) && isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
        $input = $GLOBALS['HTTP_RAW_POST_DATA'];
    }
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['username'])) {
        echo json_encode(['success' => false, 'error' => 'Username is required']);
        return;
    }
    
    $friend_username = trim($data['username']);
    
    if ($friend_username === '') {
        echo json_encode(['success' => false, 'error' => 'Username cannot be empty']);
        return;
    }
    
    // Check if friend exists
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ?");
    $stmt->execute([$friend_username]);
    $friend = $stmt->fetch();
    
    if (!$friend) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        return;
    }
    
    $friend_id = $friend['id'];
    
    // Can't add yourself
    if ($friend_id == $user_id) {
        echo json_encode(['success' => false, 'error' => 'Cannot add yourself as friend']);
        return;
    }
    
    // Check if already friends or request exists
    $stmt = $pdo->prepare("
        SELECT * FROM friends 
        WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
    ");
    $stmt->execute([$user_id, $friend_id, $friend_id, $user_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        if ($existing['status'] === 'accepted') {
            echo json_encode(['success' => false, 'error' => 'You are already friends with this user']);
        } else {
            echo json_encode(['success' => false, 'error' => 'A friend request already exists between you and this user']);
        }
        return;
    }
    
    // Send friend request
    $stmt = $pdo->prepare("
        INSERT INTO friends (user_id, friend_id, status, created_at) 
        VALUES (?, ?, 'pending', NOW())
    ");
    
    if ($stmt->execute([$user_id, $friend_id])) {
        echo json_encode(['success' => true, 'message' => 'Friend request sent successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to send friend request']);
    }
}

function handleAcceptFriendRequest($pdo, $user_id) {
    $input = file_get_contents('php://input');
    if (empty($input) && isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
        $input = $GLOBALS['HTTP_RAW_POST_DATA'];
    }
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['friend_id'])) {
        echo json_encode(['success' => false, 'error' => 'Friend ID is required']);
        return;
    }
    
    $friend_id = (int)$data['friend_id'];
    
    try {
        // Start transaction to ensure both operations succeed
        $pdo->beginTransaction();
        
        // Update the friend request to accepted
        $stmt = $pdo->prepare("
            UPDATE friends 
            SET status = 'accepted', updated_at = NOW() 
            WHERE user_id = ? AND friend_id = ? AND status = 'pending'
        ");
        
        if (!$stmt->execute([$friend_id, $user_id])) {
            throw new Exception('Failed to update friend request');
        }
        
        // Create reciprocal friendship if it doesn't exist
        $check_stmt = $pdo->prepare("
            SELECT id FROM friends 
            WHERE user_id = ? AND friend_id = ? AND status = 'accepted'
        ");
        $check_stmt->execute([$user_id, $friend_id]);
        
        if (!$check_stmt->fetch()) {
            $reciprocal_stmt = $pdo->prepare("
                INSERT INTO friends (user_id, friend_id, status, created_at) 
                VALUES (?, ?, 'accepted', NOW())
            ");
            $reciprocal_stmt->execute([$user_id, $friend_id]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Friend request accepted']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Failed to accept friend request: ' . $e->getMessage()]);
    }
}

function handleRejectFriendRequest($pdo, $user_id) {
    $input = file_get_contents('php://input');
    if (empty($input) && isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
        $input = $GLOBALS['HTTP_RAW_POST_DATA'];
    }
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['friend_id'])) {
        echo json_encode(['success' => false, 'error' => 'Friend ID is required']);
        return;
    }
    
    $friend_id = (int)$data['friend_id'];
    
    // Delete the friend request
    $stmt = $pdo->prepare("
        DELETE FROM friends 
        WHERE user_id = ? AND friend_id = ? AND status = 'pending'
    ");
    
    if ($stmt->execute([$friend_id, $user_id])) {
        echo json_encode(['success' => true, 'message' => 'Friend request rejected']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to reject friend request']);
    }
}

function handleDeleteFriend($pdo, $user_id) {
    $input = file_get_contents('php://input');
    if (empty($input) && isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
        $input = $GLOBALS['HTTP_RAW_POST_DATA'];
    }
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['friend_id'])) {
        echo json_encode(['success' => false, 'error' => 'Friend ID is required']);
        return;
    }
    
    $friend_id = (int)$data['friend_id'];
    
    try {
        // Start transaction to ensure both operations succeed
        $pdo->beginTransaction();
        
        // Delete friendship from both directions
        $stmt1 = $pdo->prepare("DELETE FROM friends WHERE user_id = ? AND friend_id = ?");
        $stmt2 = $pdo->prepare("DELETE FROM friends WHERE user_id = ? AND friend_id = ?");
        
        $stmt1->execute([$user_id, $friend_id]);
        $stmt2->execute([$friend_id, $user_id]);
        
        // Also remove from pinned chats if exists
        $pin_stmt = $pdo->prepare("DELETE FROM pinned_chats WHERE user_id = ? AND chat_id = ? AND chat_type = 'user'");
        $pin_stmt->execute([$user_id, $friend_id]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Friend deleted successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Failed to delete friend: ' . $e->getMessage()]);
    }
}

function handleSearchFriends($pdo, $user_id) {
    $search = $_GET['search'] ?? '';
    
    if (empty($search)) {
        echo json_encode(['success' => false, 'error' => 'Search term is required']);
        return;
    }
    
    try {
        // Search for friends by username or full name
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.*, f.status as friendship_status
            FROM friends f 
            JOIN users u ON (
                CASE 
                    WHEN f.user_id = ? THEN f.friend_id = u.id
                    WHEN f.friend_id = ? THEN f.user_id = u.id
                END
            )
            WHERE (f.user_id = ? OR f.friend_id = ?) 
            AND f.status = 'accepted'
            AND (u.username LIKE ? OR u.full_name LIKE ?)
            AND u.id != ?
            ORDER BY u.status DESC, u.full_name ASC
            LIMIT 20
        ");
        
        $searchTerm = '%' . $search . '%';
        $stmt->execute([$user_id, $user_id, $user_id, $user_id, $searchTerm, $searchTerm, $user_id]);
        $friends = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'friends' => $friends]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to search friends: ' . $e->getMessage()]);
    }
}
?>

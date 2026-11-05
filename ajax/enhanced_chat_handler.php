<?php
// Enhanced chat features AJAX handler
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'reply_message':
            replyMessage();
            break;
        case 'forward_message':
            forwardMessage();
            break;
        case 'tag_user':
            tagUser();
            break;
        case 'edit_message':
            editMessage();
            break;
        case 'recall_message':
            recallMessage();
            break;
        case 'pin_message':
            pinMessage();
            break;
        case 'unpin_message':
            unpinMessage();
            break;
        case 'get_message_details':
            getMessageDetails();
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function replyMessage() {
    global $pdo, $user_id;
    
    $message_id = (int)($_POST['message_id'] ?? 0);
    $reply_content = trim($_POST['content'] ?? '');
    $receiver_id = (int)($_POST['receiver_id'] ?? 0);
    $group_id = (int)($_POST['group_id'] ?? 0);
    
    if (!$message_id || !$reply_content) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    // Insert reply message
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, group_id, message, message_type, reply_to) 
        VALUES (?, ?, ?, ?, 'text', ?)
    ");
    $stmt->execute([$user_id, $receiver_id, $group_id > 0 ? $group_id : null, $reply_content, $message_id]);
    
    $reply_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true, 
        'reply_id' => $reply_id,
        'message' => 'Reply sent successfully'
    ]);
}

function forwardMessage() {
    global $pdo, $user_id;
    
    try {
        $message_id = (int)($_POST['message_id'] ?? 0);
        $receiver_id = (int)($_POST['receiver_id'] ?? 0);
        $group_id = (int)($_POST['group_id'] ?? 0);
        
        
        if (!$message_id || ($receiver_id <= 0 && $group_id <= 0)) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            return;
        }
        
        // Get original message
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ?");
        $stmt->execute([$message_id]);
        $original_message = $stmt->fetch();
        
        if (!$original_message) {
            echo json_encode(['success' => false, 'error' => 'Original message not found']);
            return;
        }
        
        // Create forwarded message
        $forwarded_content = "Forwarded: " . $original_message['message'];
        
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, group_id, message, message_type, file_path, forwarded_from) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id, 
            $receiver_id > 0 ? $receiver_id : null, 
            $group_id > 0 ? $group_id : null, 
            $forwarded_content, 
            $original_message['message_type'],
            $original_message['file_path'],
            $message_id
        ]);
        
        $forwarded_id = $pdo->lastInsertId();
        
        // Record forward
        try {
            $stmt = $pdo->prepare("
                INSERT INTO message_forwards (original_message_id, forwarded_message_id, forwarded_by) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$message_id, $forwarded_id, $user_id]);
        } catch (Exception $e) {
            // Continue anyway since the main message was inserted
        }
        
        echo json_encode([
            'success' => true, 
            'forwarded_id' => $forwarded_id,
            'message' => 'Message forwarded successfully'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to forward message: ' . $e->getMessage()
        ]);
    }
}

function tagUser() {
    global $pdo, $user_id;
    
    $message_id = (int)($_POST['message_id'] ?? 0);
    $tagged_username = trim($_POST['tagged_user_id'] ?? '');
    
    if (!$message_id || !$tagged_username) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    // Get user ID from username
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$tagged_username]);
    $tagged_user = $stmt->fetch();
    
    if (!$tagged_user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        return;
    }
    
    $tagged_user_id = $tagged_user['id'];
    
    // Check if already tagged
    $stmt = $pdo->prepare("SELECT id FROM message_tags WHERE message_id = ? AND tagged_user_id = ?");
    $stmt->execute([$message_id, $tagged_user_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'User already tagged']);
        return;
    }
    
    // Add tag
    $stmt = $pdo->prepare("
        INSERT INTO message_tags (message_id, tagged_user_id) 
        VALUES (?, ?)
    ");
    $stmt->execute([$message_id, $tagged_user_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'User tagged successfully'
    ]);
}

function editMessage() {
    global $pdo, $user_id;
    
    $message_id = (int)($_POST['message_id'] ?? 0);
    $new_content = trim($_POST['content'] ?? '');
    
    if (!$message_id || !$new_content) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    // Check if user owns the message
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ? AND sender_id = ?");
    $stmt->execute([$message_id, $user_id]);
    $message = $stmt->fetch();
    
    if (!$message) {
        echo json_encode(['success' => false, 'error' => 'Message not found or not owned by user']);
        return;
    }
    
    // Check if within 2 minutes
    $message_time = strtotime($message['created_at']);
    $current_time = time();
    if (($current_time - $message_time) > 120) { // 2 minutes = 120 seconds
        echo json_encode(['success' => false, 'error' => 'Cannot edit message after 2 minutes']);
        return;
    }
    
    // Record edit history
    $stmt = $pdo->prepare("
        INSERT INTO message_edits (message_id, old_content, new_content) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$message_id, $message['message'], $new_content]);
    
    // Update message
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET message = ?, is_edited = TRUE, edited_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$new_content, $message_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Message edited successfully'
    ]);
}

function recallMessage() {
    global $pdo, $user_id;
    
    $message_id = (int)($_POST['message_id'] ?? 0);
    
    if (!$message_id) {
        echo json_encode(['success' => false, 'error' => 'Missing message ID']);
        return;
    }
    
    // Check if user owns the message
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ? AND sender_id = ?");
    $stmt->execute([$message_id, $user_id]);
    $message = $stmt->fetch();
    
    if (!$message) {
        echo json_encode(['success' => false, 'error' => 'Message not found or not owned by user']);
        return;
    }
    
    // Check if within 2 minutes
    $message_time = strtotime($message['created_at']);
    $current_time = time();
    if (($current_time - $message_time) > 120) { // 2 minutes = 120 seconds
        echo json_encode(['success' => false, 'error' => 'Cannot recall message after 2 minutes']);
        return;
    }
    
    // Update message
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET is_recalled = TRUE, recalled_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$message_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Message recalled successfully'
    ]);
}

function pinMessage() {
    global $pdo, $user_id;
    
    $message_id = (int)($_POST['message_id'] ?? 0);
    
    if (!$message_id) {
        echo json_encode(['success' => false, 'error' => 'Missing message ID']);
        return;
    }
    
    // Update message
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET is_pinned = TRUE, pinned_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$message_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Message pinned successfully'
    ]);
}

function unpinMessage() {
    global $pdo, $user_id;
    
    $message_id = (int)($_POST['message_id'] ?? 0);
    
    if (!$message_id) {
        echo json_encode(['success' => false, 'error' => 'Missing message ID']);
        return;
    }
    
    // Update message
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET is_pinned = FALSE, pinned_at = NULL 
        WHERE id = ?
    ");
    $stmt->execute([$message_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Message unpinned successfully'
    ]);
}

function getMessageDetails() {
    global $pdo, $user_id;
    
    $message_id = (int)($_GET['message_id'] ?? 0);
    
    if (!$message_id) {
        echo json_encode(['success' => false, 'error' => 'Missing message ID']);
        return;
    }
    
    // Get message with sender info
    $stmt = $pdo->prepare("
        SELECT m.*, u.username, u.full_name, u.avatar 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE m.id = ?
    ");
    $stmt->execute([$message_id]);
    $message = $stmt->fetch();
    
    if (!$message) {
        echo json_encode(['success' => false, 'error' => 'Message not found']);
        return;
    }
    
    // Get tags
    $stmt = $pdo->prepare("
        SELECT u.username, u.full_name 
        FROM message_tags mt 
        JOIN users u ON mt.tagged_user_id = u.id 
        WHERE mt.message_id = ?
    ");
    $stmt->execute([$message_id]);
    $tags = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'tags' => $tags
    ]);
}
?>

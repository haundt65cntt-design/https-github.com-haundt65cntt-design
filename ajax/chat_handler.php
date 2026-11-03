<?php
// Set UTF-8 encoding
header('Content-Type: application/json; charset=UTF-8');
mb_internal_encoding('UTF-8');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output, but log them

// Start output buffering to catch any unwanted output
ob_start();

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/language.php';

// Function to clean output and send JSON response
function sendJsonResponse($data) {
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set proper headers
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    
    // Send JSON response
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    sendJsonResponse(['success' => false, 'error' => 'Unauthorized']);
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

switch ($action) {
    case 'send_message':
        handleSendMessage($pdo, $user_id);
        break;
    case 'get_messages':
        handleGetMessages($pdo, $user_id);
        break;
    case 'get_all_messages':
        handleGetAllMessages($pdo, $user_id);
        break;
    case 'add_friend':
        handleAddFriend($pdo, $user_id);
        break;
    case 'respond_request':
        handleRespondRequest($pdo, $user_id);
        break;
    case 'create_group':
        handleCreateGroup($pdo, $user_id);
        break;
    case 'block_user':
        handleBlockUser($pdo, $user_id);
        break;
    case 'edit_message':
        handleEditMessage($pdo, $user_id);
        break;
    case 'delete_message':
        handleDeleteMessage($pdo, $user_id);
        break;
    case 'pin_message':
        handlePinMessage($pdo, $user_id);
        break;
    case 'forward_message':
        handleForwardMessage($pdo, $user_id);
        break;
    case 'reply_message':
        handleReplyMessage($pdo, $user_id);
        break;
    case 'upload_file':
        handleUploadFile($pdo, $user_id);
        break;
    case 'send_file_message':
        handleSendFileMessage($pdo, $user_id);
        break;
    case 'record_voice':
        handleRecordVoice($pdo, $user_id);
        break;
    case 'get_users':
        handleGetUsers($pdo, $user_id);
        break;
    case 'pin_chat':
        handlePinChat($pdo, $user_id);
        break;
    case 'get_user_profile':
        handleGetUserProfile($pdo, $user_id);
        break;
    default:
        sendJsonResponse(['error' => 'Invalid action']);
        break;
}

function chatHandlerHasColumn($pdo, $table, $column) {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare("\n        SELECT COUNT(*)\n        FROM information_schema.COLUMNS\n        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?\n    ");
    $stmt->execute([$table, $column]);
    $cache[$key] = ((int)$stmt->fetchColumn() > 0);
    return $cache[$key];
}

function handleSendMessage($pdo, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        sendJsonResponse(['success' => false, 'error' => 'Dữ liệu gửi lên không hợp lệ.']);
    }

    $receiver_id = isset($data['receiver_id']) ? (int)$data['receiver_id'] : 0;
    $group_id = isset($data['group_id']) ? (int)$data['group_id'] : 0;
    $message = trim((string)($data['message'] ?? ''));
    $message_type = (string)($data['message_type'] ?? 'text');
    $file_path = isset($data['file_path']) ? trim((string)$data['file_path']) : null;
    $reply_to = !empty($data['reply_to']) ? (int)$data['reply_to'] : null;
    $forwarded_from = !empty($data['forwarded_from']) ? (int)$data['forwarded_from'] : null;

    if (($receiver_id > 0) === ($group_id > 0)) {
        sendJsonResponse(['success' => false, 'error' => 'Vui lòng chọn đúng một người nhận hoặc một nhóm chat.']);
    }
    if (!in_array($message_type, ['text', 'image', 'file', 'voice', 'video'], true)) {
        sendJsonResponse(['success' => false, 'error' => 'Loại tin nhắn không hợp lệ.']);
    }
    if ($message_type === 'text' && $message === '') {
        sendJsonResponse(['success' => false, 'error' => 'Tin nhắn không được để trống.']);
    }
    if ($message_type !== 'text' && $message === '' && empty($file_path)) {
        sendJsonResponse(['success' => false, 'error' => 'Tin nhắn đính kèm không có nội dung hoặc tệp.']);
    }

    try {
        if ($receiver_id > 0) {
            if ($receiver_id === (int)$user_id) {
                sendJsonResponse(['success' => false, 'error' => 'Bạn không thể gửi tin nhắn cho chính mình.']);
            }
            $targetStmt = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
            $targetStmt->execute([$receiver_id]);
            if (!$targetStmt->fetch()) {
                sendJsonResponse(['success' => false, 'error' => 'Không tìm thấy người nhận.']);
            }
        } else {
            $memberStmt = $pdo->prepare('SELECT id FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1');
            $memberStmt->execute([$group_id, (int)$user_id]);
            if (!$memberStmt->fetch()) {
                sendJsonResponse(['success' => false, 'error' => 'Bạn không phải thành viên của nhóm này.']);
            }
        }

        // Build the INSERT from columns available in the user's existing database.
        // This keeps text chat working across the original and slimmed SQL schemas.
        $fields = ['sender_id', 'receiver_id', 'group_id', 'message', 'message_type'];
        $values = [
            (int)$user_id,
            $receiver_id > 0 ? $receiver_id : null,
            $group_id > 0 ? $group_id : null,
            $message,
            $message_type
        ];

        $optionalValues = [
            'file_path' => $file_path,
            'reply_to' => $reply_to,
            'forwarded_from' => $forwarded_from
        ];
        foreach ($optionalValues as $column => $value) {
            if (chatHandlerHasColumn($pdo, 'messages', $column)) {
                $fields[] = $column;
                $values[] = $value;
            }
        }

        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $fieldList = implode(', ', array_map(function ($field) { return '`' . $field . '`'; }, $fields));
        $insert = $pdo->prepare('INSERT INTO messages (' . $fieldList . ') VALUES (' . $placeholders . ')');
        $insert->execute($values);
        $message_id = (int)$pdo->lastInsertId();

        // Mentions are optional and must not prevent a message from being sent.
        try {
            if ($message_type === 'text'
                && chatHandlerHasColumn($pdo, 'user_mentions', 'message_id')
                && chatHandlerHasColumn($pdo, 'user_mentions', 'mentioned_user_id')
                && preg_match_all('/@([\w]+)/u', $message, $matches)) {
                foreach (array_unique($matches[1]) as $mentionedUsername) {
                    $userStmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
                    $userStmt->execute([$mentionedUsername]);
                    $mentioned = $userStmt->fetch(PDO::FETCH_ASSOC);
                    if ($mentioned) {
                        $mentionStmt = $pdo->prepare('INSERT INTO user_mentions (message_id, mentioned_user_id) VALUES (?, ?)');
                        $mentionStmt->execute([$message_id, (int)$mentioned['id']]);
                    }
                }
            }
        } catch (Throwable $mentionError) {
            error_log('[chat mention] ' . $mentionError->getMessage());
        }

        sendJsonResponse(['success' => true, 'message_id' => $message_id]);
    } catch (Throwable $e) {
        error_log('[send message] ' . $e->getMessage());
        sendJsonResponse(['success' => false, 'error' => 'Không thể gửi tin nhắn: ' . $e->getMessage()]);
    }
}

function handleGetMessages($pdo, $user_id) {
    $receiver_id = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : 0;
    $group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $limit = max(1, min($limit, 100));
    $offset = max(0, $offset);

    if (($receiver_id > 0) === ($group_id > 0)) {
        sendJsonResponse(['success' => false, 'error' => 'Không xác định được cuộc trò chuyện.']);
    }

    try {
        if ($group_id > 0) {
            $memberCheck = $pdo->prepare('SELECT id FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1');
            $memberCheck->execute([$group_id, (int)$user_id]);
            if (!$memberCheck->fetch()) {
                sendJsonResponse(['success' => false, 'error' => 'Bạn không phải thành viên của nhóm này.']);
            }
            $whereClause = 'm.group_id = ?';
            $params = [$group_id];
        } else {
            if ($receiver_id === (int)$user_id) {
                sendJsonResponse(['success' => false, 'error' => 'Cuộc trò chuyện không hợp lệ.']);
            }
            $targetCheck = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
            $targetCheck->execute([$receiver_id]);
            if (!$targetCheck->fetch()) {
                sendJsonResponse(['success' => false, 'error' => 'Không tìm thấy người dùng này.']);
            }
            $whereClause = '((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))';
            $params = [(int)$user_id, $receiver_id, $receiver_id, (int)$user_id];
        }

        // Select only known core fields, then add optional fields when present.
        // Avoids failures when an older XAMPP database has fewer message columns.
        $select = [
            'm.id', 'm.sender_id', 'm.receiver_id', 'm.group_id',
            'm.message', 'm.message_type', 'm.created_at'
        ];
        $optionalMessageColumns = [
            'file_path', 'file_name', 'file_size', 'reply_to', 'forwarded_from',
            'is_edited', 'edited_at', 'is_recalled', 'recalled_at', 'is_pinned', 'pinned_at'
        ];
        foreach ($optionalMessageColumns as $column) {
            if (chatHandlerHasColumn($pdo, 'messages', $column)) {
                $select[] = 'm.`' . $column . '`';
            } else {
                $select[] = 'NULL AS `' . $column . '`';
            }
        }

        $hasFullName = chatHandlerHasColumn($pdo, 'users', 'full_name');
        $hasAvatar = chatHandlerHasColumn($pdo, 'users', 'avatar');
        $select[] = $hasFullName ? 'u.full_name AS sender_full_name' : 'u.username AS sender_full_name';
        $select[] = 'u.username AS sender_username';
        $select[] = $hasAvatar ? 'u.avatar AS sender_avatar' : 'NULL AS sender_avatar';

        $joins = ' INNER JOIN users u ON u.id = m.sender_id ';
        $hasReplyTo = chatHandlerHasColumn($pdo, 'messages', 'reply_to');
        if ($hasReplyTo) {
            $joins .= ' LEFT JOIN messages rm ON rm.id = m.reply_to LEFT JOIN users r ON r.id = rm.sender_id ';
            $select[] = 'r.username AS reply_username';
            $select[] = $hasFullName ? 'r.full_name AS reply_full_name' : 'r.username AS reply_full_name';
            $select[] = 'rm.message AS reply_content';
            $select[] = 'rm.message_type AS reply_message_type';
            $select[] = chatHandlerHasColumn($pdo, 'messages', 'file_path') ? 'rm.file_path AS reply_file_path' : 'NULL AS reply_file_path';
        } else {
            $select[] = 'NULL AS reply_username';
            $select[] = 'NULL AS reply_full_name';
            $select[] = 'NULL AS reply_content';
            $select[] = 'NULL AS reply_message_type';
            $select[] = 'NULL AS reply_file_path';
        }

        $hasForwardedFrom = chatHandlerHasColumn($pdo, 'messages', 'forwarded_from');
        if ($hasForwardedFrom) {
            $joins .= ' LEFT JOIN messages fm ON fm.id = m.forwarded_from LEFT JOIN users f ON f.id = fm.sender_id ';
            $select[] = 'f.username AS forward_username';
            $select[] = $hasFullName ? 'f.full_name AS forward_full_name' : 'f.username AS forward_full_name';
        } else {
            $select[] = 'NULL AS forward_username';
            $select[] = 'NULL AS forward_full_name';
        }

        $recallFilter = chatHandlerHasColumn($pdo, 'messages', 'is_recalled')
            ? ' AND COALESCE(m.is_recalled, 0) = 0' : '';
        $pinOrder = chatHandlerHasColumn($pdo, 'messages', 'is_pinned')
            ? 'COALESCE(m.is_pinned, 0) DESC, ' : '';

        $sql = 'SELECT ' . implode(', ', $select)
            . ' FROM messages m ' . $joins
            . ' WHERE ' . $whereClause . $recallFilter
            . ' ORDER BY ' . $pinOrder . 'm.created_at ASC, m.id ASC'
            . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendJsonResponse(['success' => true, 'messages' => $messages]);
    } catch (Throwable $e) {
        error_log('[get messages] ' . $e->getMessage());
        sendJsonResponse(['success' => false, 'error' => 'Không thể tải tin nhắn: ' . $e->getMessage()]);
    }
}

function handleAddFriend($pdo, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $friend_username = trim($data['username'] ?? '');
    
    if (empty($friend_username)) {
        echo json_encode(['error' => 'Username is required']);
        return;
    }
    
    try {
        // Check if user exists
        $user_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $user_stmt->execute([$friend_username]);
        $friend = $user_stmt->fetch();
        
        if (!$friend) {
            echo json_encode(['error' => 'User not found']);
            return;
        }
        
        $friend_id = $friend['id'];
        
        if ($friend_id == $user_id) {
            echo json_encode(['error' => 'Cannot add yourself as friend']);
            return;
        }
        
        // Check if friendship already exists (both directions)
        $check_stmt = $pdo->prepare("
            SELECT status FROM friends 
            WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
        ");
        $check_stmt->execute([$user_id, $friend_id, $friend_id, $user_id]);
        $existing = $check_stmt->fetch();
        
        if ($existing) {
            if ($existing['status'] === 'accepted') {
                echo json_encode(['error' => 'You are already friends with this user']);
            } else {
                echo json_encode(['error' => 'A friend request already exists between you and this user']);
            }
            return;
        }
        
        // Create friend request
        $stmt = $pdo->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$user_id, $friend_id]);
        
        echo json_encode(['success' => true, 'message' => 'Friend request sent']);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to send friend request: ' . $e->getMessage()]);
    }
}

function handleRespondRequest($pdo, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $request_id = $data['request_id'] ?? null;
    $action = $data['action'] ?? ''; // 'accept' or 'reject'
    
    if (!$request_id || !in_array($action, ['accept', 'reject'])) {
        echo json_encode(['error' => 'Invalid request']);
        return;
    }
    
    try {
        if ($action === 'accept') {
            // Update the friend request to accepted
            $stmt = $pdo->prepare("UPDATE friends SET status = 'accepted' WHERE id = ? AND friend_id = ?");
            $stmt->execute([$request_id, $user_id]);
            
            // Create reciprocal friendship
            $friend_stmt = $pdo->prepare("SELECT user_id FROM friends WHERE id = ?");
            $friend_stmt->execute([$request_id]);
            $friend_data = $friend_stmt->fetch();
            
            if ($friend_data) {
                $reciprocal_stmt = $pdo->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'accepted')");
                $reciprocal_stmt->execute([$user_id, $friend_data['user_id']]);
            }
        } else {
            // Reject the request
            $stmt = $pdo->prepare("DELETE FROM friends WHERE id = ? AND friend_id = ?");
            $stmt->execute([$request_id, $user_id]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Request ' . $action . 'ed']);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to respond to request: ' . $e->getMessage()]);
    }
}

function handleCreateGroup($pdo, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    
    if (empty($name)) {
        echo json_encode(['error' => 'Group name is required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Create group
        $stmt = $pdo->prepare("INSERT INTO `groups` (name, description, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description, $user_id]);
        $group_id = $pdo->lastInsertId();
        
        // Add creator as admin
        $member_stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'admin')");
        $member_stmt->execute([$group_id, $user_id]);
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'group_id' => $group_id, 'message' => 'Group created successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Failed to create group: ' . $e->getMessage()]);
    }
}

function handleBlockUser($pdo, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $blocked_user_id = $data['user_id'] ?? null;
    
    if (!$blocked_user_id) {
        echo json_encode(['error' => 'User ID is required']);
        return;
    }
    
    try {
        // Update friendship status to blocked
        $stmt = $pdo->prepare("UPDATE friends SET status = 'blocked' WHERE user_id = ? AND friend_id = ?");
        $stmt->execute([$user_id, $blocked_user_id]);
        
        echo json_encode(['success' => true, 'message' => 'User blocked']);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to block user: ' . $e->getMessage()]);
    }
}

function handleEditMessage($pdo, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $message_id = $data['message_id'] ?? null;
    $new_message = trim($data['message'] ?? '');
    
    if (!$message_id || empty($new_message)) {
        echo json_encode(['error' => 'Message ID and content are required']);
        return;
    }
    
    try {
        // Check if message belongs to user and is within 2 minutes
        $check_stmt = $pdo->prepare("
            SELECT created_at FROM messages 
            WHERE id = ? AND sender_id = ? AND is_recalled = FALSE
        ");
        $check_stmt->execute([$message_id, $user_id]);
        $message = $check_stmt->fetch();
        
        if (!$message) {
            echo json_encode(['error' => 'Message not found or not authorized']);
            return;
        }
        
        $created_time = strtotime($message['created_at']);
        $current_time = time();
        
        if (($current_time - $created_time) > 120) { // 2 minutes
            echo json_encode(['error' => 'Cannot edit message after 2 minutes']);
            return;
        }
        
        // Update message
        $stmt = $pdo->prepare("UPDATE messages SET message = ?, is_edited = TRUE, edited_at = NOW() WHERE id = ?");
        $stmt->execute([$new_message, $message_id]);
        
        echo json_encode(['success' => true, 'message' => 'Message edited successfully']);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to edit message: ' . $e->getMessage()]);
    }
}

function handleDeleteMessage($pdo, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $message_id = $data['message_id'] ?? null;
    
    if (!$message_id) {
        echo json_encode(['error' => 'Message ID is required']);
        return;
    }
    
    try {
        // Check if message belongs to user and is within 2 minutes
        $check_stmt = $pdo->prepare("
            SELECT created_at FROM messages 
            WHERE id = ? AND sender_id = ? AND is_recalled = FALSE
        ");
        $check_stmt->execute([$message_id, $user_id]);
        $message = $check_stmt->fetch();
        
        if (!$message) {
            echo json_encode(['error' => 'Message not found or not authorized']);
            return;
        }
        
        $created_time = strtotime($message['created_at']);
        $current_time = time();
        
        if (($current_time - $created_time) > 120) { // 2 minutes
            echo json_encode(['error' => 'Cannot delete message after 2 minutes']);
            return;
        }
        
        // Mark message as recalled
        $stmt = $pdo->prepare("UPDATE messages SET is_recalled = TRUE, recalled_at = NOW() WHERE id = ?");
        $stmt->execute([$message_id]);
        
        echo json_encode(['success' => true, 'message' => 'Message deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to delete message: ' . $e->getMessage()]);
    }
}

function handlePinMessage($pdo, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $message_id = $data['message_id'] ?? null;
    
    if (!$message_id) {
        echo json_encode(['error' => 'Message ID is required']);
        return;
    }
    
    try {
        // Check if already pinned
        $check_stmt = $pdo->prepare("SELECT id FROM pinned_messages WHERE message_id = ? AND user_id = ?");
        $check_stmt->execute([$message_id, $user_id]);
        
        if ($check_stmt->fetch()) {
            // Unpin
            $stmt = $pdo->prepare("DELETE FROM pinned_messages WHERE message_id = ? AND user_id = ?");
            $stmt->execute([$message_id, $user_id]);
            echo json_encode(['success' => true, 'message' => 'Message unpinned']);
        } else {
            // Pin
            $stmt = $pdo->prepare("INSERT INTO pinned_messages (message_id, user_id) VALUES (?, ?)");
            $stmt->execute([$message_id, $user_id]);
            echo json_encode(['success' => true, 'message' => 'Message pinned']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to pin message: ' . $e->getMessage()]);
    }
}

function handleForwardMessage($pdo, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $message_id = $data['message_id'] ?? null;
    $receiver_id = $data['receiver_id'] ?? null;
    $group_id = $data['group_id'] ?? null;
    
    if (!$message_id || (!$receiver_id && !$group_id)) {
        echo json_encode(['error' => 'Message ID and recipient are required']);
        return;
    }
    
    try {
        // Get original message
        $msg_stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ?");
        $msg_stmt->execute([$message_id]);
        $original_message = $msg_stmt->fetch();
        
        if (!$original_message) {
            echo json_encode(['error' => 'Original message not found']);
            return;
        }
        
        // Create forwarded message
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, group_id, message, message_type, file_path, forwarded_from) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id, 
            $receiver_id, 
            $group_id, 
            $original_message['message'], 
            $original_message['message_type'], 
            $original_message['file_path'], 
            $message_id
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Message forwarded']);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to forward message: ' . $e->getMessage()]);
    }
}

function handleReplyMessage($pdo, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $reply_to = $data['reply_to'] ?? null;
    $receiver_id = $data['receiver_id'] ?? null;
    $group_id = $data['group_id'] ?? null;
    $message = trim($data['message'] ?? '');
    
    if (!$reply_to || empty($message) || (!$receiver_id && !$group_id)) {
        echo json_encode(['error' => 'Reply to, message content and recipient are required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, group_id, message, reply_to) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $receiver_id, $group_id, $message, $reply_to]);
        
        echo json_encode(['success' => true, 'message' => 'Reply sent']);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to send reply: ' . $e->getMessage()]);
    }
}

function handleUploadFile($pdo, $user_id) {
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // Don't display errors in output, but log them
    
    // Add a small delay to prevent overwhelming the server
    usleep(100000); // 0.1 second delay
    
    // Check for concurrent uploads from the same user (simple rate limiting)
    $upload_key = "upload_" . $user_id;
    if (!isset($_SESSION[$upload_key])) {
        $_SESSION[$upload_key] = 0;
    }
    
    // Allow max 5 concurrent uploads per user
    if ($_SESSION[$upload_key] >= 5) {
        error_log("Too many concurrent uploads for user: " . $user_id);
        sendJsonResponse(['success' => false, 'error' => 'Too many concurrent uploads. Please wait a moment.']);
        return;
    }
    
    $_SESSION[$upload_key]++;
    
    // Log the request
    error_log("Upload request received. User ID: " . $user_id . ", Concurrent uploads: " . $_SESSION[$upload_key]);
    error_log("FILES array: " . print_r($_FILES, true));
    error_log("POST array: " . print_r($_POST, true));
    
    if (!isset($_FILES['file'])) {
        error_log("No file uploaded");
        sendJsonResponse(['success' => false, 'error' => 'No file uploaded']);
        return;
    }
    
    $file = $_FILES['file'];
    $max_size = 1024 * 1024 * 1024; // 1GB for large files like ZIP
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $error_message = $error_messages[$file['error']] ?? 'Unknown upload error';
        sendJsonResponse(['error' => 'Upload error: ' . $error_message]);
        return;
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        sendJsonResponse(['error' => 'File size too large. Maximum size is 1GB.']);
        return;
    }
    
    // Define allowed file types - More comprehensive list
    $allowed_types = [
        // Images
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml', 'image/tiff', 'image/x-icon',
        // Documents
        'application/pdf', 
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.text', 'application/vnd.oasis.opendocument.spreadsheet', 'application/vnd.oasis.opendocument.presentation',
        // Text files
        'text/plain', 'text/csv', 'text/html', 'text/css', 'text/javascript', 'application/json', 'text/xml', 'application/xml',
        'text/markdown', 'text/rtf', 'application/rtf',
        // Archives
        'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed', 'application/x-tar', 'application/gzip',
        'application/x-bzip2', 'application/x-compress', 'application/x-lzma', 'application/x-lz4',
        // Audio
        'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/webm', 'audio/mp4', 'audio/aac', 'audio/flac', 'audio/x-m4a',
        'audio/x-ms-wma', 'audio/x-wav', 'audio/mp3', 'audio/wave',
        // Video
        'video/mp4', 'video/avi', 'video/mov', 'video/wmv', 'video/flv', 'video/webm', 'video/quicktime',
        'video/x-msvideo', 'video/x-flv', 'video/x-ms-wmv', 'video/3gpp', 'video/x-matroska',
        // Other common formats
        'application/octet-stream', 'application/x-msdownload', 'application/x-executable',
        'application/x-shockwave-flash', 'application/x-director', 'application/x-ms-wmz',
        // Database files
        'application/x-sqlite3', 'application/vnd.ms-access',
        // CAD files
        'application/dwg', 'application/x-autocad',
        // Font files
        'font/ttf', 'font/otf', 'application/font-woff', 'application/font-woff2',
        // Generic types
        'application/force-download', 'application/unknown'
    ];
    
    // Also check by file extension as fallback
    $allowed_extensions = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'tiff', 'ico',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
        'txt', 'csv', 'html', 'htm', 'css', 'js', 'json', 'xml', 'md', 'rtf',
        'zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'lzma', 'lz4',
        'mp3', 'wav', 'ogg', 'webm', 'mp4', 'aac', 'flac', 'm4a', 'wma',
        'avi', 'mov', 'wmv', 'flv', 'mkv', '3gp', 'm4v',
        'exe', 'msi', 'deb', 'rpm', 'dmg', 'iso', 'bin',
        'db', 'sqlite', 'sqlite3', 'mdb', 'accdb',
        'dwg', 'dxf', 'cad',
        'ttf', 'otf', 'woff', 'woff2',
        'swf', 'fla', 'psd', 'ai', 'eps', 'sketch'
    ];
    
    // Check MIME type first
    if (in_array($file['type'], $allowed_types)) {
        // MIME type is allowed, continue
    } else {
        // Check file extension as fallback
        $fileName = strtolower($file['name']);
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        
        if (in_array($fileExtension, $allowed_extensions)) {
            // File extension is allowed, continue
        } else {
            sendJsonResponse(['error' => 'File type not allowed. Supported formats: images, documents, text files, archives, audio, video, and many other common file types. Please try a different file or contact support if you need to upload a specific file type.']);
            return;
        }
    }
    
    // Determine upload directory based on file type
    $upload_dir = 'uploads/';
    if (strpos($file['type'], 'image/') === 0) {
        $upload_dir = 'uploads/images/';
    } elseif (strpos($file['type'], 'audio/') === 0) {
        $upload_dir = 'uploads/voice/';
    } elseif (strpos($file['type'], 'video/') === 0) {
        $upload_dir = 'uploads/videos/';
    } elseif (in_array($file['type'], ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'text/plain', 'text/csv', 'text/html', 'text/css', 'text/javascript', 'application/json'])) {
        $upload_dir = 'uploads/files/';
    } elseif (in_array($file['type'], ['application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed', 'application/x-tar', 'application/gzip'])) {
        $upload_dir = 'uploads/archives/';
    } else {
        $upload_dir = 'uploads/files/';
    }
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = uniqid() . '_' . time() . '.' . $file_extension;
    $file_path = rtrim($upload_dir, '/\\') . '/' . $file_name;
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        error_log("File moved successfully to: " . $file_path);
        // Verify file was actually saved
        if (file_exists($file_path)) {
            error_log("File exists at target location. Size: " . filesize($file_path));
            // Decrease concurrent upload counter
            $_SESSION[$upload_key] = max(0, $_SESSION[$upload_key] - 1);
            sendJsonResponse([
                'success' => true, 
                'file_path' => $file_path, 
                'file_name' => $file['name'],
                'file_size' => $file['size'],
                'file_type' => $file['type'],
                'actual_size' => filesize($file_path)
            ]);
        } else {
            error_log("File was moved but does not exist at target location");
            // Decrease concurrent upload counter
            $_SESSION[$upload_key] = max(0, $_SESSION[$upload_key] - 1);
            sendJsonResponse(['success' => false, 'error' => 'File was moved but does not exist at target location']);
        }
    } else {
        error_log("Failed to move uploaded file");
        // Decrease concurrent upload counter
        $_SESSION[$upload_key] = max(0, $_SESSION[$upload_key] - 1);
        // Move uploaded file failed, get detailed error information
        $error_info = [
            'tmp_name' => $file['tmp_name'],
            'target_path' => $file_path,
            'is_uploaded_file' => is_uploaded_file($file['tmp_name']),
            'file_exists' => file_exists($file['tmp_name']),
            'target_dir_writable' => is_writable(dirname($file_path)),
            'target_dir_exists' => is_dir(dirname($file_path))
        ];
        error_log("Upload error details: " . json_encode($error_info));
        sendJsonResponse(['success' => false, 'error' => 'Failed to upload file. Debug info: ' . json_encode($error_info)]);
    }
}

function handleSendFileMessage($pdo, $user_id) {
    $input = file_get_contents('php://input');
    if (empty($input) && isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
        $input = $GLOBALS['HTTP_RAW_POST_DATA'];
    }
    $data = json_decode($input, true);
    
    $receiver_id = $data['receiver_id'] ?? null;
    $group_id = $data['group_id'] ?? null;
    $file_path = $data['file_path'] ?? '';
    $file_name = $data['file_name'] ?? '';
    $file_type = $data['file_type'] ?? 'file';
    
    if (empty($file_path)) {
        sendJsonResponse(['error' => 'File path is required']);
        return;
    }
    
    // Verify that the file actually exists before creating a database record
    if (!file_exists($file_path)) {
        sendJsonResponse(['error' => 'File does not exist: ' . $file_path]);
        return;
    }
    
    if (!$receiver_id && !$group_id) {
        sendJsonResponse(['error' => 'No recipient specified']);
        return;
    }
    
    try {
        // Determine message type based on file type
        $message_type = 'file';
        if (strpos($file_type, 'image/') === 0) {
            $message_type = 'image';
        }
        
        // Create message content based on file type
        $message_content = '';
        if ($message_type === 'image') {
            $message_content = '[Image]';
        } else {
            $message_content = '[File] ' . $file_name;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, group_id, message, message_type, file_path, file_name, file_size) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $receiver_id, $group_id, $message_content, $message_type, $file_path, $file_name, filesize($file_path)]);
        
        $message_id = $pdo->lastInsertId();
        
        sendJsonResponse(['success' => true, 'message_id' => $message_id]);
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to send file message: ' . $e->getMessage()]);
    }
}

function handleRecordVoice($pdo, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $audio_data = $data['audio_data'] ?? '';
    
    if (empty($audio_data)) {
        echo json_encode(['error' => 'No audio data']);
        return;
    }
    
    $upload_dir = 'uploads/voice/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_name = uniqid() . '_voice.webm';
    $file_path = $upload_dir . $file_name;
    
    if (file_put_contents($file_path, base64_decode($audio_data))) {
        echo json_encode(['success' => true, 'file_path' => $file_path]);
    } else {
        echo json_encode(['error' => 'Failed to save voice message']);
    }
}

function handleGetUsers($pdo, $user_id) {
    $search = $_GET['search'] ?? '';
    
    try {
        $where_clause = "WHERE id != ?";
        $params = [$user_id];
        
        if (!empty($search)) {
            $where_clause .= " AND (username LIKE ? OR full_name LIKE ?)";
            $search_term = "%$search%";
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $stmt = $pdo->prepare("
            SELECT id, username, full_name, avatar, status 
            FROM users 
            $where_clause 
            ORDER BY full_name ASC 
            LIMIT 20
        ");
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'users' => $users]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to get users: ' . $e->getMessage()]);
    }
}

function handlePinChat($pdo, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $chat_id = $data['chat_id'] ?? null;
    $chat_type = $data['chat_type'] ?? '';
    
    if (!$chat_id || !in_array($chat_type, ['user', 'group'])) {
        echo json_encode(['error' => 'Chat ID and type are required']);
        return;
    }
    
    try {
        // Check if already pinned
        $check_stmt = $pdo->prepare("SELECT id FROM pinned_chats WHERE user_id = ? AND chat_id = ? AND chat_type = ?");
        $check_stmt->execute([$user_id, $chat_id, $chat_type]);
        
        if ($check_stmt->fetch()) {
            // Unpin
            $stmt = $pdo->prepare("DELETE FROM pinned_chats WHERE user_id = ? AND chat_id = ? AND chat_type = ?");
            $stmt->execute([$user_id, $chat_id, $chat_type]);
            echo json_encode(['success' => true, 'message' => 'Chat unpinned']);
        } else {
            // Pin
            $stmt = $pdo->prepare("INSERT INTO pinned_chats (user_id, chat_id, chat_type) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $chat_id, $chat_type]);
            echo json_encode(['success' => true, 'message' => 'Chat pinned']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to pin chat: ' . $e->getMessage()]);
    }
}

function handleGetUserProfile($pdo, $user_id) {
    $target_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

    if ($target_user_id < 1) {
        sendJsonResponse(['success' => false, 'error' => 'User ID is required']);
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id, username, email, full_name, avatar, status, last_seen, created_at
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$target_user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            sendJsonResponse(['success' => false, 'error' => 'User not found']);
        }

        if ($target_user_id === (int)$user_id) {
            $friendshipStatus = 'self';
        } else {
            $friendStmt = $pdo->prepare(
                'SELECT user_id, friend_id, status
                 FROM friends
                 WHERE (user_id = ? AND friend_id = ?)
                    OR (user_id = ? AND friend_id = ?)
                 ORDER BY CASE status WHEN \'accepted\' THEN 0 WHEN \'pending\' THEN 1 ELSE 2 END
                 LIMIT 1'
            );
            $friendStmt->execute([(int)$user_id, $target_user_id, $target_user_id, (int)$user_id]);
            $friendship = $friendStmt->fetch(PDO::FETCH_ASSOC);

            if (!$friendship) {
                $friendshipStatus = 'none';
            } elseif ($friendship['status'] === 'accepted') {
                $friendshipStatus = 'accepted';
            } elseif ($friendship['status'] === 'pending') {
                $friendshipStatus = ((int)$friendship['user_id'] === (int)$user_id)
                    ? 'pending_sent'
                    : 'pending_received';
            } else {
                $friendshipStatus = 'blocked';
            }
        }

        $user['friendship_status'] = $friendshipStatus;
        sendJsonResponse(['success' => true, 'user' => $user]);
    } catch (Throwable $e) {
        error_log('[get_user_profile] ' . $e->getMessage());
        sendJsonResponse(['success' => false, 'error' => 'Failed to get user profile']);
    }
}

function handleGetAllMessages($pdo, $user_id) {
    $group_id = $_GET['group_id'] ?? null;
    
    if (!$group_id) {
        echo json_encode(['error' => 'Group ID is required']);
        return;
    }
    
    try {
        // Check if user is a member of the group
        $stmt = $pdo->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$group_id, $user_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['error' => 'Not a member of this group']);
            return;
        }
        
        // Get all messages for the group
        $stmt = $pdo->prepare("
            SELECT m.*, u.username as sender_username, u.avatar as sender_avatar
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.group_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$group_id]);
        $messages = $stmt->fetchAll();
        
        sendJsonResponse(['success' => true, 'messages' => $messages]);
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to get messages: ' . $e->getMessage()]);
    }
}
?>

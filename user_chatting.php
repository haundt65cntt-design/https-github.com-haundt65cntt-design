<?php
// Set UTF-8 encoding
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

require_once 'config/database.php';
require_once 'includes/language.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$full_name = $_SESSION['full_name'] ?? $_SESSION['username'];

// Handle logout
if (isset($_GET['logout'])) {
    // Update user status to offline
    $stmt = $pdo->prepare("UPDATE users SET status = 'offline', last_seen = NOW() WHERE id = ?");
    $stmt->execute([$user_id]);
    
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get user's friends
$stmt = $pdo->prepare("
    SELECT u.*, f.status as friendship_status 
    FROM friends f 
    JOIN users u ON (f.friend_id = u.id) 
    WHERE f.user_id = ? AND f.status = 'accepted'
    ORDER BY u.status DESC, u.full_name ASC
");
$stmt->execute([$user_id]);
$friends = $stmt->fetchAll();

// Get user's groups
$stmt = $pdo->prepare("SELECT g.*, gm.role FROM `groups` g JOIN group_members gm ON g.id = gm.group_id WHERE gm.user_id = ? ORDER BY g.name ASC");
$stmt->execute([$user_id]);
$groups = $stmt->fetchAll();

// Get pending friend requests
$stmt = $pdo->prepare("
    SELECT u.*, f.created_at as request_date 
    FROM friends f 
    JOIN users u ON f.user_id = u.id 
    WHERE f.friend_id = ? AND f.status = 'pending'
    ORDER BY f.created_at DESC
");
$stmt->execute([$user_id]);
$pending_requests = $stmt->fetchAll();

// Restore a requested conversation when arriving from another page or legacy group.php links.
$initial_chat = null;
if (isset($_GET['group_id']) && (int)$_GET['group_id'] > 0) {
    $requested_group_id = (int)$_GET['group_id'];
    $initial_stmt = $pdo->prepare("
        SELECT g.id, g.name
        FROM `groups` g
        INNER JOIN group_members gm ON gm.group_id = g.id
        WHERE g.id = ? AND gm.user_id = ?
        LIMIT 1
    ");
    $initial_stmt->execute([$requested_group_id, $user_id]);
    $initial_group = $initial_stmt->fetch();
    if ($initial_group) {
        $initial_chat = [
            'id' => (int)$initial_group['id'],
            'type' => 'group',
            'name' => $initial_group['name']
        ];
    }
} elseif (isset($_GET['user_id']) && (int)$_GET['user_id'] > 0) {
    $requested_user_id = (int)$_GET['user_id'];
    if ($requested_user_id !== (int)$user_id) {
        $initial_stmt = $pdo->prepare("SELECT id, username, full_name FROM users WHERE id = ? LIMIT 1");
        $initial_stmt->execute([$requested_user_id]);
        $initial_user = $initial_stmt->fetch();
        if ($initial_user) {
            $initial_chat = [
                'id' => (int)$initial_user['id'],
                'type' => 'user',
                'name' => $initial_user['full_name'] ?: $initial_user['username']
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang->getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang->get('chat'); ?> - Chat System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/theme.css" rel="stylesheet">
    <style>
        body {
            background: var(--gradient-bg);
            background-attachment: fixed;
            height: 100vh;
            overflow: hidden;
            margin: 0;
            padding: 0;
        }
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            width: 100%;
            height: 100vh;
        }
        .chat-header {
            padding: 1rem;
            background: white;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        
        .chat-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .chat-header-actions .btn {
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .chat-header-actions .btn:hover {
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        /* Call button special styles */
        .chat-header-actions .btn-outline-success {
            color: #28a745;
            border-color: #28a745;
        }
        
        .chat-header-actions .btn-outline-success:hover {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .chat-header-actions .btn-outline-primary {
            color: #3b82f6;
            border-color: #3b82f6;
        }
        
        .chat-header-actions .btn-outline-primary:hover {
            background-color: #3b82f6;
            border-color: #3b82f6;
            color: white;
        }
        .chat-pinned-bar {
            flex-shrink: 0;
            background: linear-gradient(135deg, #eff6ff 0%, #f0f7ff 100%);
            border-bottom: 1px solid #e0e0e0;
            padding: 8px 12px;
            max-height: 80px;
            overflow-y: auto;
        }
        .chat-pinned-bar .pinned-label {
            font-size: 11px;
            color: #60a5fa;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .chat-pinned-bar .pinned-item {
            font-size: 13px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }
        .chat-pinned-bar .pinned-item:last-child {
            margin-bottom: 0;
        }
        .chat-pinned-bar .pinned-item-content {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .chat-pinned-bar .pinned-item-img {
            max-height: 40px;
            max-width: 60px;
            object-fit: cover;
            border-radius: 4px;
            vertical-align: middle;
            margin-right: 6px;
            cursor: pointer;
            transition: opacity 0.2s;
            flex-shrink: 0;
        }
        .chat-pinned-bar .pinned-item-img:hover {
            opacity: 0.85;
        }
        .chat-pinned-bar .pinned-unpin-btn {
            flex-shrink: 0;
            border: none;
            background: rgba(0,0,0,0.06);
            color: #666;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
        }
        .chat-pinned-bar .pinned-unpin-btn:hover {
            background: rgba(244,67,54,0.2);
            color: #f44336;
        }
        .chat-messages {
            flex: 1;
            padding: 1rem 36px 1rem 36px;
            overflow-y: auto;
            background: #f8f9fa;
            min-height: 0; /* Allow flex shrinking */
        }
        .chat-input {
            padding: 0.75rem 1rem;
            background: white;
            border-top: 1px solid #e9ecef;
            min-height: 60px;
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }
        
        .chat-input .input-group {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .chat-input .form-control {
            flex: 1;
            min-height: 40px;
            max-height: 120px;
            border-radius: 20px;
            border: 1px solid #e9ecef;
            padding: 8px 16px;
            font-size: 14px;
            line-height: 1.4;
            resize: none;
            overflow-y: auto;
        }
        
        .chat-input .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            outline: none;
        }
        
        .chat-input .btn {
            height: 40px;
            width: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e9ecef;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }
        
        /* Responsive design for mobile */
        @media (max-width: 768px) {
            .chat-container {
                margin-left: 0;
                width: 100%;
            }
            
            .chat-input {
                padding: 0.5rem;
            }
            
            .chat-input .form-control {
                font-size: 16px; /* Prevent zoom on iOS */
            }
            
            .chat-input .btn {
                height: 36px;
                width: 36px;
            }
        }
        
        .chat-input .btn-outline-secondary {
            background: white;
            color: #6c757d;
        }
        
        .chat-input .btn-outline-secondary:hover {
            background: #f8f9fa;
            border-color: #adb5bd;
        }
        
        .chat-input .btn-primary {
            background: #3b82f6;
            border-color: #3b82f6;
        }
        
        .chat-input .btn-primary:hover {
            background: #2563eb;
            border-color: #2563eb;
        }
        /* Modern Message Bubble Styles */
        .message {
            margin-bottom: 12px;
            display: flex;
            align-items: flex-end;
            max-width: 78%;
            position: relative;
            overflow: visible;
        }
        
        .message.sent {
            flex-direction: row-reverse;
            margin-left: auto;
            margin-right: 0;
        }
        
        .message.received {
            flex-direction: row;
            margin-left: 0;
            margin-right: auto;
        }
        
        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            margin: 0 8px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            color: white;
            overflow: hidden;
        }
        
        .message-avatar-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .message.sent .message-avatar {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }
        
        .message.received .message-avatar {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }
        
        .message-bubble {
            padding: 44px 16px 12px 16px;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            overflow: visible;
        }
        
        .message.sent .message-bubble {
            background: rgba(59, 130, 246, 0.08);
            color: #333;
            border: 1px solid #bbdefb;
            border-bottom-right-radius: 4px;
        }
        
        .message.received .message-bubble {
            background: #f5f5f5;
            color: #333;
            border: 1px solid #e0e0e0;
            border-bottom-left-radius: 4px;
        }
        
        .message-actions {
            position: absolute;
            top: 6px;
            right: 18px;
            left: auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 4px 6px;
            display: none;
            z-index: 10;
            border: 1px solid #e0e0e0;
            flex-wrap: nowrap;
            white-space: nowrap;
        }
        
        .message.sent .message-actions {
            right: auto;
            left: 18px;
        }
        
        .message:hover .message-actions {
            display: flex;
        }
        
        /* Min bubble width on hover so action bar stays on one row */
        .message:hover .message-bubble {
            min-width: 260px;
        }
        
        .message-action-btn {
            background: none;
            border: none;
            padding: 4px;
            margin: 0 1px;
            border-radius: 50%;
            cursor: pointer;
            color: #666;
            transition: all 0.2s ease;
            font-size: 12px;
            flex-shrink: 0;
        }
        
        .message-action-btn:hover {
            background: #f0f0f0;
            color: #60a5fa;
            transform: scale(1.1);
        }
.message-action-btn.pin-btn.pinned {
            color: #ffd700;
        }
        
        .message-reply {
            background: rgba(79, 172, 254, 0.1);
            border-left: 3px solid #60a5fa;
            padding: 8px 12px;
            margin-bottom: 8px;
            border-radius: 8px;
            font-size: 0.9em;
        }
        
        .message-reply-author {
            font-weight: bold;
            color: #60a5fa;
            margin-bottom: 4px;
        }
        
        .message-reply-content {
            color: #666;
            font-style: italic;
        }
        .message-reply-content.message-reply-image img {
            max-width: 120px;
            max-height: 80px;
            border-radius: 6px;
            object-fit: cover;
            display: block;
            margin-top: 4px;
        }
        .message-forwarded-from {
            font-size: 12px;
            color: #60a5fa;
            margin-bottom: 6px;
        }
        .message-forwarded-from i {
            margin-right: 4px;
        }
        .message-pinned-badge {
            font-size: 11px;
            color: #6c757d;
            margin-bottom: 4px;
        }
        .message-pinned-badge i {
            margin-right: 3px;
        }
        .message-action-btn.pin-btn.active {
            color: #60a5fa;
        }
        
        .message-edited {
            font-size: 0.8em;
            color: #999;
            font-style: italic;
            margin-top: 4px;
        }
        
        .message-recalled {
            background: #f8f9fa;
            color: #6c757d;
            font-style: italic;
            text-align: center;
            padding: 8px;
            border-radius: 8px;
        }
        
        .message-likes {
            display: flex;
            align-items: center;
            margin-top: 4px;
            font-size: 0.8em;
            color: #666;
        }
        
        .message-likes i {
            margin-right: 4px;
            color: #ff6b6b;
        }
        
        .message-tags {
            margin-top: 4px;
        }
        
        .message-tag {
            background: rgba(79, 172, 254, 0.1);
            color: #60a5fa;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.8em;
            margin-right: 4px;
            display: inline-block;
        }
        
        .message-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 4px;
        }
        
        .message-time {
            font-size: 11px;
            opacity: 0.7;
            font-weight: 400;
        }
        
        .message-like-indicator {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            font-size: 12px;
            color: #666;
        }
        .message-like-indicator .fa-heart {
            color: #ff6b6b;
        }
        .message-like-indicator .like-num {
            font-size: 11px;
        }
        
        .message.sent .message-time {
            color: #666;
        }
        
        .message.received .message-time {
            color: #666;
        }
        
        /* File message styles */
        .file-message {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            margin: 4px 0;
        }
        
        .message.received .file-message {
            background: rgba(0, 0, 0, 0.05);
        }
        
        .file-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 18px;
            color: white;
        }
        
        .file-icon.image {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .file-icon.video {
            background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
        }
        
        .file-icon.file {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }
        
        .file-icon.pdf {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        
        .file-icon.word {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }
        
        .file-icon.excel {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .file-icon.archive {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        }
        
        .file-info {
            flex: 1;
        }
        
        .file-name {
            font-weight: 600;
            margin-bottom: 2px;
            font-size: 14px;
        }
        
        .file-size {
            font-size: 12px;
            opacity: 0.7;
        }
        
        .file-download-link {
            color: #3b82f6;
            text-decoration: none;
            cursor: pointer;
            transition: color 0.2s ease;
        }
        
        .file-download-link:hover {
            color: #2563eb;
            text-decoration: underline;
        }
        
        .file-message:hover .file-download-link {
            color: #2563eb;
        }
        
        /* Image message styles */
        .image-message img {
            max-width: 200px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        /* Image grid styles for multiple images */
        .image-grid {
            display: grid;
            gap: 4px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .image-grid.single {
            grid-template-columns: 1fr;
        }
        
        .image-grid.double {
            grid-template-columns: 1fr 1fr;
        }
        
        .image-grid.triple {
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
        }
        
        .image-grid.triple .image-item:first-child {
            grid-column: 1 / -1;
        }
        
        .image-grid.quad {
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
        }
        
        .image-grid.many {
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: repeat(auto-fit, minmax(100px, 1fr));
        }
        
        .image-item {
            position: relative;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .image-item:hover {
            transform: scale(1.02);
        }
        
        .image-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 0;
            box-shadow: none;
        }
        
        .image-item.more-overlay {
            position: relative;
        }
        
        .image-item.more-overlay::after {
            content: '+More';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: bold;
        }
        
        /* Responsive image grid */
        @media (max-width: 768px) {
            .image-grid.many {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .image-grid.triple,
            .image-grid.quad {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        /* Voice message styles */
        .voice-message {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            margin: 4px 0;
        }
        
        .message.received .voice-message {
            background: rgba(0, 0, 0, 0.05);
        }
        
        .voice-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            color: white;
            font-size: 16px;
        }
        
        .voice-message audio {
            flex: 1;
            height: 40px;
            min-width: 200px;
        }
        
        /* Volume control styles */
        .voice-volume-control {
            display: flex;
            align-items: center;
            margin-left: 10px;
            gap: 5px;
        }
        
        .voice-volume-control input[type="range"] {
            width: 60px;
            height: 20px;
        }
        
        .voice-volume-control .volume-icon {
            font-size: 12px;
            color: #666;
        }
        
        /* Message actions - inline action bar, single row no wrap */
        .message-actions {
            display: none;
            position: absolute;
            top: 6px;
            right: 18px;
            left: auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            padding: 4px 6px;
            flex-wrap: nowrap;
            white-space: nowrap;
        }
        
        .message.sent .message-actions {
            right: auto;
            left: 18px;
        }
        
        .message:hover .message-actions {
            display: flex;
        }
        
        .message-actions button {
            width: 24px;
            height: 24px;
            min-width: 24px;
            border: none;
            background: transparent;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 1px;
            padding: 0;
            color: #6c757d;
            font-size: 12px;
            transition: all 0.2s ease;
        }
        
        .message-actions button:hover {
            background: #f8f9fa;
            color: #3b82f6;
        }
        .language-selector {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        /* Welcome screen styles */
        .welcome-screen {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        .welcome-content {
            text-align: center;
            max-width: 500px;
            padding: 2rem;
        }
        
        .welcome-content h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .welcome-content p {
            font-size: 1.2rem;
            color: #6c757d;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .add-friend-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border: none;
            padding: 15px 30px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 25px;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
            transition: all 0.3s ease;
        }
        
        .add-friend-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
        }
        
        .add-friend-btn i {
            margin-right: 8px;
        }
        
        /* Image modal styles */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            cursor: pointer;
        }
        
        .image-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }
        
        .image-modal-close {
            position: absolute;
            top: 20px;
            right: 35px;
            color: #fff;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
    </style>
    <!-- Zalo-style layout is embedded here so the chat pane does not depend on an optional CSS file. -->
    <style id="zalo-chat-layout-fix">
        :root {
            --zalo-rail-width: 68px;
            --zalo-list-width: 300px;
            --zalo-sidebar-width: 368px;
            --zalo-manual-bar: var(--user-manual-bar-space, 44px);
            --zalo-blue: #0068ff;
            --zalo-border: #e6ebf2;
        }

        html, body { width: 100%; min-height: 100%; }
        body.chat-page {
            height: 100vh !important;
            min-height: 0 !important;
            margin: 0 !important;
            padding-top: var(--zalo-manual-bar) !important;
            overflow: hidden !important;
            background: #f3f5f8 !important;
        }

        /* The navigation sidebar is split into a blue icon rail and a conversation list. */
        body.chat-page #sidebar.sidebar {
            position: fixed !important;
            top: var(--zalo-manual-bar) !important;
            right: auto !important;
            bottom: 0 !important;
            left: 0 !important;
            z-index: 1050 !important;
            display: grid !important;
            grid-template-columns: var(--zalo-rail-width) var(--zalo-list-width) !important;
            grid-template-rows: 72px minmax(0, 1fr) !important;
            width: var(--zalo-sidebar-width) !important;
            height: auto !important;
            min-height: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
            border: 0 !important;
            border-right: 1px solid #dce3ec !important;
            border-radius: 0 !important;
            background: #fff !important;
            box-shadow: 2px 0 14px rgba(24, 39, 58, .06) !important;
            transform: none !important;
        }

        body.chat-page #sidebar .user-profile-section {
            grid-column: 1 !important;
            grid-row: 1 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: var(--zalo-rail-width) !important;
            height: 72px !important;
            box-sizing: border-box !important;
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
            border-right: 1px solid rgba(255,255,255,.15) !important;
            border-radius: 0 !important;
            background: var(--zalo-blue) !important;
        }
        body.chat-page #sidebar .user-avatar { margin: 0 !important; }
        body.chat-page #sidebar .user-avatar .avatar-circle {
            width: 42px !important; height: 42px !important; font-size: 17px !important;
            border: 2px solid rgba(255,255,255,.9) !important;
        }
        body.chat-page #sidebar .user-info,
        body.chat-page #sidebar .user-actions { display: none !important; }
        body.chat-page #sidebar #userDropdownMenu {
            position: fixed !important; top: calc(var(--zalo-manual-bar) + 8px) !important;
            left: 76px !important; right: auto !important; z-index: 1200 !important;
        }

        body.chat-page #sidebar .nav-tabs-container {
            grid-column: 1 !important;
            grid-row: 2 !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: stretch !important;
            justify-content: flex-start !important;
            gap: 8px !important;
            width: var(--zalo-rail-width) !important;
            min-height: 0 !important;
            margin: 0 !important;
            padding: 12px 6px !important;
            overflow-x: hidden !important;
            overflow-y: auto !important;
            border: 0 !important;
            border-right: 1px solid #e7ecf2 !important;
            border-radius: 0 !important;
            background: var(--zalo-blue) !important;
        }
        body.chat-page #sidebar .nav-tab {
            display: flex !important;
            flex: 0 0 auto !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            width: 56px !important;
            min-height: 58px !important;
            margin: 0 auto !important;
            padding: 7px 2px !important;
            border: 0 !important;
            border-radius: 9px !important;
            color: rgba(255,255,255,.86) !important;
            background: transparent !important;
            text-decoration: none !important;
            cursor: pointer !important;
        }
        body.chat-page #sidebar .nav-tab .tab-text {
            display: block !important;
            max-width: 100% !important;
            overflow: hidden !important;
            color: inherit !important;
            font-size: 9px !important;
            font-weight: 600 !important;
            line-height: 1.2 !important;
            text-align: center !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
        }
        /* Nav icons are rendered by the <i> elements in navbar.php; no pseudo icons here. */
        body.chat-page #sidebar .nav-tab.active,
        body.chat-page #sidebar .nav-tab:hover {
            color: #fff !important;
            background: rgba(255,255,255,.17) !important;
        }
        body.chat-page #sidebar .nav-tab::after { display: none !important; }

        body.chat-page #sidebar .sidebar-content {
            grid-column: 2 !important;
            grid-row: 1 / span 2 !important;
            display: flex !important;
            flex-direction: column !important;
            width: var(--zalo-list-width) !important;
            min-width: 0 !important;
            min-height: 0 !important;
            height: 100% !important;
            overflow: hidden !important;
            background: #fff !important;
        }
        body.chat-page #sidebar .tab-content {
            display: none !important;
            flex-direction: column !important;
            flex: 1 1 auto !important;
            min-width: 0 !important;
            min-height: 0 !important;
            padding: 17px 13px 12px !important;
            overflow-x: hidden !important;
            overflow-y: auto !important;
            background: #fff !important;
        }
        body.chat-page #sidebar .tab-content.active { display: flex !important; }
        body.chat-page #sidebar #chats-content::before {
            content: "Tin nhắn"; display: block; margin: 0 0 14px 2px;
            color: #172b4d; font-size: 20px; font-weight: 700;
        }
        body.chat-page #sidebar #friends-content::before {
            content: "Danh bạ"; display: block; margin: 0 0 14px 2px;
            color: #172b4d; font-size: 20px; font-weight: 700;
        }
        body.chat-page #sidebar #groups-content::before {
            content: "Nhóm"; display: block; margin: 0 0 14px 2px;
            color: #172b4d; font-size: 20px; font-weight: 700;
        }
        body.chat-page #sidebar .search-container { flex: 0 0 auto; margin: 0 0 12px !important; }
        body.chat-page #sidebar .search-input {
            width: 100% !important; min-height: 38px !important; box-sizing: border-box !important;
            border: 1px solid #e2e8f0 !important; border-radius: 7px !important;
            background: #f4f6f9 !important; box-shadow: none !important; font-size: 13px !important;
        }
        body.chat-page #sidebar .chat-list,
        body.chat-page #sidebar .friends-list,
        body.chat-page #sidebar .groups-list {
            display: flex !important; flex: 1 1 auto !important; flex-direction: column !important;
            min-height: 0 !important; gap: 3px !important; overflow-y: auto !important;
        }
        body.chat-page #sidebar .chat-item,
        body.chat-page #sidebar .friend-item,
        body.chat-page #sidebar .group-item {
            display: flex !important; align-items: center !important; min-height: 68px !important;
            box-sizing: border-box !important; margin: 0 !important; padding: 9px !important;
            border: 0 !important; border-radius: 9px !important; background: transparent !important;
        }
        body.chat-page #sidebar .chat-item:hover,
        body.chat-page #sidebar .friend-item:hover,
        body.chat-page #sidebar .group-item:hover { background: #f2f6fc !important; }
        body.chat-page #sidebar .chat-item.active,
        body.chat-page #sidebar .friend-item.active,
        body.chat-page #sidebar .group-item.active {
            background: #eaf3ff !important; box-shadow: inset 3px 0 0 var(--zalo-blue) !important;
        }
        body.chat-page #sidebar .chat-name,
        body.chat-page #sidebar .friend-name,
        body.chat-page #sidebar .group-name { color: #172b4d !important; font-size: 13px !important; font-weight: 650 !important; }

        /* Critical fix: keep the conversation pane outside/next to the fixed sidebar. */
        body.chat-page .main-wrapper { display: none !important; }
        body.chat-page .chat-container {
            position: fixed !important;
            top: var(--zalo-manual-bar) !important;
            right: 0 !important;
            bottom: 0 !important;
            left: var(--zalo-sidebar-width) !important;
            z-index: 1 !important;
            display: flex !important;
            width: auto !important;
            height: auto !important;
            min-width: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
            background: #f3f5f8 !important;
        }
        body.chat-page .chat-main {
            display: flex !important;
            flex: 1 1 auto !important;
            flex-direction: column !important;
            width: 100% !important;
            height: 100% !important;
            min-width: 0 !important;
            min-height: 0 !important;
            overflow: hidden !important;
            background: #f3f5f8 !important;
        }
        body.chat-page .chat-header {
            display: flex !important; flex: 0 0 70px !important; align-items: center !important;
            min-height: 70px !important; box-sizing: border-box !important; padding: 0 22px !important;
            border: 0 !important; border-bottom: 1px solid var(--zalo-border) !important;
            background: #fff !important;
        }
        body.chat-page #chatTitle h5 { margin: 0 0 3px !important; color: #172b4d !important; font-size: 16px !important; font-weight: 700 !important; }
        body.chat-page #chatTitle small { color: #8490a0 !important; font-size: 11px !important; }
        body.chat-page .chat-error-state {
            max-width: 760px; margin: 24px auto; padding: 14px 18px;
            color: #a4262c; background: #fff1f0; border: 1px solid #ffd2cf;
            border-radius: 10px; font-size: 14px; line-height: 1.5; overflow-wrap: anywhere;
        }
        body.chat-page .chat-messages {
            display: block !important; flex: 1 1 auto !important; min-height: 0 !important;
            width: 100% !important; padding: 22px clamp(18px, 4vw, 56px) !important;
            overflow-x: hidden !important; overflow-y: auto !important; background: #f3f5f8 !important;
        }
        body.chat-page .welcome-screen { display: flex !important; min-height: 100% !important; align-items: center !important; justify-content: center !important; background: transparent !important; }
        body.chat-page .welcome-content { max-width: 440px !important; padding: 30px 20px !important; text-align: center !important; }
        body.chat-page .welcome-content h1 { color: #30445f !important; font-size: 23px !important; }
        body.chat-page .chat-input {
            flex: 0 0 auto !important; min-height: 76px !important; box-sizing: border-box !important;
            padding: 12px 20px !important; border: 0 !important;
            border-top: 1px solid var(--zalo-border) !important; background: #fff !important;
        }
        body.chat-page .chat-input .input-group { display: flex !important; width: 100% !important; gap: 8px !important; }
        body.chat-page .chat-input textarea.form-control { border-radius: 9px !important; background: #f6f8fb !important; }

        @media (max-width: 768px) {
            :root { --zalo-rail-width: 62px; --zalo-list-width: min(280px, calc(100vw - 62px)); --zalo-sidebar-width: min(342px, 100vw - 30px); }
            body.chat-page #sidebar.sidebar { transform: translateX(-105%) !important; transition: transform .2s ease !important; }
            body.chat-page #sidebar.sidebar.show { transform: translateX(0) !important; }
            body.chat-page .chat-container { left: 0 !important; }
            body.chat-page .chat-header { min-height: 60px !important; flex-basis: 60px !important; padding: 0 12px !important; }
            body.chat-page .chat-messages { padding: 14px 12px !important; }
            body.chat-page .chat-input { padding: 9px 10px !important; }
        }
    </style>
</head>
<body class="chat-page">
    <?php include 'navbar.php'; ?>

    <div class="chat-container">
        <!-- Main Chat Area -->
        <div class="chat-main">
            <div class="chat-header">
                <div id="chatTitle">
                    <h5><i class="fas fa-comments"></i> Welcome to Chat System</h5>
                    <small class="text-muted">Select a friend or group to start chatting</small>
                </div>
                <div class="chat-header-actions" id="chatHeaderActions" style="display: none;">
                    <!-- private chat call buttons -->
                    <button class="btn btn-outline-success btn-sm" id="voiceCallBtn" title="Voice Call">
                        <i class="fas fa-phone"></i>
                    </button>
                    <button class="btn btn-outline-primary btn-sm" id="videoCallBtn" title="Video Call">
                        <i class="fas fa-video"></i>
                    </button>
                    <!-- Group call buttons -->
                    <button class="btn btn-outline-success btn-sm" id="groupVoiceCallBtn" title="Group Voice Call">
                        <i class="fas fa-phone"></i>
                    </button>
                    <button class="btn btn-outline-primary btn-sm" id="groupVideoCallBtn" title="Group Video Call">
                        <i class="fas fa-video"></i>
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" id="groupMenuBtn" title="Group Menu">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
            </div>
            
            <div class="chat-pinned-bar" id="chatPinnedBar" style="display: none;"></div>
            
            <div class="chat-messages" id="chatMessages">
                <div class="welcome-screen">
                    <div class="welcome-content">
                        <h1>Welcome to Chat System</h1>
                        <p>Select a chat to start a conversation, or add new friends</p>
                        <button class="btn btn-primary btn-lg add-friend-btn">
                            <i class="fas fa-user-plus"></i> Add Friend
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="chat-input" id="chatInput" style="display: none;">
                <div class="input-group d-flex align-items-end">
                    <textarea class="form-control" id="messageInput" placeholder="<?php echo $lang->get('type_message'); ?>" rows="1" style="flex: 1; min-height: 40px; max-height: 120px; resize: none;"></textarea>
                    <button class="btn btn-outline-secondary" type="button" id="attachFileBtn" title="Attach file">
                        <i class="fas fa-paperclip"></i>
                    </button>
                    <button class="btn btn-outline-secondary" type="button" id="voiceBtn" title="Voice message">
                        <i class="fas fa-microphone"></i>
                    </button>
                    <button class="btn btn-primary" type="button" id="sendBtn" title="Send message">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                <!-- Hidden file input -->
                <input type="file" id="fileInput" style="display: none;" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar" multiple>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="image-modal">
        <span class="image-modal-close">&times;</span>
        <img class="image-modal-content" id="modalImage">
    </div>

    <!-- Create Group Modal is handled by navbar.php -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/chat_system.js"></script>
    <script>
        // Set global variables for JavaScript
        window.currentUserId = <?php echo $user_id; ?>;
        window.currentUsername = '<?php echo htmlspecialchars($username); ?>';
        window.currentChatId = null;
        window.currentChatType = null; // 'user' or 'group'
        <?php $base = dirname($_SERVER['SCRIPT_NAME']); $base = ($base === '/' || $base === '\\') ? '' : rtrim($base, '/') . '/'; ?>
        window.BASE_URL = <?php echo json_encode($base); ?>;
        window.APP_INITIAL_CHAT = <?php echo json_encode($initial_chat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        
        // Voice recording variables
        let recording = false;
        let mediaRecorder = null;
        let audioChunks = [];
        
        // Create Group functionality is handled by navbar.php
        
        // Make functions globally available for navbar.php
        window.updateChatInterface = function(name, type, chatId) {
            if (chatId) {
                window.currentChatId = chatId;
                window.currentChatType = type;
            }

            const titleContainer = document.getElementById('chatTitle');
            const chatInput = document.getElementById('chatInput');
            if (titleContainer) {
                titleContainer.innerHTML = '';
                const heading = document.createElement('h5');
                const icon = document.createElement('i');
                icon.className = type === 'user' ? 'fas fa-user' : 'fas fa-layer-group';
                heading.appendChild(icon);
                heading.appendChild(document.createTextNode(' ' + String(name || (type === 'user' ? 'Cuộc trò chuyện' : 'Nhóm chat'))));
                const subtitle = document.createElement('small');
                subtitle.className = 'text-muted';
                subtitle.textContent = type === 'user' ? 'Tin nhắn riêng' : 'Trò chuyện nhóm';
                titleContainer.appendChild(heading);
                titleContainer.appendChild(subtitle);
            }
            if (chatInput) chatInput.style.display = 'flex';

            const chatHeaderActions = document.getElementById('chatHeaderActions');
            const voiceCallBtn = document.getElementById('voiceCallBtn');
            const videoCallBtn = document.getElementById('videoCallBtn');
            const groupVoiceCallBtn = document.getElementById('groupVoiceCallBtn');
            const groupVideoCallBtn = document.getElementById('groupVideoCallBtn');
            const groupMenuBtn = document.getElementById('groupMenuBtn');

            if (chatHeaderActions) chatHeaderActions.style.display = 'flex';
            if (voiceCallBtn) voiceCallBtn.style.display = type === 'user' ? 'flex' : 'none';
            if (videoCallBtn) videoCallBtn.style.display = type === 'user' ? 'flex' : 'none';
            if (groupVoiceCallBtn) groupVoiceCallBtn.style.display = type === 'group' ? 'flex' : 'none';
            if (groupVideoCallBtn) groupVideoCallBtn.style.display = type === 'group' ? 'flex' : 'none';
            if (groupMenuBtn) groupMenuBtn.style.display = type === 'group' ? 'flex' : 'none';
        };

        window.loadMessages = function(chatId, type, options) {
            options = options || {};
            if (!chatId || !type) {
                console.error('Missing chatId or type');
                return;
            }
            
            // Set global variables
            window.currentChatId = chatId;
            window.currentChatType = type;
            
            const messagesContainer = document.getElementById('chatMessages');
            const preserveScroll = options.silent && messagesContainer && 
                (messagesContainer.scrollHeight - messagesContainer.scrollTop - messagesContainer.clientHeight > 80);
            const scrollTop = preserveScroll ? messagesContainer.scrollTop : 0;
            
            // Show loading message only on explicit user-initiated loads
            if (!options.silent && messagesContainer) {
                messagesContainer.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading messages...</div>';
            }
            
            // Make AJAX request to load messages
            let url;
            if (type === 'user') {
                url = 'ajax/chat_handler.php?action=get_messages&receiver_id=' + chatId;
            } else if (type === 'group') {
                url = 'ajax/chat_handler.php?action=get_messages&group_id=' + chatId;
            } else {
                console.error('Invalid chat type:', type);
                return;
            }
            
            url = (window.BASE_URL || '') + url;
            fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
                .then(async response => {
                    const raw = await response.text();
                    let data;
                    try {
                        data = JSON.parse(raw);
                    } catch (parseError) {
                        throw new Error('Máy chủ không trả về JSON hợp lệ (HTTP ' + response.status + ').');
                    }
                    if (!response.ok) {
                        throw new Error(data.error || ('HTTP ' + response.status));
                    }
                    return data;
                })
                .then(data => {
                    if (data.success && Array.isArray(data.messages)) {
                        displayMessages(data.messages, { preserveScroll: preserveScroll, scrollTop: scrollTop });
                    } else {
                        const reason = data.error || 'Không tải được lịch sử tin nhắn.';
                        console.error('Error loading messages:', reason);
                        messagesContainer.innerHTML = '<div class="chat-error-state">' + escapeHtml(reason) + '</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading/rendering messages:', error);
                    if (messagesContainer) {
                        messagesContainer.innerHTML = '<div class="chat-error-state">' + escapeHtml(error.message || 'Không tải được tin nhắn.') + '</div>';
                    }
                });
        };
        
        function displayMessages(messages, options) {
            options = options || {};
            const messagesContainer = document.getElementById('chatMessages');
            const pinnedBar = document.getElementById('chatPinnedBar');
            if (!messagesContainer) return;
            
            if (options.silent) {
                const newSig = messages.map(m => m.id + ':' + (m.is_pinned ? 1 : 0)).join('|');
                if (messagesContainer.dataset.messageSig === newSig) {
                    return;
                }
                messagesContainer.dataset.messageSig = newSig;
            } else {
                delete messagesContainer.dataset.messageSig;
            }
            
            messagesContainer.innerHTML = '';
            
            const pinned = messages.filter(m => m.is_pinned);
            const normal = messages.filter(m => !m.is_pinned);
            
            if (pinned.length > 0 && pinnedBar) {
                pinnedBar.style.display = 'block';
                pinnedBar.innerHTML = '<div class="pinned-label"><i class="fas fa-thumbtack"></i> Pinned</div>';
                pinned.forEach(msg => {
                    const el = document.createElement('div');
                    el.className = 'pinned-item';
                    el.dataset.messageId = msg.id;
                    let imgSrc = '';
                    if (msg.message_type === 'image' && msg.file_path) {
                        imgSrc = msg.file_path.startsWith('uploads/') && !msg.file_path.startsWith('ajax/') ? 'ajax/' + msg.file_path : msg.file_path;
                        el.innerHTML = '<span class="pinned-item-content"><img src="' + imgSrc + '" alt="" class="pinned-item-img" onclick="event.stopPropagation(); openImageModal(\'' + imgSrc.replace(/'/g, "\\'") + '\')"> [Image]</span><button type="button" class="pinned-unpin-btn" title="CancelPinned" onclick="event.stopPropagation(); togglePin(' + msg.id + ', true)"><i class="fas fa-times"></i></button>';
                    } else {
                        const text = ((msg.message || '').substring(0, 50) + (msg.message && msg.message.length > 50 ? '…' : '')).replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        el.innerHTML = '<span class="pinned-item-content">' + text + '</span><button type="button" class="pinned-unpin-btn" title="CancelPinned" onclick="event.stopPropagation(); togglePin(' + msg.id + ', true)"><i class="fas fa-times"></i></button>';
                    }
                    pinnedBar.appendChild(el);
                });
            } else if (pinnedBar) {
                pinnedBar.style.display = 'none';
                pinnedBar.innerHTML = '';
            }
            
            if (normal.length === 0 && pinned.length === 0) {
                messagesContainer.innerHTML = '<div class="text-center text-muted"><p>No messages yet. Start the conversation!</p></div>';
                return;
            }
            
            if (normal.length === 0) {
                messagesContainer.innerHTML = '<div class="text-center text-muted"><p>No other messages</p></div>';
                return;
            }
            
            normal.forEach(message => {
                const messageElement = createMessageElement(message);
                messagesContainer.appendChild(messageElement);
            });
            
            if (options.preserveScroll) {
                messagesContainer.scrollTop = options.scrollTop;
            } else {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }
        
        function createMessageElement(message) {
            const messageDiv = document.createElement('div');
            const isOwnMessage = message.sender_id == window.currentUserId;
            messageDiv.className = 'message ' + (isOwnMessage ? 'sent' : 'received');
            messageDiv.dataset.messageId = message.id;
            
            // Get user avatar (first letter of username or actual avatar)
            const username = message.sender_full_name || message.sender_username || message.username || 'U';
            const avatarLetter = username.charAt(0).toUpperCase();
            const userAvatar = message.sender_avatar || message.avatar || null;
            
            let content = '';
            
            // Add avatar
            if (userAvatar && userAvatar !== 'default_avatar.png') {
                content += '<div class="message-avatar"><img src="uploads/avatars/' + userAvatar + '?t=' + Date.now() + '" alt="Avatar" class="message-avatar-image"></div>';
            } else {
                content += '<div class="message-avatar">' + avatarLetter + '</div>';
            }
            
            // Add message bubble
            content += '<div class="message-bubble">';
            
            // Add message actions
            content += '<div class="message-actions">';
            content += '<button class="message-action-btn reply-btn" onclick="replyToMessage(' + message.id + ')" title="Reply"><i class="fas fa-reply"></i></button>';
            content += '<button class="message-action-btn forward-btn" onclick="forwardMessage(' + message.id + ')" title="Forward"><i class="fas fa-share"></i></button>';
            content += '<button class="message-action-btn tag-btn" onclick="tagUser(' + message.id + ')" title="Tag User"><i class="fas fa-at"></i></button>';
            
            // Only show edit/recall for own messages within 2 minutes
            const messageTime = new Date(message.created_at).getTime();
            const currentTime = Date.now();
            const timeDiff = Math.abs((currentTime - messageTime) / 1000); // seconds (use absolute value to handle timezone issues)
            
            // For testing, show edit button for own messages regardless of time
            if (message.sender_id == window.currentUserId) {
                content += '<button class="message-action-btn edit-btn" onclick="editMessage(' + message.id + ')" title="Edit"><i class="fas fa-edit"></i></button>';
                content += '<button class="message-action-btn recall-btn" onclick="recallMessage(' + message.id + ')" title="Recall"><i class="fas fa-undo"></i></button>';
            }
            
            content += '<button class="message-action-btn pin-btn' + (message.is_pinned ? ' active' : '') + '" onclick="togglePin(' + message.id + ', ' + (message.is_pinned ? 'true' : 'false') + ')" title="' + (message.is_pinned ? 'CancelPinned' : 'Pin') + '"><i class="fas fa-thumbtack"></i></button>';
            content += '</div>';
            
            // Pinned indicator
            if (message.is_pinned) {
                content += '<div class="message-pinned-badge"><i class="fas fa-thumbtack"></i> Pinned</div>';
            }
            
            // Reply content if this is a reply
            if (message.reply_to) {
                content += '<div class="message-reply">';
                content += '<div class="message-reply-author">Replying to message</div>';
                if (message.reply_message_type === 'image' && message.reply_file_path) {
                    let replyImgPath = message.reply_file_path;
                    if (replyImgPath.startsWith('uploads/') && !replyImgPath.startsWith('ajax/')) replyImgPath = 'ajax/' + replyImgPath;
                    content += '<div class="message-reply-content message-reply-image"><img src="' + replyImgPath + '" alt="" onerror="this.parentElement.innerHTML=\'[Image]\'"></div>';
                } else {
                    content += '<div class="message-reply-content">' + (message.reply_content || 'Original message') + '</div>';
                }
                content += '</div>';
            }
            
            // Forwarded indicator: show who the message was forwarded from
            if (message.forwarded_from) {
                const forwardName = (message.forward_full_name || message.forward_username || '').trim() || 'Unknown';
                const safeName = (forwardName + '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                content += '<div class="message-forwarded-from"><i class="fas fa-share"></i> Forwarded from ' + safeName + '</div>';
            }
            
            // Message content
            if (message.is_recalled) {
                content += '<div class="message-recalled">This message was recalled</div>';
            } else if (message.message_type === 'text') {
                content += '<div class="message-content">' + formatMessage(message.message) + '</div>';
            } else if (message.message_type === 'image') {
                content += '<div class="message-content image-message">';
                // Check if this is part of a consecutive image group
                const imageGroup = getConsecutiveImages(message);
                if (imageGroup.length > 1) {
                    content += renderImageGrid(imageGroup);
                } else {
                    // Fix image path - check both possible locations
                    let imagePath = message.file_path || '';
                    if (imagePath && imagePath.startsWith('uploads/') && !imagePath.startsWith('ajax/')) {
                        // Try ajax/uploads path first
                        let ajaxPath = 'ajax/' + imagePath;
                        content += '<img src="' + ajaxPath + '" alt="Image" style="max-width: 200px; max-height: 200px; border-radius: 8px; cursor: pointer; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);" onclick="openImageModal(\'' + ajaxPath + '\')" onerror="this.src=\'' + imagePath + '\'">';
                    } else {
                        content += '<img src="' + imagePath + '" alt="Image" style="max-width: 200px; max-height: 200px; border-radius: 8px; cursor: pointer; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);" onclick="openImageModal(\'' + imagePath + '\')">';
                    }
                }
                content += '</div>';
            } else if (message.message_type === 'file') {
                content += '<div class="message-content">';
                content += '<div class="file-message">';
                
                // Determine file type and icon
                let fileName = message.file_name || 'File';
                
                // If file_name is empty but we have file_path, extract filename from path
                if (!fileName || fileName === 'File') {
                    const filePath = message.file_path || '';
                    if (filePath) {
                        fileName = filePath.split('/').pop() || filePath.split('\\').pop() || 'File';
                    }
                }
                
                const fileExtension = fileName.split('.').pop().toLowerCase();
                let fileTypeClass = 'file';
                let fileIcon = 'fas fa-file';
                
                if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(fileExtension)) {
                    fileTypeClass = 'image';
                    fileIcon = 'fas fa-image';
                } else if (['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'].includes(fileExtension)) {
                    fileTypeClass = 'video';
                    fileIcon = 'fas fa-video';
                } else if (['pdf'].includes(fileExtension)) {
                    fileTypeClass = 'pdf';
                    fileIcon = 'fas fa-file-pdf';
                } else if (['doc', 'docx'].includes(fileExtension)) {
                    fileTypeClass = 'word';
                    fileIcon = 'fas fa-file-word';
                } else if (['xls', 'xlsx'].includes(fileExtension)) {
                    fileTypeClass = 'excel';
                    fileIcon = 'fas fa-file-excel';
                } else if (['zip', 'rar', '7z'].includes(fileExtension)) {
                    fileTypeClass = 'archive';
                    fileIcon = 'fas fa-file-archive';
                }
                
                content += '<div class="file-icon ' + fileTypeClass + '">';
                content += '<i class="' + fileIcon + '"></i>';
                content += '</div>';
                content += '<div class="file-info">';
                content += '<div class="file-name">';
                // Fix file path - check both possible locations
                let filePath = message.file_path;
                if (filePath.startsWith('uploads/') && !filePath.startsWith('ajax/')) {
                    // Try ajax/uploads path first
                    let ajaxPath = 'ajax/' + filePath;
                    content += '<a href="' + ajaxPath + '" download="' + fileName + '" class="file-download-link" title="Click to download file">' + fileName + '</a>';
                } else {
                    content += '<a href="' + filePath + '" download="' + fileName + '" class="file-download-link" title="Click to download file">' + fileName + '</a>';
                }
                content += '</div>';
                content += '<div class="file-size">' + formatFileSize(message.file_size || 0) + '</div>';
                content += '</div>';
                content += '</div>';
                content += '</div>';
            } else if (message.message_type === 'voice') {
                content += '<div class="message-content">';
                content += '<div class="voice-message">';
                content += '<div class="voice-icon">';
                content += '<i class="fas fa-microphone"></i>';
                content += '</div>';
                content += '<audio controls volume="0.8" preload="metadata">';
                let audioPath = message.file_path || '';
                // Fix voice file path - files are stored in ajax/uploads/voice/ but DB has uploads/voice/
                if (audioPath && audioPath.startsWith('uploads/voice/')) {
                    audioPath = 'ajax/' + audioPath;
                }
                content += '<source src="' + audioPath + '" type="audio/webm">';
                content += '</audio>';
                content += '<div class="voice-volume-control">';
                content += '<i class="fas fa-volume-up volume-icon"></i>';
                content += '<input type="range" min="0" max="1" step="0.1" value="0.8" onchange="setAudioVolume(this, this.previousElementSibling.previousElementSibling)">';
                content += '</div>';
                content += '</div>';
                content += '</div>';
            } else {
                content += '<div class="message-content">' + formatMessage(message.message) + '</div>';
            }
            
            // Add message meta (time + like indicator in bottom right)
            const timeAgo = getTimeAgo(message.created_at);
            content += '<div class="message-meta">';
            content += '<div class="message-time">' + timeAgo + '</div>';
            
            // Show edited status
            if (message.is_edited) {
                content += '<div class="message-edited">edited</div>';
            }
            
            // Message reactions are intentionally not shown in chat.
                        // Show tags
            if (message.tags && message.tags.length > 0) {
                content += '<div class="message-tags">';
                message.tags.forEach(tag => {
                    content += '<span class="message-tag">@' + tag.username + '</span>';
                });
                content += '</div>';
            }
            
            content += '</div>';
            
            content += '</div>'; // Close message-bubble
            
            messageDiv.innerHTML = content;
            return messageDiv;
        }
        
        function escapeHtml(value) {
            return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
                return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]);
            });
        }

        function formatMessage(message) {
            // Escape user content first; only then add safe mention/link markup.
            let safe = String(message || '').replace(/[&<>"']/g, function (character) {
                return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]);
            });
            safe = safe.replace(/@(\w+)/g, '<span class="mention">@$1</span>');
            safe = safe.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
            return safe.replace(/\n/g, '<br>');
        }
        
        function getTimeAgo(timestamp) {
            const now = new Date();
            const messageTime = new Date(timestamp);
            const diffInSeconds = Math.floor((now - messageTime) / 1000);
            
            if (diffInSeconds < 60) return 'Just now';
            if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + 'm ago';
            if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + 'h ago';
            return messageTime.toLocaleDateString();
        }
        
        // Send message. The input is cleared only after the server confirms success.
        document.getElementById('sendBtn').addEventListener('click', function() {
            const input = document.getElementById('messageInput');
            const message = input ? input.value.trim() : '';
            if (!message) return;
            if (!window.currentChatId || !['user', 'group'].includes(window.currentChatType)) {
                alert('Vui lòng chọn một cuộc trò chuyện trước khi gửi tin nhắn.');
                return;
            }
            sendMessage(message);
        });
        
        // Auto-resize textarea and handle Enter key
        const messageInput = document.getElementById('messageInput');
        
        messageInput.addEventListener('input', function() {
            // Auto-resize textarea
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
        
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('sendBtn').click();
            }
        });
        
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.shiftKey) {
                // Allow Shift+Enter for new line
                return;
            }
        });
        
        // ===== COMPLETELY NEW FILE UPLOAD FUNCTIONALITY =====
        
        // File upload button click handler
        document.getElementById('attachFileBtn').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Check if chat is selected
            if (!window.currentChatId || !window.currentChatType) {
                alert('Please select a chat first');
                return;
            }
            
            // Open file dialog
            document.getElementById('fileInput').click();
        });
        
        // File input change handler
        document.getElementById('fileInput').addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            if (files.length > 0) {
                handleMultipleFileUpload(files);
            }
            // Clear the input
            e.target.value = '';
        });
        
        // Voice recording button click handler
        document.getElementById('voiceBtn').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Check if chat is selected
            if (!window.currentChatId || !window.currentChatType) {
                alert('Please select a chat first');
                return;
            }
            
            toggleVoiceRecording();
        });
        
        // Multiple file upload handler
        function handleMultipleFileUpload(files) {
            console.log('Uploading multiple files:', files.length);
            
            // Validate all files first
            const validFiles = [];
            for (let file of files) {
                if (validateFile(file)) {
                    validFiles.push(file);
                }
            }
            
            if (validFiles.length === 0) {
                return;
            }
            
            // Show upload progress
            showUploadProgress(true, 0, validFiles.length);
            
            // Upload files sequentially to avoid overwhelming the server
            uploadFilesSequentially(validFiles, 0);
        }
        
        // Upload files one by one with retry mechanism
        function uploadFilesSequentially(files, index, retryCount = 0) {
            if (index >= files.length) {
                showUploadProgress(false);
                return;
            }
            
            const file = files[index];
            const maxRetries = 3;
            console.log(`Uploading file ${index + 1}/${files.length}:`, file.name, `(attempt ${retryCount + 1})`);
            
            // Update progress display
            showUploadProgress(true, index + 1, files.length);
            
            // Create form data
            const formData = new FormData();
            formData.append('file', file);
            
            // Add timeout and abort controller for better control
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout
            
            // Upload file with retry logic
            fetch('ajax/chat_handler.php?action=upload_file', {
                method: 'POST',
                body: formData,
                signal: controller.signal,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                clearTimeout(timeoutId);
                console.log(`File ${index + 1} upload response:`, response);
                console.log(`File ${index + 1} response status:`, response.status);
                console.log(`File ${index + 1} response ok:`, response.ok);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // If not JSON, get text and try to parse
                    return response.text().then(text => {
                        console.log(`File ${index + 1} response text:`, text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error(`Invalid JSON response: ${text}`);
                        }
                    });
                }
            })
            .then(data => {
                console.log(`File ${index + 1} upload data:`, data);
                if (data.success) {
                    // Send file message
                    sendFileMessage(data.file_path, data.file_name, data.file_type);
                    console.log(`File ${index + 1} uploaded successfully`);
                    // Upload next file
                    setTimeout(() => {
                        uploadFilesSequentially(files, index + 1);
                    }, 500); // Small delay between uploads
                } else {
                    console.error(`File ${index + 1} upload failed:`, data.error);
                    alert(`File "${file.name}" upload failed: ${data.error}`);
                    // Continue with next file even if this one failed
                    setTimeout(() => {
                        uploadFilesSequentially(files, index + 1);
                    }, 500);
                }
            })
            .catch(error => {
                clearTimeout(timeoutId);
                console.error(`File ${index + 1} upload error:`, error);
                console.error(`File ${index + 1} error stack:`, error.stack);
                
                // Retry logic
                if (retryCount < maxRetries && (error.name === 'AbortError' || error.message.includes('Failed to fetch'))) {
                    console.log(`Retrying file ${index + 1} upload (attempt ${retryCount + 2})`);
                    setTimeout(() => {
                        uploadFilesSequentially(files, index, retryCount + 1);
                    }, 1000 * (retryCount + 1)); // Exponential backoff
                } else {
                    alert(`File "${file.name}" upload failed after ${maxRetries + 1} attempts: ${error.message}`);
                    // Continue with next file
                    setTimeout(() => {
                        uploadFilesSequentially(files, index + 1);
                    }, 500);
                }
            });
        }
        
        // Single file upload handler (kept for compatibility)
        function handleFileUpload(file) {
            handleMultipleFileUpload([file]);
        }
        
        // File validation
        function validateFile(file) {
            // Check file size (1GB limit for large files like ZIP)
            const maxSize = 1024 * 1024 * 1024;
            if (file.size > maxSize) {
                alert('File size must be less than 1GB');
                return false;
            }
            
            // Check file type - More comprehensive list
            const allowedTypes = [
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
            const allowedExtensions = [
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
            if (allowedTypes.includes(file.type)) {
                return true;
            }
            
            // Check file extension as fallback
            const fileName = file.name.toLowerCase();
            const fileExtension = fileName.split('.').pop();
            if (fileExtension && allowedExtensions.includes(fileExtension)) {
                return true;
            }
            
            // If neither MIME type nor extension matches, show error
            alert('File type not supported. Supported formats: images, documents, text files, archives, audio, video, and many other common file types. Please try a different file or contact support if you need to upload a specific file type.');
            return false;
        }
        
        // Show/hide upload progress
        function showUploadProgress(show, currentFile = 0, totalFiles = 0) {
            const sendBtn = document.getElementById('sendBtn');
            if (show) {
                if (totalFiles > 1) {
                    sendBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${currentFile}/${totalFiles}`;
                } else {
                    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                }
                sendBtn.disabled = true;
            } else {
                sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
                sendBtn.disabled = false;
            }
        }
        
        // Send file message
        function sendFileMessage(filePath, fileName, fileType) {
            console.log('Sending file message:', filePath, fileName, fileType);
            
            const data = {
                file_path: filePath,
                file_name: fileName,
                file_type: fileType
            };
            
            if (window.currentChatType === 'user') {
                data.receiver_id = window.currentChatId;
            } else {
                data.group_id = window.currentChatId;
                // Store group message update
                storeGroupMessageUpdate(window.currentChatId);
            }
            
            fetch('ajax/chat_handler.php?action=send_file_message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('File message sent successfully');
                    // Reload messages
                    loadMessages(window.currentChatId, window.currentChatType);
                } else {
                    alert('Failed to send file: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Send file error:', error);
                alert('Failed to send file: ' + error.message);
            });
        }
        
        // Image modal functionality
        function openImageModal(imageSrc) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            
            modal.style.display = 'block';
            modalImg.src = imageSrc;
        }
        
        // Close image modal
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this || e.target.classList.contains('image-modal-close')) {
                this.style.display = 'none';
            }
        });
        
        // ===== END NEW FILE UPLOAD FUNCTIONALITY =====
        
        async function sendMessage(message) {
            if (!message || !window.currentChatId || !['user', 'group'].includes(window.currentChatType)) {
                alert('Vui lòng chọn một cuộc trò chuyện hợp lệ.');
                return false;
            }

            const sendBtn = document.getElementById('sendBtn');
            const input = document.getElementById('messageInput');
            const originalButtonHtml = sendBtn ? sendBtn.innerHTML : '';
            const data = { message: message, message_type: 'text' };
            if (window.currentChatType === 'user') {
                data.receiver_id = Number(window.currentChatId);
            } else {
                data.group_id = Number(window.currentChatId);
                storeGroupMessageUpdate(window.currentChatId);
            }

            try {
                if (sendBtn) {
                    sendBtn.disabled = true;
                    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                }
                const response = await fetch((window.BASE_URL || '') + 'ajax/chat_handler.php?action=send_message', {
                    method: 'POST',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                const raw = await response.text();
                let result;
                try {
                    result = JSON.parse(raw);
                } catch (parseError) {
                    throw new Error('Máy chủ không trả JSON hợp lệ (HTTP ' + response.status + '). Hãy kiểm tra PHP error log.');
                }
                if (!response.ok || !result.success) {
                    throw new Error(result.error || ('Không gửi được tin nhắn (HTTP ' + response.status + ').'));
                }

                if (input) {
                    input.value = '';
                    input.style.height = 'auto';
                }
                window.loadMessages(window.currentChatId, window.currentChatType);
                return true;
            } catch (error) {
                console.error('Error sending message:', error);
                alert(error.message || 'Không thể gửi tin nhắn. Vui lòng thử lại.');
                return false;
            } finally {
                if (sendBtn) {
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = originalButtonHtml || '<i class="fas fa-paper-plane"></i>';
                }
            }
        }

        // Store group message update
        function storeGroupMessageUpdate(groupId) {
            try {
                const messageUpdates = JSON.parse(localStorage.getItem('groupMessageUpdates') || '{}');
                messageUpdates[groupId] = {
                    timestamp: Date.now()
                };
                localStorage.setItem('groupMessageUpdates', JSON.stringify(messageUpdates));
            } catch (e) {
                console.error('Error storing message update:', e);
            }
        }
        
        // Store group member update
        function storeGroupMemberUpdate(groupId) {
            try {
                const memberUpdates = JSON.parse(localStorage.getItem('groupMemberUpdates') || '{}');
                memberUpdates[groupId] = {
                    timestamp: Date.now()
                };
                localStorage.setItem('groupMemberUpdates', JSON.stringify(memberUpdates));
            } catch (e) {
                console.error('Error storing member update:', e);
            }
        }
        
        // Check group message updates
        function checkForGroupMessageUpdates() {
            try {
                const messageUpdates = JSON.parse(localStorage.getItem('groupMessageUpdates') || '{}');
                const now = Date.now();
                let changed = false;
                
                Object.keys(messageUpdates).forEach(groupId => {
                    const update = messageUpdates[groupId];
                    if (now - update.timestamp < 300000) { // 5 minutes
                        if (window.currentChatId == groupId && window.currentChatType === 'group') {
                            window.loadMessages(groupId, 'group', { silent: true });
                            changed = true;
                        }
                    }
                });
                
                if (changed) {
                    localStorage.setItem('groupMessageUpdates', '{}');
                    return;
                }
                // Clean up expired updates
                const filteredUpdates = {};
                Object.keys(messageUpdates).forEach(groupId => {
                    const update = messageUpdates[groupId];
                    if (now - update.timestamp < 300000) {
                        filteredUpdates[groupId] = update;
                    }
                });
                localStorage.setItem('groupMessageUpdates', JSON.stringify(filteredUpdates));
            } catch (e) {
                console.error('Error checking message updates:', e);
            }
        }
        
        // Check group member updates
        function checkForGroupMemberUpdates() {
            try {
                const memberUpdates = JSON.parse(localStorage.getItem('groupMemberUpdates') || '{}');
                const now = Date.now();
                
                // Process member updates within 1 hour
                Object.keys(memberUpdates).forEach(groupId => {
                    const update = memberUpdates[groupId];
                    if (now - update.timestamp < 3600000) { // 1 hour
                        if (window.currentChatId == groupId && window.currentChatType === 'group') {
                            // If current group, may need UI update
                            console.log('Group member update detected for current group');
                        }
                    }
                });
                
                // Clean up expired updates
                const filteredUpdates = {};
                Object.keys(memberUpdates).forEach(groupId => {
                    const update = memberUpdates[groupId];
                    if (now - update.timestamp < 3600000) {
                        filteredUpdates[groupId] = update;
                    }
                });
                localStorage.setItem('groupMemberUpdates', JSON.stringify(filteredUpdates));
            } catch (e) {
                console.error('Error checking member updates:', e);
            }
        }
        
        // Listen to localStorage changes for multi-tab sync
        window.addEventListener('storage', function(e) {
            if (e.key === 'groupAvatarUpdates') {
                checkForGroupAvatarUpdates();
            } else if (e.key === 'groupMessageUpdates') {
                checkForGroupMessageUpdates();
            } else if (e.key === 'groupMemberUpdates') {
                checkForGroupMemberUpdates();
            }
        });
        
        // Create group functionality is handled by navbar.php
        
        // Welcome screen Add Friend button
        const welcomeAddFriendBtn = document.querySelector('.add-friend-btn');
        if (welcomeAddFriendBtn) {
            welcomeAddFriendBtn.addEventListener('click', function() {
                showAddFriendModal();
            });
        }
        
        // Add Friend modal functionality (for welcome screen)
        function showAddFriendModal() {
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.id = 'addFriendModal';
            modal.setAttribute('tabindex', '-1');
            modal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add Friend</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="friendUsername" class="form-label">Username</label>
                                <input type="text" class="form-control" id="friendUsername" placeholder="Enter username">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="sendFriendRequest">Send Request</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Show modal
            const bootstrapModal = new bootstrap.Modal(modal);
            bootstrapModal.show();
            
            // Handle send friend request
            document.getElementById('sendFriendRequest').addEventListener('click', function() {
                const username = document.getElementById('friendUsername').value.trim();
                if (username) {
                    sendFriendRequest(username);
                    bootstrapModal.hide();
                } else {
                    alert('Please enter a username');
                }
            });
            
            // Remove modal from DOM when hidden
            modal.addEventListener('hidden.bs.modal', function() {
                document.body.removeChild(modal);
            });
        }
        
        // Send friend request function
        function sendFriendRequest(username) {
            fetch('ajax/friend_handler.php?action=send_request', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ username: username })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Friend request sent successfully!');
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error sending friend request:', error);
                alert('Error sending friend request. Please try again.');
            });
        }
        
        // Check for group avatar updates from localStorage
        function checkForGroupAvatarUpdates() {
            try {
                const avatarUpdates = JSON.parse(localStorage.getItem('groupAvatarUpdates') || '{}');
                const now = Date.now();
                
                // Process updates that are less than 1 hour old
                Object.keys(avatarUpdates).forEach(groupId => {
                    const update = avatarUpdates[groupId];
                    if (now - update.timestamp < 3600000) { // 1 hour
                        updateGroupAvatarInSidebar(groupId, update.avatarPath);
                    }
                });
                
                // Clear old updates
                const filteredUpdates = {};
                Object.keys(avatarUpdates).forEach(groupId => {
                    const update = avatarUpdates[groupId];
                    if (now - update.timestamp < 3600000) {
                        filteredUpdates[groupId] = update;
                    }
                });
                localStorage.setItem('groupAvatarUpdates', JSON.stringify(filteredUpdates));
            } catch (e) {
                console.error('Error checking avatar updates:', e);
            }
        }
        
        // Update group avatar in sidebar
        function updateGroupAvatarInSidebar(groupId, avatarPath) {
            // Find the group item in the sidebar
            const groupItems = document.querySelectorAll('[data-group-id="' + groupId + '"]');
            groupItems.forEach(groupItem => {
                const avatarContainer = groupItem.querySelector('.group-avatar');
                if (avatarContainer) {
                    // Replace the entire avatar content with the new image
                    avatarContainer.innerHTML = '<img src="' + avatarPath + '?t=' + Date.now() + '" alt="Group Avatar" class="avatar-circle" style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%;">';
                }
            });
        }
        
        // Private voice call: create call and open call page
        document.getElementById('voiceCallBtn').addEventListener('click', function() {
            if (!window.currentChatId || window.currentChatType !== 'user') {
                alert('Please select a friend to chat first');
                return;
            }
            const calleeId = window.currentChatId;
            const formData = new FormData();
            formData.append('action', 'create');
            formData.append('callee_id', calleeId);
            fetch((window.BASE_URL || '') + 'ajax/voice_call_handler.php', { method: 'POST', body: formData, credentials: 'include' })
                .then(r => r.json())
                .then(data => {
                    if (data.error) { alert(data.error); return; }
                    window.open((window.BASE_URL || '') + 'voice_call.php?room_id=' + encodeURIComponent(data.room_id) + '&role=caller&other_id=' + calleeId, 'voice_' + data.room_id, 'width=420,height=380,scrollbars=no');
                })
                .catch(e => alert('Failed to start call: ' + e.message));
        });

        // Incoming call polling and popup
        let lastIncomingRoomId = '';
        setInterval(function() {
            fetch((window.BASE_URL || '') + 'ajax/voice_call_handler.php?action=incoming', { credentials: 'include' })
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.call || data.call.room_id === lastIncomingRoomId) return;
                    lastIncomingRoomId = data.call.room_id;
                    document.getElementById('incomingCallText').textContent = data.call.caller_name + ' invites you to a voice call';
                    const modal = new bootstrap.Modal(document.getElementById('incomingCallModal'));
                    modal.show();
                    document.getElementById('incomingCallAccept').onclick = function() {
                        const fd = new FormData();
                        fd.append('action', 'accept');
                        fd.append('room_id', data.call.room_id);
                        fetch((window.BASE_URL || '') + 'ajax/voice_call_handler.php', { method: 'POST', body: fd, credentials: 'include' })
                            .then(r => r.json())
                            .then(function(res) {
                                if (res.error) { alert(res.error); return; }
                                modal.hide();
                                window.open((window.BASE_URL || '') + 'voice_call.php?room_id=' + encodeURIComponent(data.call.room_id) + '&role=callee&other_id=' + data.call.caller_id, 'voice_' + data.call.room_id, 'width=420,height=380,scrollbars=no');
                            });
                    };
                    document.getElementById('incomingCallReject').onclick = function() {
                        const fd = new FormData();
                        fd.append('action', 'reject');
                        fd.append('room_id', data.call.room_id);
                        fetch((window.BASE_URL || '') + 'ajax/voice_call_handler.php', { method: 'POST', body: fd, credentials: 'include' });
                        modal.hide();
                    };
                })
                .catch(function() {});
        }, 2500);
        
        document.getElementById('videoCallBtn').addEventListener('click', function() {
            if (!window.currentChatId || window.currentChatType !== 'user') {
                alert('Please select a friend to chat first');
                return;
            }
            
            // Video call logic can be added here
            console.log('Starting video call, target user ID:', window.currentChatId);
            alert('Video call feature is under development...');
        });
        
        // Group call button event handler
        document.getElementById('groupVoiceCallBtn').addEventListener('click', function() {
            if (!window.currentChatId || window.currentChatType !== 'group') {
                alert('Please select a group to chat first');
                return;
            }
            
            // Group voice call logic can be added here
            console.log('Starting group voice call, group ID:', window.currentChatId);
            alert('Group voice call feature is under development...');
        });
        
        document.getElementById('groupVideoCallBtn').addEventListener('click', function() {
            if (!window.currentChatId || window.currentChatType !== 'group') {
                alert('Please select a group to chat first');
                return;
            }
            
            // Group video call logic can be added here
            console.log('Starting group video call, group ID:', window.currentChatId);
            alert('GroupVideo call feature is under development...');
        });
        
        // Group menu feature
        document.addEventListener('click', function(e) {
            if (e.target.closest('#groupMenuBtn')) {
                e.stopPropagation();
                const groupId = window.currentChatId;
                const groupName = document.querySelector('#chatTitle h5').textContent.replace(/^[^\s]+\s/, '');
                console.log('Group menu clicked, groupId:', groupId, 'groupName:', groupName);
                showGroupMenu(groupId, groupName);
            }
        });
        
        // Show group menu
        function showGroupMenu(groupId, groupName) {
            // Remove any existing menu
            const existingMenu = document.querySelector('.group-menu');
            if (existingMenu) {
                existingMenu.remove();
            }
            
            const menu = document.createElement('div');
            menu.className = 'group-menu';
            menu.style.cssText = `
                position: fixed;
                background: white;
                border: 1px solid #e9ecef;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                z-index: 1000;
                min-width: 200px;
                padding: 8px 0;
            `;
            
            // Show basic menu first, then fetch user role async
            let menuItems = `
                <div class="menu-item" onclick="viewAllGroupMessages(${groupId})">
                    <i class="fas fa-history"></i>
                    <span>View All Messages</span>
                </div>
                <div class="menu-item" onclick="viewGroupMembers(${groupId})">
                    <i class="fas fa-users"></i>
                    <span>Group Members</span>
                </div>
                <div class="menu-item" onclick="viewGroupInfo(${groupId})">
                    <i class="fas fa-info-circle"></i>
                    <span>Group Info</span>
                </div>
            `;
            
            menu.innerHTML = menuItems;
            
            // Fetch user role async and update menu
            fetch(`ajax/group_handler.php?action=get_group_permissions&group_id=${groupId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && (data.permissions.can_change_avatar || data.permissions.can_invite_members)) {
                    // Only admins see management options
                    menuItems += `
                        <div class="menu-divider"></div>
                        <div class="menu-item" onclick="inviteFriendsToGroup(${groupId}, '${groupName}')">
                            <i class="fas fa-user-plus"></i>
                            <span>Invite Friends</span>
                        </div>
                        <div class="menu-item" onclick="updateGroupAvatar(${groupId})">
                            <i class="fas fa-camera"></i>
                            <span>Change Group Avatar</span>
                        </div>
                    `;
                    menu.innerHTML = menuItems;
                }
            })
            .catch(error => {
                console.error('Error checking group permissions:', error);
            });
            
            // Position menu near the clicked button
            const button = document.getElementById('groupMenuBtn');
            const rect = button.getBoundingClientRect();
            menu.style.left = (rect.right - 200) + 'px';
            menu.style.top = (rect.bottom + 5) + 'px';
            
            // Add menu item styles
            const style = document.createElement('style');
            style.textContent = `
                .menu-item {
                    display: flex;
                    align-items: center;
                    padding: 10px 16px;
                    cursor: pointer;
                    transition: background-color 0.2s ease;
                    font-size: 14px;
                    color: #333;
                }
                .menu-item:hover {
                    background-color: #f8f9fa;
                }
                .menu-item i {
                    width: 20px;
                    margin-right: 12px;
                    font-size: 14px;
                    color: #6c757d;
                }
                .menu-divider {
                    height: 1px;
                    background-color: #e9ecef;
                    margin: 4px 0;
                }
            `;
            document.head.appendChild(style);
            
            document.body.appendChild(menu);
            
            // Close menu when clicking outside
            setTimeout(() => {
                document.addEventListener('click', function closeMenu(e) {
                    if (!menu.contains(e.target)) {
                        menu.remove();
                        style.remove();
                        document.removeEventListener('click', closeMenu);
                    }
                });
            }, 0);
        }
        
        // Group menu helper functions
        window.viewAllGroupMessages = function(groupId) {
            showAllGroupMessagesModal(groupId);
        };
        
        window.inviteFriendsToGroup = function(groupId, groupName) {
            showInviteFriendsModal(groupId, groupName);
        };
        
        window.updateGroupAvatar = function(groupId) {
            showUpdateGroupAvatarModal(groupId);
        };
        
        window.viewGroupMembers = function(groupId) {
            showGroupMembersModal(groupId);
        };
        
        window.viewGroupInfo = function(groupId) {
            fetch('ajax/group_handler.php?action=get_group_info&group_id=' + groupId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showGroupInfoModal(data.group);
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error loading group info:', error);
                alert('Failed to load group info');
            });
        };
        
        // Show change group avatar modal
        function showUpdateGroupAvatarModal(groupId) {
            if (typeof bootstrap === 'undefined') {
                alert('Bootstrap is not loaded yet. Please wait a moment and try again.');
                return;
            }
            
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.id = 'updateGroupAvatarModal';
            modal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Change Group Avatar</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="avatarForm" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="avatarFile" class="form-label">Select Avatar</label>
                                    <input type="file" class="form-control" id="avatarFile" accept="image/*" required>
                                    <div class="form-text">Supports JPG, PNG, GIF, WebP formats, max 5MB</div>
                                </div>
                                <div class="mb-3">
                                    <div id="avatarPreview" style="text-align: center; margin-top: 15px;"></div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="updateAvatarBtn">Update Avatar</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bootstrapModal = new bootstrap.Modal(modal);
            bootstrapModal.show();
            
            // Handle file preview
            document.getElementById('avatarFile').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('avatarPreview').innerHTML = `
                            <img src="${e.target.result}" style="max-width: 200px; max-height: 200px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        `;
                    };
                    reader.readAsDataURL(file);
                }
            });
            
            // Handle update avatar
            document.getElementById('updateAvatarBtn').addEventListener('click', function() {
                const fileInput = document.getElementById('avatarFile');
                const file = fileInput.files[0];
                
                if (!file) {
                    alert('Please select an avatar file');
                    return;
                }
                
                const formData = new FormData();
                formData.append('avatar', file);
                formData.append('group_id', groupId);
                
                // Show loading
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                this.disabled = true;
                
                fetch('ajax/group_handler.php?action=update_group_avatar', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Group avatar updated successfully!');
                        bootstrapModal.hide();
                        
                        // Update avatar in sidebar and chat header
                        updateGroupAvatarInUI(groupId, data.avatar_path);
                    } else {
                        alert('Update failed: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error updating avatar:', error);
                    alert('Update failed, please try again');
                })
                .finally(() => {
                    this.innerHTML = 'Update Avatar';
                    this.disabled = false;
                });
            });
            
            modal.addEventListener('hidden.bs.modal', function() {
                document.body.removeChild(modal);
            });
        }
        
        // Update group avatar in UI
        function updateGroupAvatarInUI(groupId, avatarPath) {
            // Update sidebar avatar
            updateGroupAvatarInSidebar(groupId, avatarPath);
            
            // Store update for future page loads
            try {
                const avatarUpdates = JSON.parse(localStorage.getItem('groupAvatarUpdates') || '{}');
                avatarUpdates[groupId] = {
                    avatarPath: avatarPath,
                    timestamp: Date.now()
                };
                localStorage.setItem('groupAvatarUpdates', JSON.stringify(avatarUpdates));
            } catch (e) {
                console.error('Error storing avatar update:', e);
            }
        }
        
        // Show group info modal
        function showGroupInfoModal(group) {
            if (typeof bootstrap === 'undefined') {
                alert('Bootstrap is not loaded yet. Please wait a moment and try again.');
                return;
            }
            
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.id = 'groupInfoModal';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Group Info</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-4 text-center">
                                    <div class="group-avatar mb-3">
                                        ${group.avatar && group.avatar !== 'default_group_avatar.png' ? 
                                            `<img src="${group.avatar}?t=${Date.now()}" class="img-fluid rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">` :
                                            `<div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 120px; height: 120px; font-size: 48px; margin: 0 auto;">${group.name.charAt(0).toUpperCase()}</div>`
                                        }
                                    </div>
                                    <h4>${group.name}</h4>
                                    <p class="text-muted">Group</p>
                                </div>
                                <div class="col-md-8">
                                    <h5>Basic Information</h5>
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Group Name:</strong></td>
                                            <td>${group.name}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Description:</strong></td>
                                            <td>${group.description || 'No description'}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Member Count:</strong></td>
                                            <td>${group.member_count || 0} members</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Created At:</strong></td>
                                            <td>${group.created_at ? new Date(group.created_at).toLocaleDateString() : 'Unknown'}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bootstrapModal = new bootstrap.Modal(modal);
            bootstrapModal.show();
            
            modal.addEventListener('hidden.bs.modal', function() {
                document.body.removeChild(modal);
            });
        }
        
        // Show all group messages modal
        function showAllGroupMessagesModal(groupId) {
            if (typeof bootstrap === 'undefined') {
                alert('Bootstrap is not loaded yet. Please wait a moment and try again.');
                return;
            }
            
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.id = 'allGroupMessagesModal';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">All Group Messages</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="allMessagesContainer" style="max-height: 500px; overflow-y: auto;">
                                <div class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i> Loading messages...
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bootstrapModal = new bootstrap.Modal(modal);
            bootstrapModal.show();
            
            // Load all messages
            loadAllGroupMessages(groupId);
            
            modal.addEventListener('hidden.bs.modal', function() {
                document.body.removeChild(modal);
            });
        }
        
        // Load all group messages
        function loadAllGroupMessages(groupId) {
            fetch(`ajax/chat_handler.php?action=get_all_messages&group_id=${groupId}`)
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('allMessagesContainer');
                if (data.success && data.messages) {
                    container.innerHTML = '';
                    data.messages.forEach(message => {
                        const messageDiv = createMessageElement(message);
                        container.appendChild(messageDiv);
                    });
                } else {
                    container.innerHTML = '<div class="text-center text-muted">No messages yet</div>';
                }
            })
            .catch(error => {
                console.error('Error loading messages:', error);
                document.getElementById('allMessagesContainer').innerHTML = '<div class="text-center text-danger">Failed to load messages</div>';
            });
        }
        
        // Show invite friends modal
        function showInviteFriendsModal(groupId, groupName) {
            if (typeof bootstrap === 'undefined') {
                alert('Bootstrap is not loaded yet. Please wait a moment and try again.');
                return;
            }
            
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.id = 'inviteFriendsModal';
            modal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Invite Friends to "${groupName}"</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="friendSearch" class="form-label">Search Friends</label>
                                <input type="text" class="form-control" id="friendSearch" placeholder="Enter friend username">
                            </div>
                            <div id="inviteFriendsList" class="friends-list" style="max-height: 300px; overflow-y: auto;">
                                <div class="text-center text-muted">Enter username to search friends</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bootstrapModal = new bootstrap.Modal(modal);
            bootstrapModal.show();
            
            // Handle friend search (on input + Enter)
            const friendSearchEl = document.getElementById('friendSearch');
            if (friendSearchEl) {
                function doSearch() {
                    const searchTerm = friendSearchEl.value.trim();
                    if (searchTerm.length >= 1) {
                        searchFriendsForInvite(searchTerm, groupId);
                    } else {
                        const listEl = document.getElementById('inviteFriendsList');
                        if (listEl) listEl.innerHTML = '<div class="text-center text-muted">Enter username to search friends</div>';
                    }
                }
                friendSearchEl.addEventListener('input', doSearch);
                friendSearchEl.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
                });
            }
            
            modal.addEventListener('hidden.bs.modal', function() {
                document.body.removeChild(modal);
            });
        }
        
        // Search friends for invitation
        function searchFriendsForInvite(searchTerm, groupId) {
            const container = document.getElementById('inviteFriendsList');
            if (!container) return;
            container.innerHTML = '<div class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
            fetch(`ajax/friend_handler.php?action=search_friends&search=${encodeURIComponent(searchTerm)}`)
            .then(response => response.json())
            .then(data => {
                const listEl = document.getElementById('inviteFriendsList');
                if (!listEl) return;
                if (data.success && data.friends) {
                    listEl.innerHTML = '';
                    data.friends.forEach(friend => {
                        const friendDiv = document.createElement('div');
                        friendDiv.className = 'friend-item d-flex align-items-center p-2 border-bottom';
                        friendDiv.innerHTML = `
                            <div class="friend-avatar me-3">
                                <div class="avatar-circle ${friend.status === 'online' ? 'green' : 'purple'}">
                                    ${friend.avatar && friend.avatar !== 'default_avatar.png' ? 
                                        `<img src="uploads/avatars/${friend.avatar}" class="friend-avatar-image">` :
                                        (friend.username && typeof friend.username === 'string' ? friend.username.charAt(0) : 'U').toUpperCase()
                                    }
                                </div>
                            </div>
                            <div class="friend-info flex-grow-1">
                                <div class="friend-name">${friend.full_name || friend.username}</div>
                                <div class="friend-status">${friend.status === 'online' ? 'Online' : 'Offline'}</div>
                            </div>
                            <button class="btn btn-primary btn-sm" onclick="inviteFriendToGroup(${friend.id}, ${groupId})">
                                <i class="fas fa-user-plus"></i> Invite
                            </button>
                        `;
                        listEl.appendChild(friendDiv);
                    });
                } else {
                    listEl.innerHTML = '<div class="text-center text-muted">No friends found</div>';
                }
            })
            .catch(error => {
                console.error('Error searching friends:', error);
                const listEl = document.getElementById('inviteFriendsList');
                if (listEl) listEl.innerHTML = '<div class="text-center text-danger">Search failed</div>';
            });
        }
        
        // Invite friends to group
        window.inviteFriendToGroup = function(friendId, groupId) {
            fetch('ajax/group_handler.php?action=invite_friend', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    friend_id: friendId,
                    group_id: groupId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Invitation sent!');
                    // Store member update
                    storeGroupMemberUpdate(groupId);
                } else {
                    alert('Invitation failed: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error inviting friend:', error);
                alert('Invitation failed, please try again');
            });
        };
        
        // Show group members modal
        function showGroupMembersModal(groupId) {
            if (typeof bootstrap === 'undefined') {
                alert('Bootstrap is not loaded yet. Please wait a moment and try again.');
                return;
            }
            
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.id = 'groupMembersModal';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Group Members</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="membersList" class="members-list">
                                <div class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i> Loading members...
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bootstrapModal = new bootstrap.Modal(modal);
            bootstrapModal.show();
            
            // Load group members
            loadGroupMembers(groupId);
            
            modal.addEventListener('hidden.bs.modal', function() {
                document.body.removeChild(modal);
            });
        }
        
        // Load group members
        function loadGroupMembers(groupId) {
            fetch(`ajax/group_handler.php?action=get_group_members&group_id=${groupId}`)
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('membersList');
                if (data.success && data.members) {
                    container.innerHTML = '';
                    data.members.forEach(member => {
                        const memberDiv = document.createElement('div');
                        memberDiv.className = 'member-item d-flex align-items-center p-3 border-bottom';
                        memberDiv.innerHTML = `
                            <div class="member-avatar me-3">
                                <div class="avatar-circle ${member.status === 'online' ? 'green' : 'purple'}">
                                    ${member.avatar && member.avatar !== 'default_avatar.png' ? 
                                        `<img src="uploads/avatars/${member.avatar}" class="member-avatar-image" alt="${member.username}">` :
                                        member.username.charAt(0).toUpperCase()
                                    }
                                </div>
                            </div>
                            <div class="member-info flex-grow-1">
                                <div class="member-name">${member.full_name || member.username}</div>
                                <div class="member-role">
                                    <span class="badge ${member.role === 'admin' ? 'bg-danger' : 'bg-secondary'}">${member.role === 'admin' ? 'Admin' : 'Members'}</span>
                                    <span class="text-muted">${member.status === 'online' ? 'Online' : 'Offline'}</span>
                                </div>
                            </div>
                        `;
                        container.appendChild(memberDiv);
                    });
                } else {
                    container.innerHTML = '<div class="text-center text-muted">Failed to load members</div>';
                }
            })
            .catch(error => {
                console.error('Error loading members:', error);
                document.getElementById('membersList').innerHTML = '<div class="text-center text-danger">Failed to load members</div>';
            });
        }
        
        // Check for updates when page loads
        document.addEventListener('DOMContentLoaded', function() {
            checkForGroupAvatarUpdates();
            checkForGroupMessageUpdates();
            checkForGroupMemberUpdates();
        });
        
        // Also check when window gains focus (user returns from group.php)
        window.addEventListener('focus', function() {
            checkForGroupAvatarUpdates();
            checkForGroupMessageUpdates();
            checkForGroupMemberUpdates();
        });
        
        // Format file size
        function formatFileSize(bytes) {
            if (bytes >= 1073741824) {
                return (bytes / 1073741824).toFixed(2) + ' GB';
            } else if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            } else {
                return bytes + ' bytes';
            }
        }
        
        // Get consecutive images from the same sender
        function getConsecutiveImages(currentMessage) {
            // This is a simplified version for JavaScript
            // In a real implementation, you would need access to all messages
            // For now, we'll return just the current message
            return [currentMessage];
        }
        
        // Render image grid
        function renderImageGrid(imageGroup) {
            const count = imageGroup.length;
            let gridClass = '';
            
            // Determine grid layout based on image count
            if (count === 1) {
                gridClass = 'single';
            } else if (count === 2) {
                gridClass = 'double';
            } else if (count === 3) {
                gridClass = 'triple';
            } else if (count === 4) {
                gridClass = 'quad';
            } else {
                gridClass = 'many';
            }
            
            let html = '<div class="image-grid ' + gridClass + '">';
            
            // Limit display to 9 images maximum
            const displayCount = Math.min(count, 9);
            
            for (let i = 0; i < displayCount; i++) {
                let imagePath = imageGroup[i].file_path;
                if (imagePath && imagePath.startsWith('uploads/') && !imagePath.startsWith('ajax/')) {
                    imagePath = 'ajax/' + imagePath;
                }
                html += '<div class="image-item" onclick="openImageModal(\'' + imagePath + '\')">';
                html += '<img src="' + imagePath + '" alt="Image">';
                html += '</div>';
            }
            
            // Add "more" overlay if there are more than 9 images
            if (count > 9) {
                let lastImagePath = imageGroup[8].file_path;
                if (lastImagePath.startsWith('uploads/') && !lastImagePath.startsWith('ajax/')) {
                    lastImagePath = 'ajax/' + lastImagePath;
                }
                html += '<div class="image-item more-overlay" onclick="openImageModal(\'' + lastImagePath + '\')">';
                html += '<img src="' + lastImagePath + '" alt="Image">';
                html += '</div>';
            }
            
            html += '</div>';
            
            return html;
        }
        
        // Voice volume control function
        function setAudioVolume(slider, audioElement) {
            if (audioElement && audioElement.volume !== undefined) {
                audioElement.volume = parseFloat(slider.value);
                
                // Update volume icon based on volume level
                const volumeIcon = slider.previousElementSibling;
                if (parseFloat(slider.value) === 0) {
                    volumeIcon.className = 'fas fa-volume-mute volume-icon';
                } else if (parseFloat(slider.value) < 0.5) {
                    volumeIcon.className = 'fas fa-volume-down volume-icon';
                } else {
                    volumeIcon.className = 'fas fa-volume-up volume-icon';
                }
            }
        }
        
        // Initialize audio volume enhancement
        function initializeAudioVolumeEnhancement() {
            // Add event listeners to all audio elements for volume enhancement
            document.addEventListener('DOMContentLoaded', function() {
                const audioElements = document.querySelectorAll('audio');
                audioElements.forEach(audio => {
                    // Set default volume to 0.8 (80%)
                    audio.volume = 0.8;
                    
                    // Add volume enhancement on play
                    audio.addEventListener('play', function() {
                        // Apply volume boost if needed
                        if (this.volume < 0.8) {
                            this.volume = 0.8;
                            // Update corresponding slider if exists
                            const volumeControl = this.parentElement.querySelector('.voice-volume-control input[type="range"]');
                            if (volumeControl) {
                                volumeControl.value = 0.8;
                                setAudioVolume(volumeControl, this);
                            }
                        }
                    });
                });
            });
        }
        
        // Call initialization
        initializeAudioVolumeEnhancement();
        
        // Voice recording functions
        async function toggleVoiceRecording() {
            if (!recording) {
                await startVoiceRecording();
            } else {
                stopVoiceRecording();
            }
        }
        
        async function startVoiceRecording() {
            try {
                // Request higher volume audio stream
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true,
                        volume: 1.0
                    }
                });
                
                // Try to set audio track volume gain
                const audioTracks = stream.getAudioTracks();
                audioTracks.forEach(track => {
                    if (track.applyConstraints) {
                        track.applyConstraints({
                            volume: 1.0,
                            sampleRate: 44100
                        }).catch(e => console.log('Audio constraints not supported:', e));
                    }
                });
                
                mediaRecorder = new MediaRecorder(stream, {
                    mimeType: 'audio/webm;codecs=opus'
                });
                audioChunks = [];
                
                mediaRecorder.ondataavailable = (event) => {
                    audioChunks.push(event.data);
                };
                
                mediaRecorder.onstop = async () => {
                    const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                    const reader = new FileReader();
                    reader.onload = async () => {
                        const base64 = reader.result.split(',')[1];
                        await sendVoiceMessage(base64);
                    };
                    reader.readAsDataURL(audioBlob);
                };
                
                mediaRecorder.start();
                recording = true;
                
                const voiceBtn = document.getElementById('voiceBtn');
                if (voiceBtn) {
                    voiceBtn.innerHTML = '<i class="fas fa-stop"></i>';
                    voiceBtn.classList.add('btn-danger');
                }
                
            } catch (error) {
                console.error('Error starting voice recording:', error);
                alert('Could not access microphone. Please check permissions.');
            }
        }
        
        function stopVoiceRecording() {
            if (mediaRecorder && recording) {
                mediaRecorder.stop();
                recording = false;
                
                const voiceBtn = document.getElementById('voiceBtn');
                if (voiceBtn) {
                    voiceBtn.innerHTML = '<i class="fas fa-microphone"></i>';
                    voiceBtn.classList.remove('btn-danger');
                }
            }
        }
        
        async function sendVoiceMessage(audioData) {
            try {
                const response = await fetch('ajax/chat_handler.php?action=record_voice', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        audio_data: audioData
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    await sendVoiceFileMessage(result.file_path, 'audio/webm', 'Voice Message');
                }
            } catch (error) {
                console.error('Error sending voice message:', error);
            }
        }
        
        async function sendVoiceFileMessage(filePath, fileType, fileName) {
            let messageType = 'file';
            if (fileType.startsWith('image/')) {
                messageType = 'image';
            } else if (fileType.startsWith('audio/')) {
                messageType = 'voice';
            }
            
            const data = {
                message: fileName,
                message_type: messageType,
                file_path: filePath
            };
            
            // Add recipient based on chat type
            if (window.currentChatType === 'user') {
                data.receiver_id = window.currentChatId;
            } else if (window.currentChatType === 'group') {
                data.group_id = window.currentChatId;
            }
            
            try {
                const response = await fetch((window.BASE_URL || '') + 'ajax/chat_handler.php?action=send_message', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Reload messages to show the new voice message
                    if (window.currentChatType === 'user') {
                        loadMessages(window.currentChatId, 'user');
                    } else if (window.currentChatType === 'group') {
                        loadMessages(window.currentChatId, 'group');
                    }
                }
            } catch (error) {
                console.error('Error sending voice file:', error);
            }
        }
        // Enhanced chat functions
        function replyToMessage(messageId) {
            const replyContent = prompt('Enter your reply:');
            if (!replyContent) return;
            
            const formData = new FormData();
            formData.append('action', 'reply_message');
            formData.append('message_id', messageId);
            formData.append('content', replyContent);
            formData.append('receiver_id', window.currentChatId);
            formData.append('group_id', '0'); // group_id is 0 in user chat
            
            fetch('ajax/enhanced_chat_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Reply sent successfully');
                    // Reload messages to show the reply
                    if (window.currentChatId && window.currentChatType) {
                        window.loadMessages(window.currentChatId, window.currentChatType);
                    }
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to send reply');
            });
        }
        
        // Global variables for forward modal
        let currentForwardMessageId = null;
        let selectedRecipients = [];
        
        function forwardMessage(messageId) {
            currentForwardMessageId = messageId;
            selectedRecipients = [];
            
            // Load friends and groups
            loadForwardRecipients();
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('forwardModal'));
            modal.show();
        }
        
        function loadForwardRecipients() {
            // Load friends
            const friendsList = document.getElementById('friendsList');
            friendsList.innerHTML = '';
            
            <?php foreach($friends as $friend): ?>
            const friendItem<?php echo $friend['id']; ?> = document.createElement('div');
            friendItem<?php echo $friend['id']; ?>.className = 'forward-item';
            friendItem<?php echo $friend['id']; ?>.innerHTML = `
                <input type="checkbox" id="friend_<?php echo $friend['id']; ?>" value="friend_<?php echo $friend['id']; ?>">
                <div class="avatar" style="background: linear-gradient(135deg, #3b82f6 0%, #93c5fd 100%);">
                    <?php echo strtoupper(substr($friend['full_name'] ?: $friend['username'], 0, 1)); ?>
                </div>
                <div class="info">
                    <p class="name"><?php echo htmlspecialchars($friend['full_name'] ?: $friend['username']); ?></p>
                    <p class="status"><?php echo $friend['status'] === 'online' ? 'Online' : 'Offline'; ?></p>
                </div>
            `;
            friendsList.appendChild(friendItem<?php echo $friend['id']; ?>);
            <?php endforeach; ?>
            
            // Load groups
            const groupsList = document.getElementById('groupsList');
            groupsList.innerHTML = '';
            
            <?php foreach($groups as $group): ?>
            const groupItem<?php echo $group['id']; ?> = document.createElement('div');
            groupItem<?php echo $group['id']; ?>.className = 'forward-item';
            groupItem<?php echo $group['id']; ?>.innerHTML = `
                <input type="checkbox" id="group_<?php echo $group['id']; ?>" value="group_<?php echo $group['id']; ?>">
                <div class="avatar" style="background: linear-gradient(135deg, #3b82f6 0%, #93c5fd 100%);">
                    <?php echo strtoupper(substr($group['name'], 0, 1)); ?>
                </div>
                <div class="info">
                    <p class="name"><?php echo htmlspecialchars($group['name']); ?></p>
                    <p class="status">Group</p>
                </div>
            `;
            groupsList.appendChild(groupItem<?php echo $group['id']; ?>);
            <?php endforeach; ?>
            
            // Add event listeners
            addForwardEventListeners();
        }
        
        function addForwardEventListeners() {
            // Add click event listeners to forward items
            document.querySelectorAll('.forward-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    if (e.target.type !== 'checkbox') {
                        const checkbox = this.querySelector('input[type="checkbox"]');
                        checkbox.checked = !checkbox.checked;
                        updateSelection(checkbox);
                    }
                });
            });
            
            // Add change event listeners to checkboxes
            document.querySelectorAll('.forward-item input[type="checkbox"]').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateSelection(this);
                });
            });
            
            // Add confirm button event listener
            document.getElementById('confirmForwardBtn').addEventListener('click', function() {
                confirmForward();
            });
        }
        
        function updateSelection(checkbox) {
            const value = checkbox.value;
            const isChecked = checkbox.checked;
            
            if (isChecked) {
                if (!selectedRecipients.includes(value)) {
                    selectedRecipients.push(value);
                }
            } else {
                const index = selectedRecipients.indexOf(value);
                if (index > -1) {
                    selectedRecipients.splice(index, 1);
                }
            }
            
            updateSelectedRecipientsDisplay();
            updateConfirmButton();
        }
        
        function updateSelectedRecipientsDisplay() {
            const container = document.getElementById('selectedRecipients');
            container.innerHTML = '';
            
            selectedRecipients.forEach(recipient => {
                const [type, id] = recipient.split('_');
                const item = document.createElement('span');
                item.className = 'selected-recipient';
                
                let name = '';
                if (type === 'friend') {
                    <?php foreach($friends as $friend): ?>
                    if (id == '<?php echo $friend['id']; ?>') {
                        name = '<?php echo htmlspecialchars($friend['full_name'] ?: $friend['username']); ?>';
                    }
                    <?php endforeach; ?>
                } else if (type === 'group') {
                    <?php foreach($groups as $group): ?>
                    if (id == '<?php echo $group['id']; ?>') {
                        name = '<?php echo htmlspecialchars($group['name']); ?>';
                    }
                    <?php endforeach; ?>
                }
                
                item.innerHTML = `${name} <span class="remove" onclick="removeRecipient('${recipient}')">&times;</span>`;
                container.appendChild(item);
            });
        }
        
        function removeRecipient(recipient) {
            const index = selectedRecipients.indexOf(recipient);
            if (index > -1) {
                selectedRecipients.splice(index, 1);
            }
            
            // Uncheck the checkbox
            const checkbox = document.getElementById(recipient);
            if (checkbox) {
                checkbox.checked = false;
            }
            
            updateSelectedRecipientsDisplay();
            updateConfirmButton();
        }
        
        function updateConfirmButton() {
            const btn = document.getElementById('confirmForwardBtn');
            btn.disabled = selectedRecipients.length === 0;
        }
        
        function confirmForward() {
            if (selectedRecipients.length === 0) {
                alert('Please select at least one recipient');
                return;
            }
            
            // Forward to each selected recipient
            let completedCount = 0;
            let successCount = 0;
            let totalCount = selectedRecipients.length;
            
            selectedRecipients.forEach((recipient) => {
                const [type, id] = recipient.split('_');
                console.log('Processing recipient:', recipient, 'Type:', type, 'ID:', id);
                
                const formData = new FormData();
                formData.append('action', 'forward_message');
                formData.append('message_id', currentForwardMessageId);
                
                if (type === 'friend') {
                    formData.append('receiver_id', id);
                    formData.append('group_id', '0');
                    console.log('Sending friend forward - receiver_id:', id, 'group_id: 0');
                } else if (type === 'group') {
                    formData.append('receiver_id', '0');
                    formData.append('group_id', id);
                    console.log('Sending group forward - receiver_id: 0, group_id:', id);
                }
                
                fetch('ajax/enhanced_chat_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    completedCount++;
                    console.log('Forward response for', recipient, ':', data);
                    if (data.success) {
                        successCount++;
                        console.log('Forward successful for', recipient);
                    } else {
                        console.error('Forward failed for', recipient, ':', data.error);
                    }
                    
                    // Check if all forwards are complete
                    if (completedCount === totalCount) {
                        if (successCount === totalCount) {
                            alert(`Message forwarded to ${successCount} recipient(s) successfully`);
                        } else {
                            alert(`Message forwarding complete, ${successCount}/${totalCount} succeeded`);
                        }
                        
                        // Reload messages to show the forwarded message
                        if (window.currentChatId && window.currentChatType) {
                            window.loadMessages(window.currentChatId, window.currentChatType);
                        }
                        
                        // Close modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('forwardModal'));
                        modal.hide();
                    }
                })
                .catch(error => {
                    completedCount++;
                    console.error('Error forwarding message to', recipient, ':', error);
                    
                    // Check if all forwards are complete
                    if (completedCount === totalCount) {
                        if (successCount === totalCount) {
                            alert(`Message forwarded to ${successCount} recipient(s) successfully`);
                        } else {
                            alert(`Message forwarding complete, ${successCount}/${totalCount} succeeded`);
                        }
                        
                        // Reload messages to show the forwarded message
                        if (window.currentChatId && window.currentChatType) {
                            window.loadMessages(window.currentChatId, window.currentChatType);
                        }
                        
                        // Close modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('forwardModal'));
                        modal.hide();
                    }
                });
            });
        }
        
        function tagUser(messageId) {
            const username = prompt('Enter username to tag:');
            if (!username) return;
            
            const formData = new FormData();
            formData.append('action', 'tag_user');
            formData.append('message_id', messageId);
            formData.append('tagged_user_id', username);
            
            fetch('ajax/enhanced_chat_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('User tagged successfully');
                    loadMessages();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to tag user');
            });
        }
        
        function editMessage(messageId) {
            const newContent = prompt('Edit message:');
            if (!newContent) return;
            
            const formData = new FormData();
            formData.append('action', 'edit_message');
            formData.append('message_id', messageId);
            formData.append('content', newContent);
            
            fetch('ajax/enhanced_chat_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Message edited successfully');
                    // Reload messages to show the updated content
                    if (window.currentChatId && window.currentChatType) {
                        window.loadMessages(window.currentChatId, window.currentChatType);
                    }
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to edit message');
            });
        }
        
        function recallMessage(messageId) {
            if (!confirm('Are you sure you want to recall this message?')) return;
            
            const formData = new FormData();
            formData.append('action', 'recall_message');
            formData.append('message_id', messageId);
            
            fetch('ajax/enhanced_chat_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.loadMessages(window.currentChatId, window.currentChatType);
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to recall message');
            });
        }
        
        function togglePin(messageId, isCurrentlyPinned) {
            const action = isCurrentlyPinned ? 'unpin_message' : 'pin_message';
            const formData = new FormData();
            formData.append('action', action);
            formData.append('message_id', messageId);
            
            fetch('ajax/enhanced_chat_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.loadMessages(window.currentChatId, window.currentChatType);
                } else {
                    alert('Error: ' + (data.error || 'Failed to toggle pin'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to toggle pin');
            });
        }
    </script>

    <!-- Incoming call popup -->
    <div class="modal fade" id="incomingCallModal" tabindex="-1" aria-labelledby="incomingCallLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="incomingCallLabel"><i class="fas fa-phone"></i> Incoming Call</h5>
                </div>
                <div class="modal-body text-center">
                    <p class="mb-0" id="incomingCallText">Loading...</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-danger" id="incomingCallReject"><i class="fas fa-phone-slash"></i> Reject</button>
                    <button type="button" class="btn btn-success" id="incomingCallAccept"><i class="fas fa-phone"></i> Accept</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Forward Message Modal -->
    <div class="modal fade" id="forwardModal" tabindex="-1" aria-labelledby="forwardModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="forwardModalLabel">
                        <i class="fas fa-share"></i> Forward Message
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-users"></i> Friends List</h6>
                            <div class="forward-list" id="friendsList">
                                <!-- Friends will be loaded here -->
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-layer-group"></i> Groups List</h6>
                            <div class="forward-list" id="groupsList">
                                <!-- Groups will be loaded here -->
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <h6><i class="fas fa-check-circle"></i> Selected</h6>
                        <div id="selectedRecipients" class="selected-recipients">
                            <!-- Selected recipients will be shown here -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmForwardBtn" disabled>
                        <i class="fas fa-paper-plane"></i> Forward Message
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .forward-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
        }
        
        .forward-item {
            display: flex;
            align-items: center;
            padding: 8px;
            margin: 5px 0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .forward-item:hover {
            background-color: #f8f9fa;
        }
        
        .forward-item.selected {
            background-color: rgba(59, 130, 246, 0.08);
            border: 2px solid #3b82f6;
        }
        
        .forward-item input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .forward-item .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            margin-right: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 14px;
        }
        
        .forward-item .info {
            flex: 1;
        }
        
        .forward-item .name {
            font-weight: 500;
            margin: 0;
        }
        
        .forward-item .status {
            font-size: 12px;
            color: #6c757d;
            margin: 0;
        }
        
        .selected-recipients {
            min-height: 50px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
            background-color: #f8f9fa;
        }
        
        .selected-recipient {
            display: inline-block;
            background-color: #3b82f6;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            margin: 2px;
            font-size: 12px;
        }
        
        .selected-recipient .remove {
            margin-left: 5px;
            cursor: pointer;
        }
        
        .selected-recipient .remove:hover {
            color: #ffcdd2;
        }
    </style>
    <?php include_once 'includes/copyright_footer.php'; ?>
    <script>
    // Keep the shared Zalo-style chat pane visible and restore a requested conversation.
    document.addEventListener('DOMContentLoaded', function () {
        const pane = document.querySelector('body.chat-page .chat-main');
        const container = document.querySelector('body.chat-page .chat-container');
        if (container) container.style.setProperty('display', 'flex', 'important');
        if (pane) pane.style.setProperty('display', 'flex', 'important');

        const initial = window.APP_INITIAL_CHAT;
        if (!initial || !initial.id || !initial.type) return;

        const tabName = initial.type === 'group' ? 'groups' : 'chats';
        document.querySelectorAll('.nav-tab').forEach(tab => tab.classList.toggle('active', tab.dataset.tab === tabName));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        const content = document.getElementById(tabName + '-content');
        if (content) content.classList.add('active');

        document.querySelectorAll('.chat-item, .friend-item, .group-item').forEach(item => item.classList.remove('active'));
        const selector = initial.type === 'group'
            ? '.group-item[data-group-id="' + initial.id + '"], .chat-item[data-chat-type="group"][data-group-id="' + initial.id + '"]'
            : '.chat-item[data-chat-type="user"][data-user-id="' + initial.id + '"], .friend-item[data-user-id="' + initial.id + '"]';
        document.querySelectorAll(selector).forEach(item => item.classList.add('active'));

        if (typeof window.updateChatInterface === 'function') {
            window.updateChatInterface(initial.name, initial.type, initial.id);
            const title = document.querySelector('#chatTitle h5');
            if (title) {
                title.textContent = '';
                const icon = document.createElement('i');
                icon.className = initial.type === 'group' ? 'fas fa-layer-group' : 'fas fa-user';
                title.appendChild(icon);
                title.appendChild(document.createTextNode(' ' + initial.name));
            }
        }
        if (typeof window.loadMessages === 'function') {
            window.loadMessages(initial.id, initial.type);
        }
    }, { once: true });
    </script>
</body>
</html>

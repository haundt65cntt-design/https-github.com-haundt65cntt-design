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
        case 'create_group':
            handleCreateGroup($pdo, $user_id);
            break;
        case 'join_group':
            handleJoinGroup($pdo, $user_id);
            break;
        case 'leave_group':
            handleLeaveGroup($pdo, $user_id);
            break;
        case 'get_group_info':
            handleGetGroupInfo($pdo, $user_id);
            break;
        case 'get_group_members':
            handleGetGroupMembers($pdo, $user_id);
            break;
        case 'invite_friend':
            handleInviteFriend($pdo, $user_id);
            break;
        case 'set_admin':
            handleSetAdmin($pdo, $user_id);
            break;
        case 'remove_admin':
            handleRemoveAdmin($pdo, $user_id);
            break;
        case 'update_group_avatar':
            handleUpdateGroupAvatar($pdo, $user_id);
            break;
        case 'get_group_permissions':
            handleGetGroupPermissions($pdo, $user_id);
            break;
        case 'get_user_groups':
            handleGetUserGroups($pdo, $user_id);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

function handleCreateGroup($pdo, $user_id) {
    $input = file_get_contents('php://input');
    if (empty($input) && isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
        $input = $GLOBALS['HTTP_RAW_POST_DATA'];
    }
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['name'])) {
        echo json_encode(['success' => false, 'error' => 'Group name is required']);
        return;
    }
    
    $group_name = trim($data['name']);
    $group_description = trim($data['description'] ?? '');
    
    if ($group_name === '') {
        echo json_encode(['success' => false, 'error' => 'Group name cannot be empty']);
        return;
    }
    
    // Check if group name already exists
    $stmt = $pdo->prepare("SELECT id FROM `groups` WHERE name = ?");
    $stmt->execute([$group_name]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Group name already exists']);
        return;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Create the group
        $stmt = $pdo->prepare("
            INSERT INTO `groups` (name, description, created_by, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        
        if (!$stmt->execute([$group_name, $group_description, $user_id])) {
            throw new Exception('Failed to create group');
        }
        
        $group_id = $pdo->lastInsertId();
        
        // Add creator as owner member
        $stmt = $pdo->prepare("
            INSERT INTO group_members (group_id, user_id, role, joined_at) 
            VALUES (?, ?, 'owner', NOW())
        ");
        
        if (!$stmt->execute([$group_id, $user_id])) {
            throw new Exception('Failed to add creator as group member');
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Group created successfully', 'group_id' => $group_id]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handleJoinGroup($pdo, $user_id) {
    $input = file_get_contents('php://input');
    if (empty($input) && isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
        $input = $GLOBALS['HTTP_RAW_POST_DATA'];
    }
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['group_id'])) {
        echo json_encode(['success' => false, 'error' => 'Group ID is required']);
        return;
    }
    
    $group_id = (int)$data['group_id'];
    
    // Check if group exists
    $stmt = $pdo->prepare("SELECT id FROM `groups` WHERE id = ?");
    $stmt->execute([$group_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Group not found']);
        return;
    }
    
    // Check if already a member
    $stmt = $pdo->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$group_id, $user_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Already a member of this group']);
        return;
    }
    
    // Add user to group
    $stmt = $pdo->prepare("
        INSERT INTO group_members (group_id, user_id, role, joined_at) 
        VALUES (?, ?, 'member', NOW())
    ");
    
    if ($stmt->execute([$group_id, $user_id])) {
        echo json_encode(['success' => true, 'message' => 'Joined group successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to join group']);
    }
}

function handleLeaveGroup($pdo, $user_id) {
    $input = file_get_contents('php://input');
    if (empty($input) && isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
        $input = $GLOBALS['HTTP_RAW_POST_DATA'];
    }
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['group_id'])) {
        echo json_encode(['success' => false, 'error' => 'Group ID is required']);
        return;
    }
    
    $group_id = (int)$data['group_id'];
    
    // Check if user is a member
    $stmt = $pdo->prepare("SELECT role FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$group_id, $user_id]);
    $membership = $stmt->fetch();
    
    if (!$membership) {
        echo json_encode(['success' => false, 'error' => 'Not a member of this group']);
        return;
    }
    
    // Check if user is the only admin
    if ($membership['role'] === 'admin') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as admin_count FROM group_members WHERE group_id = ? AND role = 'admin'");
        $stmt->execute([$group_id]);
        $admin_count = $stmt->fetch()['admin_count'];
        
        if ($admin_count <= 1) {
            echo json_encode(['success' => false, 'error' => 'Cannot leave group as the only admin. Transfer admin rights first.']);
            return;
        }
    }
    
    // Remove user from group
    $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
    
    if ($stmt->execute([$group_id, $user_id])) {
        // Also remove from pinned chats if exists
        $pin_stmt = $pdo->prepare("DELETE FROM pinned_chats WHERE user_id = ? AND chat_id = ? AND chat_type = 'group'");
        $pin_stmt->execute([$user_id, $group_id]);
        
        echo json_encode(['success' => true, 'message' => 'Left group successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to leave group']);
    }
}

function handleGetGroupInfo($pdo, $user_id) {
    $group_id = $_GET['group_id'] ?? null;
    
    if (!$group_id) {
        echo json_encode(['success' => false, 'error' => 'Group ID is required']);
        return;
    }
    
    try {
        // Get group information
        $stmt = $pdo->prepare("
            SELECT g.*, u.username as creator_username, u.full_name as creator_name
            FROM `groups` g
            LEFT JOIN users u ON g.created_by = u.id
            WHERE g.id = ?
        ");
        $stmt->execute([$group_id]);
        $group = $stmt->fetch();
        
        if (!$group) {
            echo json_encode(['success' => false, 'error' => 'Group not found']);
            return;
        }
        
        // Get member count
        $member_stmt = $pdo->prepare("SELECT COUNT(*) as member_count FROM group_members WHERE group_id = ?");
        $member_stmt->execute([$group_id]);
        $group['member_count'] = $member_stmt->fetch()['member_count'];
        
        // Check if current user is a member
        $user_member_stmt = $pdo->prepare("SELECT role FROM group_members WHERE group_id = ? AND user_id = ?");
        $user_member_stmt->execute([$group_id, $user_id]);
        $user_membership = $user_member_stmt->fetch();
        $group['user_role'] = $user_membership ? $user_membership['role'] : null;
        $group['is_member'] = $user_membership ? true : false;
        
        echo json_encode(['success' => true, 'group' => $group]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to get group info: ' . $e->getMessage()]);
    }
}

function handleGetGroupMembers($pdo, $user_id) {
    $group_id = $_GET['group_id'] ?? null;
    
    if (!$group_id) {
        echo json_encode(['success' => false, 'error' => 'Group ID is required']);
        return;
    }
    
    try {
        // Check if user is a member of the group
        $stmt = $pdo->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$group_id, $user_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Not a member of this group']);
            return;
        }
        
        // Get all group members
        $stmt = $pdo->prepare("
            SELECT u.*, gm.role, gm.joined_at
            FROM group_members gm
            JOIN users u ON gm.user_id = u.id
            WHERE gm.group_id = ?
            ORDER BY gm.role DESC, gm.joined_at ASC
        ");
        $stmt->execute([$group_id]);
        $members = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'members' => $members]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to get group members: ' . $e->getMessage()]);
    }
}

function handleInviteFriend($pdo, $user_id) {
    $input = file_get_contents('php://input');
    if (empty($input) && isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
        $input = $GLOBALS['HTTP_RAW_POST_DATA'];
    }
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['friend_id']) || !isset($data['group_id'])) {
        echo json_encode(['success' => false, 'error' => 'Friend ID and Group ID are required']);
        return;
    }
    
    $friend_id = (int)$data['friend_id'];
    $group_id = (int)$data['group_id'];
    
    try {
        // Check if user is a member of the group
        $stmt = $pdo->prepare("SELECT role FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$group_id, $user_id]);
        $membership = $stmt->fetch();
        
        if (!$membership) {
            echo json_encode(['success' => false, 'error' => 'Not a member of this group']);
            return;
        }
        
        // Check if friend is already a member
        $stmt = $pdo->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$group_id, $friend_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'User is already a member of this group']);
            return;
        }
        
        // Check if they are friends
        $stmt = $pdo->prepare("
            SELECT id FROM friends 
            WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)) 
            AND status = 'accepted'
        ");
        $stmt->execute([$user_id, $friend_id, $friend_id, $user_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'You can only invite friends to the group']);
            return;
        }
        
        // Add friend to group
        $stmt = $pdo->prepare("
            INSERT INTO group_members (group_id, user_id, role, joined_at) 
            VALUES (?, ?, 'member', NOW())
        ");
        
        if ($stmt->execute([$group_id, $friend_id])) {
            echo json_encode(['success' => true, 'message' => 'Friend invited to group successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to invite friend to group']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to invite friend: ' . $e->getMessage()]);
    }
}

// SettingsAdmin
function handleSetAdmin($pdo, $user_id) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $group_id = $data['group_id'] ?? null;
    $target_user_id = $data['user_id'] ?? null;
    
    if (!$group_id || !$target_user_id) {
        echo json_encode(['success' => false, 'error' => 'Group ID and User ID are required']);
        return;
    }
    
    try {
        // Check if current user is group owner
        $stmt = $pdo->prepare("SELECT role FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$group_id, $user_id]);
        $userRole = $stmt->fetchColumn();
        
        if ($userRole !== 'owner') {
            echo json_encode(['success' => false, 'error' => 'Only group owner can set admins']);
            return;
        }
        
        // Check if target user is in group
        $stmt = $pdo->prepare("SELECT role FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$group_id, $target_user_id]);
        $targetRole = $stmt->fetchColumn();
        
        if (!$targetRole) {
            echo json_encode(['success' => false, 'error' => 'User is not a member of this group']);
            return;
        }
        
        if ($targetRole === 'owner') {
            echo json_encode(['success' => false, 'error' => 'Cannot change owner role']);
            return;
        }
        
        // Update user role to admin
        $stmt = $pdo->prepare("UPDATE group_members SET role = 'admin' WHERE group_id = ? AND user_id = ?");
        
        if ($stmt->execute([$group_id, $target_user_id])) {
            echo json_encode(['success' => true, 'message' => 'User promoted to admin successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to promote user to admin']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to set admin: ' . $e->getMessage()]);
    }
}

// Remove admin
function handleRemoveAdmin($pdo, $user_id) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $group_id = $data['group_id'] ?? null;
    $target_user_id = $data['user_id'] ?? null;
    
    if (!$group_id || !$target_user_id) {
        echo json_encode(['success' => false, 'error' => 'Group ID and User ID are required']);
        return;
    }
    
    try {
        // Check if current user is group owner
        $stmt = $pdo->prepare("SELECT role FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$group_id, $user_id]);
        $userRole = $stmt->fetchColumn();
        
        if ($userRole !== 'owner') {
            echo json_encode(['success' => false, 'error' => 'Only group owner can remove admins']);
            return;
        }
        
        // Check if target user is admin
        $stmt = $pdo->prepare("SELECT role FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$group_id, $target_user_id]);
        $targetRole = $stmt->fetchColumn();
        
        if ($targetRole !== 'admin') {
            echo json_encode(['success' => false, 'error' => 'User is not an admin']);
            return;
        }
        
        // Update user role to regular member
        $stmt = $pdo->prepare("UPDATE group_members SET role = 'member' WHERE group_id = ? AND user_id = ?");
        
        if ($stmt->execute([$group_id, $target_user_id])) {
            echo json_encode(['success' => true, 'message' => 'Admin privileges removed successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to remove admin privileges']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to remove admin: ' . $e->getMessage()]);
    }
}

// Update group avatar
function handleUpdateGroupAvatar($pdo, $user_id) {
    $group_id = $_POST['group_id'] ?? null;
    
    if (!$group_id) {
        echo json_encode(['success' => false, 'error' => 'Group ID is required']);
        return;
    }
    
    try {
        // Check if current user is group owner
        $stmt = $pdo->prepare("SELECT role FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$group_id, $user_id]);
        $userRole = $stmt->fetchColumn();
        
        if ($userRole !== 'owner') {
            echo json_encode(['success' => false, 'error' => 'Only group owner can change group avatar']);
            return;
        }
        
        // Handle file upload
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
            return;
        }
        
        $file = $_FILES['avatar'];
        $max_size = 1024 * 1024 * 1024; // 1GB for large files like ZIP
        
        // Check file size
        if ($file['size'] > $max_size) {
            echo json_encode(['success' => false, 'error' => 'File size too large. Maximum size is 1GB.']);
            return;
        }
        
        // Check file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed_types)) {
            echo json_encode(['success' => false, 'error' => 'Only image files are allowed']);
            return;
        }
        
        // Create upload directory
        $upload_dir = 'uploads/group_avatars/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $file_name = 'group_' . $group_id . '_' . uniqid() . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // Update database
            $stmt = $pdo->prepare("UPDATE `groups` SET avatar = ? WHERE id = ?");
            
            if ($stmt->execute([$file_path, $group_id])) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Group avatar updated successfully',
                    'avatar_path' => $file_path
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update group avatar in database']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to update group avatar: ' . $e->getMessage()]);
    }
}

// Get group permissions
function handleGetGroupPermissions($pdo, $user_id) {
    $group_id = $_GET['group_id'] ?? null;
    
    if (!$group_id) {
        echo json_encode(['success' => false, 'error' => 'Group ID is required']);
        return;
    }
    
    try {
        // Check if user is in group
        $stmt = $pdo->prepare("SELECT role FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$group_id, $user_id]);
        $userRole = $stmt->fetchColumn();
        
        if (!$userRole) {
            echo json_encode(['success' => false, 'error' => 'You are not a member of this group']);
            return;
        }
        
        // Get group member info
        $stmt = $pdo->prepare("
            SELECT gm.user_id, gm.role, u.username, u.full_name, u.avatar
            FROM group_members gm
            JOIN users u ON gm.user_id = u.id
            WHERE gm.group_id = ?
            ORDER BY 
                CASE gm.role 
                    WHEN 'owner' THEN 1 
                    WHEN 'admin' THEN 2 
                    WHEN 'member' THEN 3 
                END,
                u.username
        ");
        $stmt->execute([$group_id]);
        $members = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'user_role' => $userRole,
            'members' => $members,
            'permissions' => [
                'can_set_admin' => $userRole === 'owner',
                'can_remove_admin' => $userRole === 'owner',
                'can_change_avatar' => $userRole === 'owner',
                'can_invite_members' => in_array($userRole, ['owner', 'admin']),
                'can_remove_members' => in_array($userRole, ['owner', 'admin'])
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to get group permissions: ' . $e->getMessage()]);
    }
}

// Get user group list
function handleGetUserGroups($pdo, $user_id) {
    try {
        // Get all user groups including pin status
        $stmt = $pdo->prepare("
            SELECT g.*, gm.role, 
                   CASE WHEN pc.id IS NOT NULL THEN 1 ELSE 0 END as is_pinned
            FROM `groups` g 
            JOIN group_members gm ON g.id = gm.group_id 
            LEFT JOIN pinned_chats pc ON pc.user_id = ? AND pc.chat_id = g.id AND pc.chat_type = 'group'
            WHERE gm.user_id = ? 
            ORDER BY is_pinned DESC, g.name ASC
        ");
        $stmt->execute([$user_id, $user_id]);
        $groups = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'groups' => $groups]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to get user groups: ' . $e->getMessage()]);
    }
}
?>

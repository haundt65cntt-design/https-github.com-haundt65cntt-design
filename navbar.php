<?php
// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? '';
$full_name = $_SESSION['full_name'] ?? $username;

// Get user's friends, groups, and pending requests if logged in
$friends = [];
$groups = [];
$pending_requests = [];

if ($is_logged_in && $user_id) {
    require_once 'config/database.php';
    
    // Get user's avatar
    $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_avatar = $stmt->fetch()['avatar'] ?? 'default_avatar.png';
    
    // Get user's friends (check both directions, avoid duplicates) with pinned status
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.*, f.status as friendship_status, 
               CASE WHEN pc.id IS NOT NULL THEN 1 ELSE 0 END as is_pinned
        FROM friends f 
        JOIN users u ON (
            CASE 
                WHEN f.user_id = ? THEN f.friend_id = u.id
                WHEN f.friend_id = ? THEN f.user_id = u.id
            END
        )
        LEFT JOIN pinned_chats pc ON pc.user_id = ? AND pc.chat_id = u.id AND pc.chat_type = 'user'
        WHERE (f.user_id = ? OR f.friend_id = ?) AND f.status = 'accepted'
        ORDER BY is_pinned DESC, u.status DESC, u.full_name ASC
    ");
    $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
    $friends = $stmt->fetchAll();

    // Get user's groups with pinned status
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
}
?>

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
    <!-- User Profile Section -->
    <?php if ($is_logged_in): ?>
        <div class="user-profile-section">
            <div class="user-avatar" id="userAvatarDropdown">
                <div class="avatar-circle blue">
                    <?php if ($is_logged_in && $user_avatar && $user_avatar !== 'default_avatar.png' && file_exists('uploads/avatars/' . $user_avatar)): ?>
                        <img src="uploads/avatars/<?php echo htmlspecialchars($user_avatar); ?>?t=<?php echo time(); ?>" alt="Avatar" class="navbar-avatar-image">
                    <?php else: ?>
                        <?php echo $is_logged_in ? strtoupper(substr($username, 0, 1)) : 'G'; ?>
                    <?php endif; ?>
                </div>
                <div class="dropdown-menu" id="userDropdownMenu">
                    <div class="dropdown-item" onclick="showProfile()">
                        <i class="fas fa-user"></i>
                        <span>Profile</span>
                    </div>
                    <div class="dropdown-item" onclick="showBlockedUsers()">
                        <i class="fas fa-ban"></i>
                        <span>Block User</span>
                    </div>
                    <div class="dropdown-item" onclick="showBlacklist()">
                        <i class="fas fa-user-slash"></i>
                        <span>Blacklist</span>
                    </div>
                    <div class="dropdown-item" onclick="showSettings()">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </div>
                    <div class="dropdown-divider"></div>
                    <div class="dropdown-item logout-item" onclick="logout()">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </div>
                </div>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo $is_logged_in ? htmlspecialchars($username) : 'Guest'; ?></div>
                <div class="user-status">
                    <span class="status-text online"><?php echo $is_logged_in ? 'Online' : 'Offline'; ?></span>
                </div>
            </div>
            <div class="user-actions">
                <a href="logout.php" class="logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Thanh điều hướng dọc theo phong cách Zalo PC -->
    <div class="nav-tabs-container" aria-label="Điều hướng chính">
        <div class="nav-tab <?php echo (basename($_SERVER['PHP_SELF']) === 'user_chatting.php') ? 'active' : ''; ?>" data-tab="chats" title="Tin nhắn" aria-label="Tin nhắn">
            <i class="fas fa-comment-dots" aria-hidden="true"></i>
            <span class="tab-text">Tin nhắn</span>
        </div>
        <div class="nav-tab" data-tab="friends" title="Bạn bè" aria-label="Bạn bè">
            <i class="fas fa-address-book" aria-hidden="true"></i>
            <span class="tab-text">Bạn bè</span>
            <?php if (!empty($pending_requests)): ?>
                <span class="friend-request-count" aria-label="<?php echo count($pending_requests); ?> pending friend requests"><?php echo count($pending_requests); ?></span>
            <?php endif; ?>
        </div>
        <div class="nav-tab <?php echo (basename($_SERVER['PHP_SELF']) === 'group.php') ? 'active' : ''; ?>" data-tab="groups" title="Nhóm" aria-label="Nhóm">
            <i class="fas fa-users" aria-hidden="true"></i>
            <span class="tab-text">Nhóm</span>
        </div>
        <a class="nav-tab <?php echo (basename($_SERVER['PHP_SELF']) === 'social_feed.php') ? 'active' : ''; ?>"
           href="social_feed.php" data-tab="social-feed" title="Bài đăng" aria-label="Bài đăng">
            <i class="fas fa-newspaper" aria-hidden="true"></i>
            <span class="tab-text">Bài đăng</span>
        </a>
    </div>

    <!-- Content Area -->
    <div class="sidebar-content">
        <!-- Chats Tab Content -->
        <div class="tab-content <?php echo in_array(basename($_SERVER['PHP_SELF']), array('user_chatting.php', 'social_feed.php')) ? 'active' : ''; ?>" id="chats-content">
            <div class="search-container">
                <input type="text" placeholder="Search..." class="search-input">
            </div>
            
            <div class="chat-list">
                <?php if (empty($friends) && empty($groups)): ?>
                    <div class="no-chats">No chats yet</div>
                <?php else: ?>
                    <?php foreach ($friends as $friend): ?>
                        <div class="chat-item <?php echo $friend['is_pinned'] ? 'pinned' : ''; ?>" data-user-id="<?php echo $friend['id']; ?>" data-username="<?php echo htmlspecialchars($friend['username'], ENT_QUOTES, 'UTF-8'); ?>" data-full-name="<?php echo htmlspecialchars($friend['full_name'] ?? $friend['username'], ENT_QUOTES, 'UTF-8'); ?>" data-chat-type="user">
                            <div class="chat-avatar">
                                <div class="avatar-circle <?php echo $friend['status'] === 'online' ? 'green' : 'purple'; ?>">
                                    <?php if ($friend['avatar'] && $friend['avatar'] !== 'default_avatar.png' && file_exists('uploads/avatars/' . $friend['avatar'])): ?>
                                        <img src="uploads/avatars/<?php echo htmlspecialchars($friend['avatar']); ?>?t=<?php echo time(); ?>" alt="Avatar" class="chat-avatar-image">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($friend['username'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($friend['is_pinned']): ?>
                                    <div class="pin-indicator" title="Pin Chat">
                                        <i class="fas fa-thumbtack"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="chat-details">
                                <div class="chat-name"><?php echo htmlspecialchars($friend['full_name'] ?? $friend['username']); ?></div>
                                <div class="chat-status"><?php echo ucfirst($friend['status']); ?></div>
                            </div>
                            <div class="chat-actions">
                                <button class="chat-settings-btn" data-user-id="<?php echo $friend['id']; ?>" data-username="<?php echo htmlspecialchars($friend['username']); ?>" title="Settings">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php foreach ($groups as $gr): ?>
                        <div class="chat-item <?php echo $gr['is_pinned'] ? 'pinned' : ''; ?>" data-group-id="<?php echo $gr['id']; ?>" data-group-name="<?php echo htmlspecialchars($gr['name']); ?>" data-chat-type="group">
                            <div class="chat-avatar">
                                <div class="avatar-circle pink"><?php echo strtoupper(substr($gr['name'], 0, 1)); ?></div>
                                <div class="group-icon">👥</div>
                                <?php if ($gr['is_pinned']): ?>
                                    <div class="pin-indicator" title="Pin Chat">
                                        <i class="fas fa-thumbtack"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="chat-details">
                                <div class="chat-name"><?php echo htmlspecialchars($gr['name']); ?></div>
                                <div class="chat-description"><?php echo htmlspecialchars($gr['description']); ?></div>
                            </div>
                            <div class="chat-actions">
                                <button class="chat-settings-btn" data-group-id="<?php echo $gr['id']; ?>" data-group-name="<?php echo htmlspecialchars($gr['name']); ?>" title="Settings">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Friends Tab Content -->
        <div class="tab-content" id="friends-content">
            <div class="search-container">
                <input type="text" placeholder="Search Friends..." class="search-input">
            </div>
            
            <div class="action-buttons">
                <button class="btn-add-friend">Add Friend</button>
            </div>
            
            <!-- Friend Requests Section -->
            <div class="friend-requests-section" aria-live="polite">
                <h6 class="section-title">
                    <i class="fas fa-user-plus"></i> Friend Requests (<?php echo count($pending_requests); ?>)
                </h6>
                <?php if (!empty($pending_requests)): ?>
                    <div class="friend-requests-list">
                        <?php foreach ($pending_requests as $request): ?>
                            <?php
                            $request_username = $request['username'] ?? '';
                            $request_name = $request['full_name'] ?: $request_username;
                            $request_avatar = $request['avatar'] ?? '';
                            ?>
                            <div class="friend-request-item" data-user-id="<?php echo (int)$request['id']; ?>" data-username="<?php echo htmlspecialchars($request_username, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="friend-request-avatar">
                                    <div class="avatar-circle purple">
                                        <?php if ($request_avatar && $request_avatar !== 'default_avatar.png' && file_exists('uploads/avatars/' . $request_avatar)): ?>
                                            <img src="uploads/avatars/<?php echo htmlspecialchars($request_avatar, ENT_QUOTES, 'UTF-8'); ?>?t=<?php echo time(); ?>" alt="" class="request-avatar-image">
                                        <?php else: ?>
                                            <?php echo htmlspecialchars(strtoupper(substr($request_username, 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="friend-request-details">
                                    <div class="friend-request-name"><?php echo htmlspecialchars($request_name, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="friend-request-time"><?php echo date('M j, Y', strtotime($request['request_date'])); ?></div>
                                </div>
                                <div class="friend-request-actions">
                                    <button type="button" class="btn-accept-request" data-user-id="<?php echo (int)$request['id']; ?>" title="Accept" aria-label="Accept friend request from <?php echo htmlspecialchars($request_name, ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button type="button" class="btn-reject-request" data-user-id="<?php echo (int)$request['id']; ?>" title="Reject" aria-label="Reject friend request from <?php echo htmlspecialchars($request_name, ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="friend-request-empty">No pending friend requests</div>
                <?php endif; ?>
            </div>
            
            <div class="friends-list">
                <?php if (empty($friends)): ?>
                    <div class="no-friends">No accepted friends yet</div>
                <?php else: ?>
                    <?php foreach ($friends as $friend): ?>
                        <div class="friend-item" data-user-id="<?php echo $friend['id']; ?>" data-username="<?php echo htmlspecialchars($friend['username'], ENT_QUOTES, 'UTF-8'); ?>" data-full-name="<?php echo htmlspecialchars($friend['full_name'] ?? $friend['username'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="friend-avatar">
                                <div class="avatar-circle <?php echo $friend['status'] === 'online' ? 'green' : 'purple'; ?>">
                                    <?php if ($friend['avatar'] && $friend['avatar'] !== 'default_avatar.png' && file_exists('uploads/avatars/' . $friend['avatar'])): ?>
                                        <img src="uploads/avatars/<?php echo htmlspecialchars($friend['avatar']); ?>?t=<?php echo time(); ?>" alt="Avatar" class="friend-avatar-image">
                                    <?php else: ?>
                                        <?php 
                                            $displayName = $friend['full_name'] ?? $friend['username'];
                                            if (is_numeric($friend['username']) && !$friend['full_name']) {
                                                $displayName = 'User' . $friend['username'];
                                            }
                                            echo strtoupper(substr($displayName, 0, 1)); 
                                        ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="friend-details">
                                <div class="friend-name"><?php 
                                    $displayName = $friend['full_name'] ?? $friend['username'];
                                    // If username is just numbers, make it more friendly
                                    if (is_numeric($friend['username']) && !$friend['full_name']) {
                                        $displayName = 'User' . $friend['username'];
                                    }
                                    echo htmlspecialchars($displayName); 
                                ?></div>
                                <div class="friend-status"><?php echo ucfirst($friend['status']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Groups Tab Content -->
        <div class="tab-content <?php echo (basename($_SERVER['PHP_SELF']) === 'group.php') ? 'active' : ''; ?>" id="groups-content">
            <div class="search-container">
                <input type="text" placeholder="Search Groups..." class="search-input">
            </div>
            
            <div class="action-buttons">
                <button class="btn-create-group">Create Group</button>
            </div>
            
            <div class="groups-list">
                <?php if (empty($groups)): ?>
                    <div class="no-groups">No groups yet</div>
                <?php else: ?>
                    <?php 
                    $is_group_page = (basename($_SERVER['PHP_SELF']) === 'group.php');
                    foreach ($groups as $g): 
                        $tag = $is_group_page ? 'a' : 'div';
                        $href_attr = $is_group_page ? ' href="user_chatting.php?group_id=' . (int)$g['id'] . '"' : '';
                    ?>
                        <<?php echo $tag; ?> class="group-item"<?php echo $href_attr; ?> data-group-id="<?php echo (int)$g['id']; ?>" data-group-name="<?php echo htmlspecialchars($g['name']); ?>">
                            <div class="group-avatar">
                                <?php if (!empty($g['avatar']) && $g['avatar'] !== 'default_group_avatar.png'): ?>
                                    <img src="<?php echo htmlspecialchars($g['avatar']); ?>?t=<?php echo time(); ?>" alt="Group Avatar" class="avatar-circle" style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%;">
                                <?php else: ?>
                                    <div class="avatar-circle pink"><?php echo strtoupper(substr($g['name'], 0, 1)); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="group-details">
                                <div class="group-name"><?php echo htmlspecialchars($g['name']); ?></div>
                                <div class="group-members"><?php echo $g['role'] === 'admin' ? 'Admin' : 'Member'; ?></div>
                            </div>
                            <div class="group-actions">
                                <button type="button" class="group-settings-btn" data-group-id="<?php echo (int)$g['id']; ?>" data-group-name="<?php echo htmlspecialchars($g['name']); ?>" title="Settings">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                            </div>
                        </<?php echo $tag; ?>>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bảng tin được mở ở trang social_feed.php; không còn danh sách nhiều forum tại sidebar. -->
        </div>
    </div>
</nav>

<!-- Main Content Wrapper -->
<div class="main-wrapper" id="mainWrapper">
    <!-- Content will be inserted here by pages -->
</div>

<!-- Custom styles for sidebar -->
<style>
:root {
    --sidebar-width: 300px;
    --sidebar-bg: rgba(255, 255, 255, 0.92);
    --sidebar-text: #1e3a5f;
    --sidebar-hover: rgba(59, 130, 246, 0.1);
    --sidebar-active: #3b82f6;
    --sidebar-border: rgba(147, 197, 253, 0.45);
    --transition-speed: 0.3s;
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: var(--sidebar-width);
    background: var(--sidebar-bg);
    color: var(--sidebar-text);
    transition: all var(--transition-speed) ease;
    z-index: 1000;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    overflow-y: auto;
    border-right: 1px solid var(--sidebar-border);
    display: flex;
    flex-direction: column;
    min-height: 100vh; /* Ensure minimum height */
}

/* User Profile Section */
.user-profile-section {
    padding: 20px;
    display: flex;
    align-items: center;
    background: white;
    border-bottom: 1px solid var(--sidebar-border);
    position: relative;
}

.user-avatar {
    margin-right: 15px;
    position: relative;
    cursor: pointer;
}

.user-avatar .avatar-circle {
    width: 60px;
    height: 60px;
    font-size: 24px;
    transition: transform 0.2s ease;
    overflow: hidden;
    position: relative;
}

.navbar-avatar-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.friend-avatar-image,
.chat-avatar-image,
.request-avatar-image,
.member-avatar-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.user-avatar:hover .avatar-circle {
    transform: scale(1.05);
}

/* Dropdown Menu Styles */
.dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    min-width: 200px;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.2s ease;
    margin-top: 8px;
}

.dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-item {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    cursor: pointer;
    transition: background-color 0.2s ease;
    color: #333;
    font-size: 14px;
}

.dropdown-item:hover {
    background-color: rgba(59, 130, 246, 0.06);
}

.dropdown-item i {
    width: 20px;
    margin-right: 12px;
    color: #6c757d;
    font-size: 14px;
}

.dropdown-item.logout-item {
    color: #dc3545;
}

.dropdown-item.logout-item i {
    color: #dc3545;
}

.dropdown-item.logout-item:hover {
    background-color: #f8d7da;
}

.dropdown-divider {
    height: 1px;
    background-color: #e9ecef;
    margin: 4px 0;
}

/* Mobile responsive dropdown */
@media (max-width: 768px) {
    .dropdown-menu {
        position: fixed;
        top: 80px;
        left: 20px;
        right: 20px;
        width: auto;
        min-width: auto;
    }
}

.user-info {
    flex: 1;
}

.user-name {
    font-size: 18px;
    font-weight: 600;
    color: var(--sidebar-text);
    margin-bottom: 5px;
}

.user-status {
    display: flex;
    align-items: center;
}

.status-text {
    font-size: 14px;
    color: #28a745;
    font-weight: 500;
}

/* User Actions */
.user-actions {
    position: absolute;
    top: 20px;
    right: 20px;
}

.logout-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    text-decoration: none;
    transition: all var(--transition-speed) ease;
    font-size: 14px;
}

.logout-btn:hover {
    background: #c82333;
    color: white;
    transform: scale(1.1);
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
}

.logout-btn:active {
    transform: scale(0.95);
}

/* Navigation Tabs */
.nav-tabs-container {
    padding: 15px 20px;
    background: white;
    border-bottom: 1px solid var(--sidebar-border);
    display: flex;
    gap: 20px;
}

.nav-tab {
    padding: 8px 0;
    cursor: pointer;
    position: relative;
    display: inline-block;
    text-decoration: none;
    font-size: 16px;
    font-weight: 500;
    color: var(--sidebar-text);
    transition: all var(--transition-speed) ease;
}

.friend-request-count {
    position: absolute;
    top: 2px;
    right: 2px;
    min-width: 17px;
    height: 17px;
    padding: 0 4px;
    border-radius: 9px;
    color: #fff;
    background: #e5484d;
    font-size: 10px;
    font-weight: 700;
    line-height: 17px;
    text-align: center;
}

.nav-tab.active {
    color: var(--sidebar-active);
}

.nav-tab.active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 2px;
    background: var(--sidebar-active);
}

.nav-tab:hover {
    color: var(--sidebar-active);
}

/* Sidebar Content */
.sidebar-content {
    flex: 1;
    background: white;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    min-height: 0; /* Allow flex shrinking */
}

.tab-content {
    display: none;
    padding: 20px;
    flex: 1;
    overflow-y: auto;
}

.tab-content.active {
    display: flex;
    flex-direction: column;
}

/* Search Container */
.search-container {
    margin-bottom: 20px;
}

.search-input {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid var(--sidebar-border);
    border-radius: 20px;
    background: var(--sidebar-bg);
    color: var(--sidebar-text);
    font-size: 14px;
    transition: all var(--transition-speed) ease;
}

.search-input:focus {
    outline: none;
    border-color: var(--sidebar-active);
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

/* Action Buttons */
.action-buttons {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
}

.btn-add-friend,
.btn-create-group,
.btn-create-forum,
.btn-join-forum {
    padding: 10px 20px;
    border: none;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all var(--transition-speed) ease;
}

.btn-add-friend,
.btn-create-group,
.btn-create-forum {
    background: linear-gradient(135deg, #6366f1 0%, #3b82f6 100%);
    color: white;
}

.btn-join-forum {
    background: linear-gradient(135deg, #e83e8c 0%, #dc3545 100%);
    color: white;
}

.btn-add-friend:hover,
.btn-create-group:hover,
.btn-create-forum:hover,
.btn-join-forum:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

/* Chat List */
.chat-list,
.friends-list,
.groups-list,
.forums-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    flex: 1;
    overflow-y: auto;
}

.chat-item,
.friend-item,
.group-item,
.forum-item {
    display: flex;
    align-items: center;
    padding: 12px;
    cursor: pointer;
    transition: all var(--transition-speed) ease;
    border-radius: 8px;
    position: relative;
}

a.group-item {
    text-decoration: none;
    color: inherit;
}

.forum-main-content {
    display: flex;
    align-items: center;
    flex: 1;
    cursor: pointer;
    padding: 0;
    margin: 0;
}

.btn-forum-settings {
    position: absolute;
    right: 12px;
    width: 28px;
    height: 28px;
    border: none;
    background: rgba(108, 117, 125, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all var(--transition-speed) ease;
    color: #6c757d;
    opacity: 0;
}

.forum-item:hover .btn-forum-settings {
    opacity: 1;
}

.btn-forum-settings:hover {
    background: rgba(108, 117, 125, 0.2);
    color: #495057;
}

/* Forum Settings Modal Styles */
.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: #3b82f6;
}

.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
    margin-top: 0.25rem;
}

/* Chat Actions */
.chat-actions {
    position: absolute;
    right: 12px;
    opacity: 0;
    transition: opacity var(--transition-speed) ease;
}

.chat-item:hover .chat-actions {
    opacity: 1;
}

.chat-settings-btn {
    width: 28px;
    height: 28px;
    border: none;
    background: rgba(108, 117, 125, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all var(--transition-speed) ease;
    color: #6c757d;
    font-size: 12px;
}

.chat-settings-btn:hover {
    background: rgba(108, 117, 125, 0.2);
    color: #495057;
    transform: scale(1.1);
}

.chat-settings-btn:active {
    transform: scale(0.95);
}

/* Group Actions */
.group-actions {
    position: absolute;
    right: 12px;
    opacity: 0;
    transition: opacity var(--transition-speed) ease;
}

.group-item:hover .group-actions {
    opacity: 1;
}

.group-settings-btn {
    width: 28px;
    height: 28px;
    border: none;
    background: rgba(108, 117, 125, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all var(--transition-speed) ease;
    color: #6c757d;
    font-size: 12px;
}

.group-settings-btn:hover {
    background: rgba(108, 117, 125, 0.2);
    color: #495057;
    transform: scale(1.1);
}

.group-settings-btn:active {
    transform: scale(0.95);
}

/* Pin indicator */
.pin-indicator {
    position: absolute;
    top: -2px;
    right: -2px;
    background: #ffc107;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 8px;
    color: white;
    z-index: 2;
}

/* Pinned chat item styling */
.chat-item.pinned {
    background-color: #fff3cd;
    border-left: 3px solid #ffc107;
}

.chat-item.pinned:hover {
    background-color: #ffeaa7;
}

.chat-item:hover,
.friend-item:hover,
.group-item:hover,
.forum-item:hover {
    background-color: var(--sidebar-hover);
}

.chat-item.active,
.friend-item.active,
.group-item.active {
    background-color: rgba(59, 130, 246, 0.08);
    border-left: 4px solid #3b82f6;
}

.chat-avatar,
.friend-avatar,
.group-avatar,
.forum-avatar {
    margin-right: 15px;
    position: relative;
}

.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
}

.avatar-circle.purple {
    background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
}

.avatar-circle.green {
    background-color: #28a745;
}

.avatar-circle.pink {
    background-color: #e83e8c;
}

.avatar-circle.blue {
    background-color: #87CEEB;
}

.group-icon {
    position: absolute;
    bottom: -2px;
    right: -2px;
    background: #28a745;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    color: white;
}

.chat-details,
.friend-details,
.group-details,
.forum-details {
    flex: 1;
    min-width: 0;
}

.chat-name,
.friend-name,
.group-name,
.forum-name {
    font-size: 16px;
    font-weight: 600;
    color: var(--sidebar-text);
    margin-bottom: 4px;
}

.chat-number,
.friend-status,
.group-members,
.forum-info {
    font-size: 14px;
    color: #6c757d;
    font-weight: 500;
}

.chat-message {
    font-size: 14px;
    color: #6c757d;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Friend Requests Section */
.friend-requests-section {
    margin-bottom: 20px;
    padding: 15px;
    background: rgba(59, 130, 246, 0.06);
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.friend-request-empty {
    padding: 10px 8px;
    color: #6c757d;
    font-size: 13px;
    text-align: center;
}

.section-title {
    font-size: 14px;
    font-weight: 600;
    color: #495057;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.friend-requests-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.friend-request-item {
    display: flex;
    align-items: center;
    padding: 8px;
    background: white;
    border-radius: 6px;
    border: 1px solid #e9ecef;
    transition: all var(--transition-speed) ease;
}

.friend-request-item:hover {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.friend-request-avatar {
    margin-right: 10px;
}

.friend-request-avatar .avatar-circle {
    width: 32px;
    height: 32px;
    font-size: 14px;
}

.friend-request-details {
    flex: 1;
    min-width: 0;
}

.friend-request-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--sidebar-text);
    margin-bottom: 2px;
}

.friend-request-time {
    font-size: 12px;
    color: #6c757d;
}

.friend-request-actions {
    display: flex;
    gap: 5px;
}

.btn-accept-request,
.btn-reject-request {
    width: 24px;
    height: 24px;
    border: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    cursor: pointer;
    transition: all var(--transition-speed) ease;
}

.btn-accept-request {
    background: #28a745;
    color: white;
}

.btn-accept-request:hover {
    background: #218838;
    transform: scale(1.1);
}

.btn-reject-request {
    background: #dc3545;
    color: white;
}

.btn-reject-request:hover {
    background: #c82333;
    transform: scale(1.1);
}

/* Empty states */
.no-friends,
.no-groups,
.no-chats {
    text-align: center;
    color: #6c757d;
    font-style: italic;
    padding: 40px 20px;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Main content wrapper */
.main-wrapper {
    margin-left: var(--sidebar-width);
    transition: margin-left var(--transition-speed) ease;
    min-height: 100vh;
    width: calc(100% - var(--sidebar-width));
}

/* Responsive design */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        width: var(--sidebar-width);
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .main-wrapper {
        margin-left: 0;
        width: 100%;
    }
    
    .mobile-sidebar-toggle {
        display: block !important;
    }
}

@media (min-width: 769px) {
    .mobile-sidebar-toggle {
        display: none !important;
    }
}

/* Scrollbar styling */
.sidebar::-webkit-scrollbar {
    width: 4px;
}

.sidebar::-webkit-scrollbar-track {
    background: var(--sidebar-hover);
}

.sidebar::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 2px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: #999;
}

/* Group Members Modal Styles */
.member-item {
    border-bottom: 1px solid #e9ecef !important;
    padding: 15px !important;
}

.member-item:last-child {
    border-bottom: none !important;
}

.member-avatar .avatar-circle {
    width: 50px !important;
    height: 50px !important;
    font-size: 18px !important;
}

.member-name {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin-bottom: 4px;
}

.member-role {
    display: flex;
    align-items: center;
    gap: 8px;
}

.member-role .badge {
    font-size: 12px;
    padding: 4px 8px;
}

.member-role .text-muted {
    font-size: 14px;
}



/* Profile modal actions (kept in the existing navbar stylesheet block). */
.profile-modal-avatar { display:flex; align-items:center; justify-content:center; }
.profile-modal-avatar-image,
.profile-modal-avatar-fallback { width:116px; height:116px; border-radius:50%; object-fit:cover; }
.profile-modal-avatar-fallback { display:flex; align-items:center; justify-content:center; margin:0 auto; background:#dbeafe; color:#1d4ed8; font-size:44px; font-weight:700; }
.profile-friend-action { display:flex; justify-content:center; }
.profile-friend-note { display:inline-flex; align-items:center; gap:7px; padding:9px 13px; border-radius:10px; background:#f1f5f9; color:#475569; font-size:14px; font-weight:600; }
.profile-friend-note.is-friend { background:#ecfdf5; color:#047857; }

/* Zalo PC layout styles (merged here to avoid adding a new CSS file). */
/*
 * Zalo-inspired desktop interface for Advanced Chat System.
 * Presentation only: keeps existing PHP, AJAX handlers and DOM identifiers.
 */
:root {
    --zalo-blue: #0068ff;
    --zalo-blue-dark: #0057d9;
    --zalo-blue-soft: #eaf3ff;
    --zalo-rail-width: 68px;
    --zalo-panel-width: 340px;
    --zalo-sidebar-width: calc(var(--zalo-rail-width) + var(--zalo-panel-width));
    --zalo-line: #e7ebf0;
    --zalo-text: #1f2937;
    --zalo-muted: #7b8492;
    --zalo-app-bg: #f3f5f8;
}

html,
body {
    min-height: 100%;
    font-family: "Segoe UI", Roboto, Arial, sans-serif;
    color: var(--zalo-text);
}

body.chat-page {
    height: 100vh !important;
    min-height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow: hidden !important;
    background: var(--zalo-app-bg) !important;
}

/* Left blue navigation rail + conversation/friends panel. */
#sidebar.sidebar {
    position: fixed !important;
    inset: 0 auto 0 0 !important;
    z-index: 1050 !important;
    width: var(--zalo-sidebar-width) !important;
    height: 100dvh !important;
    min-height: 0 !important;
    display: grid !important;
    grid-template-columns: var(--zalo-rail-width) minmax(0, var(--zalo-panel-width));
    grid-template-rows: 72px minmax(0, 1fr);
    overflow: visible !important;
    padding: 0 !important;
    border: 0 !important;
    border-right: 1px solid #dce2ea !important;
    border-radius: 0 !important;
    background: #fff !important;
    box-shadow: 2px 0 16px rgba(23, 42, 69, .06) !important;
    transform: none;
}

#sidebar .user-profile-section {
    grid-column: 1;
    grid-row: 1;
    width: var(--zalo-rail-width);
    height: 72px;
    min-width: 0;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 !important;
    margin: 0 !important;
    border: 0 !important;
    border-right: 1px solid rgba(255, 255, 255, .13) !important;
    border-bottom: 1px solid rgba(255, 255, 255, .12) !important;
    border-radius: 0 !important;
    background: var(--zalo-blue) !important;
}

#sidebar .user-avatar {
    margin: 0 !important;
    position: relative;
}

#sidebar .user-avatar .avatar-circle {
    width: 42px !important;
    height: 42px !important;
    border: 2px solid rgba(255, 255, 255, .9);
    box-shadow: 0 2px 7px rgba(0, 0, 0, .16);
    font-size: 17px !important;
}

#sidebar .user-info,
#sidebar .user-actions {
    display: none !important;
}

#sidebar .dropdown-menu {
    position: fixed !important;
    top: 12px !important;
    left: 78px !important;
    right: auto !important;
    z-index: 1200 !important;
    margin: 0 !important;
    min-width: 220px;
    border: 1px solid #e6eaf0;
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(16, 24, 40, .18);
}

#sidebar .nav-tabs-container {
    grid-column: 1;
    grid-row: 2;
    width: var(--zalo-rail-width);
    min-height: 0;
    box-sizing: border-box;
    display: flex !important;
    flex-direction: column !important;
    align-items: stretch;
    justify-content: flex-start;
    gap: 8px !important;
    padding: 12px 7px !important;
    margin: 0 !important;
    overflow-y: auto;
    overflow-x: hidden;
    border: 0 !important;
    border-right: 1px solid #e5eaf1 !important;
    border-radius: 0 !important;
    background: var(--zalo-blue) !important;
}

#sidebar .nav-tab {
    width: 54px;
    min-height: 58px;
    flex: 0 0 auto;
    display: flex !important;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 5px;
    box-sizing: border-box;
    padding: 7px 2px !important;
    margin: 0 auto !important;
    border: 0 !important;
    border-radius: 10px !important;
    color: rgba(255, 255, 255, .82) !important;
    background: transparent !important;
    text-decoration: none !important;
    cursor: pointer;
    transition: background .16s ease, color .16s ease;
}

#sidebar .nav-tab i {
    display: block;
    font-size: 20px;
    line-height: 1;
}

#sidebar .nav-tab .tab-text {
    display: block !important;
    max-width: 100%;
    overflow: hidden;
    font-size: 9px;
    line-height: 1.15;
    font-weight: 600;
    text-align: center;
    text-overflow: ellipsis;
    white-space: nowrap;
}

#sidebar .nav-tab::after {
    display: none !important;
}

#sidebar .nav-tab.active,
#sidebar .nav-tab:hover {
    color: #fff !important;
    background: rgba(255, 255, 255, .17) !important;
}

#sidebar .nav-tab.active {
    box-shadow: inset 3px 0 0 #fff;
}

#sidebar .sidebar-content {
    grid-column: 2;
    grid-row: 1 / span 2;
    width: var(--zalo-panel-width);
    min-width: 0;
    min-height: 0;
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden !important;
    border: 0 !important;
    background: #fff !important;
}

#sidebar .tab-content {
    min-width: 0;
    min-height: 0;
    padding: 18px 14px 12px !important;
    overflow-x: hidden;
    overflow-y: auto;
    background: #fff;
}

#sidebar .tab-content.active {
    display: flex !important;
    flex-direction: column;
}

#chats-content::before,
#friends-content::before,
#groups-content::before {
    display: block;
    flex: 0 0 auto;
    margin: 0 0 15px 2px;
    color: #172b4d;
    font-size: 20px;
    line-height: 1.3;
    font-weight: 700;
}

#chats-content::before { content: "Tin nhắn"; }
#friends-content::before { content: "Danh bạ"; }
#groups-content::before { content: "Nhóm"; }

#sidebar .search-container {
    flex: 0 0 auto;
    margin: 0 0 13px !important;
}

#sidebar .search-input {
    width: 100%;
    min-height: 38px;
    box-sizing: border-box;
    padding: 8px 13px 8px 36px !important;
    border: 1px solid #e4e9f0 !important;
    border-radius: 7px !important;
    outline: none;
    color: #283548 !important;
    background: #f2f4f7 !important;
    font-size: 13px !important;
    box-shadow: none !important;
}

#sidebar .search-input:focus {
    border-color: #91bfff !important;
    background: #fff !important;
}

#sidebar .chat-list,
#sidebar .friends-list,
#sidebar .groups-list {
    min-height: 0;
    gap: 3px !important;
    overflow-x: hidden;
    overflow-y: auto;
}

#sidebar .chat-item,
#sidebar .friend-item,
#sidebar .group-item {
    min-height: 68px;
    box-sizing: border-box;
    gap: 0;
    padding: 10px 9px !important;
    margin: 0 !important;
    border: 0 !important;
    border-radius: 9px !important;
    background: transparent;
    transition: background .14s ease;
}

#sidebar .chat-item:hover,
#sidebar .friend-item:hover,
#sidebar .group-item:hover {
    background: #f2f6fc !important;
}

#sidebar .chat-item.active,
#sidebar .friend-item.active,
#sidebar .group-item.active {
    border: 0 !important;
    background: #eaf3ff !important;
    box-shadow: inset 3px 0 0 var(--zalo-blue);
}

#sidebar .chat-avatar,
#sidebar .friend-avatar,
#sidebar .group-avatar {
    flex: 0 0 46px;
    width: 46px;
    height: 46px;
    margin: 0 11px 0 0 !important;
}

#sidebar .chat-avatar .avatar-circle,
#sidebar .friend-avatar .avatar-circle,
#sidebar .group-avatar .avatar-circle {
    width: 46px !important;
    height: 46px !important;
    font-size: 16px;
}

#sidebar .chat-details,
#sidebar .friend-details,
#sidebar .group-details {
    min-width: 0;
    flex: 1;
}

#sidebar .chat-name,
#sidebar .friend-name,
#sidebar .group-name {
    overflow: hidden;
    color: #172b4d !important;
    font-size: 13px !important;
    font-weight: 650 !important;
    line-height: 1.35;
    text-overflow: ellipsis;
    white-space: nowrap;
}

#sidebar .chat-status,
#sidebar .chat-description,
#sidebar .group-members,
#sidebar .friend-status {
    overflow: hidden;
    margin-top: 4px;
    color: #8792a2 !important;
    font-size: 11px !important;
    line-height: 1.25;
    text-overflow: ellipsis;
    white-space: nowrap;
}

#sidebar .chat-actions,
#sidebar .group-actions {
    right: 7px !important;
}

#sidebar .chat-settings-btn,
#sidebar .group-settings-btn {
    width: 27px;
    height: 27px;
    border: 0 !important;
    border-radius: 50%;
    color: #68778b !important;
    background: transparent !important;
}

#sidebar .chat-settings-btn:hover,
#sidebar .group-settings-btn:hover {
    color: var(--zalo-blue) !important;
    background: #eaf3ff !important;
}

#sidebar .chat-item.pinned {
    border: 0 !important;
    background: #f5f8fd !important;
}

#sidebar .action-buttons {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
}

#sidebar .btn-add-friend,
#sidebar .btn-create-group {
    min-height: 36px;
    padding: 7px 12px;
    border: 0;
    border-radius: 7px;
    background: var(--zalo-blue) !important;
    color: #fff !important;
    font-size: 12px;
    font-weight: 600;
}

#sidebar .friend-requests-section .section-title {
    margin: 9px 2px 10px;
    color: #697586;
    font-size: 12px;
    font-weight: 700;
}

/* Main conversation pane */
body.chat-page .main-wrapper {
    display: none !important;
}

body.chat-page .chat-container {
    position: fixed !important;
    inset: 0 0 0 var(--zalo-sidebar-width) !important;
    width: calc(100vw - var(--zalo-sidebar-width)) !important;
    height: 100vh !important;
    min-width: 0;
    display: flex !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow: hidden !important;
    background: #f4f6f9 !important;
}

body.chat-page .chat-main {
    width: 100%;
    min-width: 0;
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #f4f6f9;
}

body.chat-page .chat-header {
    min-height: 70px;
    flex: 0 0 70px;
    box-sizing: border-box;
    padding: 0 24px !important;
    border: 0 !important;
    border-bottom: 1px solid var(--zalo-line) !important;
    background: #fff !important;
    box-shadow: none !important;
}

body.chat-page #chatTitle h5 {
    margin: 0 0 3px;
    color: #172b4d;
    font-size: 16px;
    font-weight: 700;
}

body.chat-page #chatTitle small {
    color: #8590a0 !important;
    font-size: 11px;
}

body.chat-page .chat-header-actions {
    gap: 7px;
}

body.chat-page .chat-header-actions .btn {
    width: 36px;
    height: 36px;
    border: 0 !important;
    border-radius: 9px !important;
    background: #f1f6fd !important;
    box-shadow: none !important;
}

body.chat-page .chat-header-actions .btn:hover {
    transform: none;
    background: #e5f0ff !important;
}

body.chat-page .chat-pinned-bar {
    border: 0 !important;
    border-bottom: 1px solid var(--zalo-line) !important;
    background: #fff !important;
}

body.chat-page .chat-messages {
    flex: 1 1 auto;
    min-height: 0;
    padding: 24px clamp(18px, 4vw, 58px) !important;
    overflow-y: auto;
    background: #f4f6f9 !important;
}

body.chat-page .welcome-screen {
    min-height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent !important;
}

body.chat-page .welcome-content {
    max-width: 440px;
    padding: 36px 24px;
    text-align: center;
    color: #7b8797;
}

body.chat-page .welcome-content h1 {
    color: #30445f;
    font-size: 23px;
    font-weight: 700;
}

body.chat-page .chat-input {
    flex: 0 0 auto;
    min-height: 76px;
    box-sizing: border-box;
    padding: 12px 22px !important;
    border: 0 !important;
    border-top: 1px solid var(--zalo-line) !important;
    background: #fff !important;
}

body.chat-page .chat-input .input-group {
    gap: 8px;
    align-items: flex-end;
}

body.chat-page .chat-input textarea.form-control {
    min-height: 42px !important;
    padding: 10px 15px !important;
    border: 1px solid #e0e6ee !important;
    border-radius: 10px !important;
    background: #f7f9fc !important;
    color: #263445;
    box-shadow: none !important;
    font-size: 14px;
}

body.chat-page .chat-input textarea.form-control:focus {
    border-color: #9cc5ff !important;
    background: #fff !important;
}

body.chat-page .chat-input .btn {
    width: 40px;
    height: 40px;
    border: 0 !important;
    border-radius: 9px !important;
    color: #64748b;
    background: #f2f5f9 !important;
    box-shadow: none !important;
}

body.chat-page .chat-input .btn-primary {
    color: #fff !important;
    background: var(--zalo-blue) !important;
}

body.chat-page .chat-input .btn:hover {
    filter: brightness(.97);
}

/* Message bubbles follow Zalo's soft blue/white visual language. */
body.chat-page .message {
    max-width: min(78%, 760px);
    margin-bottom: 14px;
}

body.chat-page .message-bubble {
    border: 1px solid #e8edf3 !important;
    border-radius: 14px !important;
    padding: 12px 15px !important;
    box-shadow: 0 1px 2px rgba(25, 42, 70, .04) !important;
    line-height: 1.5;
}

body.chat-page .message.sent .message-bubble {
    border-color: #d5e8ff !important;
    border-bottom-right-radius: 4px !important;
    background: #e5f2ff !important;
    color: #23354a !important;
}

body.chat-page .message.received .message-bubble {
    border-bottom-left-radius: 4px !important;
    background: #fff !important;
    color: #273548 !important;
}

body.chat-page .message-time,
body.chat-page .message-timestamp {
    color: #95a0ae !important;
    font-size: 10px !important;
}

/* The feed uses the same left rail and a centered, light canvas. */
body:not(.chat-page) .main-wrapper {
    position: fixed !important;
    inset: 0 0 0 var(--zalo-sidebar-width) !important;
    display: block !important;
    width: auto !important;
    min-height: 0 !important;
    height: 100vh;
    box-sizing: border-box;
    margin: 0 !important;
    padding: 22px 26px !important;
    overflow-x: hidden;
    overflow-y: auto;
    background: #f3f5f8 !important;
}

body:not(.chat-page) .social-feed-page {
    width: 100%;
    max-width: 850px;
    margin: 0 auto;
    padding: 0 0 36px !important;
}

body:not(.chat-page) .sf-card {
    border: 1px solid #e7ebf0;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(31, 45, 61, .035);
}

/* Mobile: keep the conversation full-screen and show the side panel as a drawer. */
.mobile-sidebar-toggle {
    display: none;
}

@media (max-width: 1100px) and (min-width: 769px) {
    :root {
        --zalo-rail-width: 64px;
        --zalo-panel-width: 296px;
    }

    #sidebar .tab-content {
        padding-left: 11px !important;
        padding-right: 11px !important;
    }

    body.chat-page .chat-messages {
        padding-left: 22px !important;
        padding-right: 22px !important;
    }
}

@media (max-width: 768px) {
    :root {
        --zalo-rail-width: 64px;
        --zalo-panel-width: min(316px, calc(100vw - 64px));
        --zalo-sidebar-width: min(380px, 100vw - 48px);
    }

    #sidebar.sidebar {
        width: var(--zalo-sidebar-width) !important;
        grid-template-columns: var(--zalo-rail-width) minmax(0, 1fr);
        transform: translateX(-105%) !important;
        transition: transform .22s ease !important;
        box-shadow: 8px 0 28px rgba(16, 24, 40, .18) !important;
    }

    #sidebar.sidebar.show {
        transform: translateX(0) !important;
    }

    #sidebar .sidebar-content {
        width: auto;
    }

    body.chat-page .chat-container {
        inset: 0 !important;
        width: 100vw !important;
    }

    body:not(.chat-page) .main-wrapper {
        inset: 0 !important;
        padding: 14px 12px !important;
    }

    body.chat-page .chat-header {
        min-height: 62px;
        flex-basis: 62px;
        padding: 0 12px !important;
    }

    body.chat-page .chat-header-actions .btn {
        width: 32px;
        height: 32px;
    }

    body.chat-page .chat-messages {
        padding: 16px 12px !important;
    }

    body.chat-page .message {
        max-width: 92%;
    }

    body.chat-page .chat-input {
        padding: 9px 10px !important;
    }

    .mobile-sidebar-toggle {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        top: 12px !important;
        left: 12px !important;
        padding: 0 !important;
        border: 0 !important;
        border-radius: 10px !important;
        color: #fff !important;
        background: var(--zalo-blue) !important;
        box-shadow: 0 3px 10px rgba(0, 80, 190, .2);
    }
}

@media (prefers-reduced-motion: reduce) {
    #sidebar.sidebar,
    #sidebar .nav-tab,
    #sidebar .chat-item {
        transition: none !important;
    }
}


/* Social Feed page: use the same Zalo PC shell as the chat screen. */
body.zalo-app-page {
    --zalo-rail-width: 68px;
    --zalo-panel-width: 300px;
    --zalo-sidebar-width: 368px;
    min-height: 100vh !important;
    height: 100vh !important;
    overflow: hidden !important;
    margin: 0 !important;
    padding: 0 !important;
    background: #f3f5f8 !important;
    color: #172b4d;
    font-family: "Segoe UI", Roboto, Arial, sans-serif;
}

body.zalo-app-page #sidebar.sidebar {
    position: fixed !important;
    inset: 0 auto 0 0 !important;
    z-index: 1050 !important;
    display: grid !important;
    grid-template-columns: 68px 300px !important;
    grid-template-rows: 72px minmax(0, 1fr) !important;
    width: 368px !important;
    height: 100vh !important;
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

body.zalo-app-page #sidebar .user-profile-section {
    grid-column: 1 !important;
    grid-row: 1 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 68px !important;
    height: 72px !important;
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    border-right: 1px solid rgba(255,255,255,.15) !important;
    border-bottom: 1px solid rgba(255,255,255,.12) !important;
    border-radius: 0 !important;
    background: #0068ff !important;
}

body.zalo-app-page #sidebar .user-avatar { margin: 0 !important; }
body.zalo-app-page #sidebar .user-avatar .avatar-circle {
    width: 42px !important;
    height: 42px !important;
    font-size: 17px !important;
    border: 2px solid rgba(255,255,255,.9) !important;
}
body.zalo-app-page #sidebar .user-info,
body.zalo-app-page #sidebar .user-actions { display: none !important; }
body.zalo-app-page #sidebar #userDropdownMenu {
    position: fixed !important;
    top: 8px !important;
    left: 76px !important;
    right: auto !important;
    z-index: 1200 !important;
}

body.zalo-app-page #sidebar .nav-tabs-container {
    grid-column: 1 !important;
    grid-row: 2 !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: stretch !important;
    justify-content: flex-start !important;
    gap: 8px !important;
    width: 68px !important;
    min-height: 0 !important;
    margin: 0 !important;
    padding: 12px 6px !important;
    overflow-x: hidden !important;
    overflow-y: auto !important;
    border: 0 !important;
    border-right: 1px solid #e7ecf2 !important;
    border-radius: 0 !important;
    background: #0068ff !important;
}
body.zalo-app-page #sidebar .nav-tab {
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
}
body.zalo-app-page #sidebar .nav-tab i { font-size: 19px !important; }
body.zalo-app-page #sidebar .nav-tab .tab-text {
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
body.zalo-app-page #sidebar .nav-tab.active,
body.zalo-app-page #sidebar .nav-tab:hover {
    color: #fff !important;
    background: rgba(255,255,255,.17) !important;
}
body.zalo-app-page #sidebar .nav-tab.active { box-shadow: inset 3px 0 0 #fff !important; }
body.zalo-app-page #sidebar .nav-tab::after { display: none !important; }

body.zalo-app-page #sidebar .sidebar-content {
    grid-column: 2 !important;
    grid-row: 1 / span 2 !important;
    display: flex !important;
    flex-direction: column !important;
    width: 300px !important;
    min-width: 0 !important;
    min-height: 0 !important;
    height: 100% !important;
    overflow: hidden !important;
    background: #fff !important;
}
body.zalo-app-page #sidebar .tab-content {
    display: none !important;
    flex: 1 1 auto !important;
    flex-direction: column !important;
    min-width: 0 !important;
    min-height: 0 !important;
    padding: 17px 13px 12px !important;
    overflow-x: hidden !important;
    overflow-y: auto !important;
    background: #fff !important;
}
body.zalo-app-page #sidebar .tab-content.active { display: flex !important; }
body.zalo-app-page #sidebar #chats-content::before {
    content: "Tin nhắn";
    display: block;
    margin: 0 0 14px 2px;
    color: #172b4d;
    font-size: 20px;
    font-weight: 700;
}
body.zalo-app-page #sidebar .search-container { flex: 0 0 auto; margin: 0 0 12px !important; }
body.zalo-app-page #sidebar .search-input {
    width: 100% !important;
    min-height: 38px !important;
    box-sizing: border-box !important;
    padding: 8px 13px !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 7px !important;
    background: #f4f6f9 !important;
    box-shadow: none !important;
    font-size: 13px !important;
}
body.zalo-app-page #sidebar .chat-list,
body.zalo-app-page #sidebar .friends-list,
body.zalo-app-page #sidebar .groups-list {
    display: flex !important;
    flex: 1 1 auto !important;
    flex-direction: column !important;
    min-height: 0 !important;
    gap: 3px !important;
    overflow-y: auto !important;
}
body.zalo-app-page #sidebar .chat-item,
body.zalo-app-page #sidebar .friend-item,
body.zalo-app-page #sidebar .group-item {
    display: flex !important;
    align-items: center !important;
    min-height: 68px !important;
    box-sizing: border-box !important;
    margin: 0 !important;
    padding: 9px !important;
    border: 0 !important;
    border-radius: 9px !important;
    background: transparent !important;
}
body.zalo-app-page #sidebar .chat-item:hover,
body.zalo-app-page #sidebar .friend-item:hover,
body.zalo-app-page #sidebar .group-item:hover { background: #f2f6fc !important; }
body.zalo-app-page #sidebar .chat-item.active,
body.zalo-app-page #sidebar .friend-item.active,
body.zalo-app-page #sidebar .group-item.active {
    background: #eaf3ff !important;
    box-shadow: inset 3px 0 0 #0068ff !important;
}
body.zalo-app-page #sidebar .chat-name,
body.zalo-app-page #sidebar .friend-name,
body.zalo-app-page #sidebar .group-name {
    color: #172b4d !important;
    font-size: 13px !important;
    font-weight: 650 !important;
}

body.zalo-app-page .main-wrapper {
    position: fixed !important;
    inset: 0 0 0 368px !important;
    display: block !important;
    width: auto !important;
    height: 100vh !important;
    min-height: 0 !important;
    box-sizing: border-box !important;
    margin: 0 !important;
    padding: 22px 28px !important;
    overflow-x: hidden !important;
    overflow-y: auto !important;
    background: #f3f5f8 !important;
}
body.zalo-app-page .social-feed-page {
    width: 100% !important;
    max-width: 900px !important;
    margin: 0 auto !important;
    padding: 4px 0 36px !important;
    color: #172b4d !important;
    font-family: "Segoe UI", Roboto, Arial, sans-serif !important;
}
body.zalo-app-page .social-feed-header { margin: 0 0 18px !important; }
body.zalo-app-page .social-feed-header h1 {
    margin: 0 0 4px !important;
    color: #172b4d !important;
    font-size: 24px !important;
    font-weight: 700 !important;
}
body.zalo-app-page .social-feed-header h1 i { color: #0068ff !important; }
body.zalo-app-page .social-feed-header p { margin: 0 !important; color: #7b8797 !important; font-size: 13px !important; }
body.zalo-app-page .sf-card {
    margin-bottom: 14px !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 10px !important;
    background: #fff !important;
    box-shadow: 0 1px 3px rgba(25, 42, 70, .045) !important;
}
body.zalo-app-page .sf-create-card,
body.zalo-app-page .sf-post { padding: 16px 18px !important; }
body.zalo-app-page .sf-avatar { width: 40px !important; height: 40px !important; flex-basis: 40px !important; }
body.zalo-app-page .sf-post-form textarea {
    min-height: 82px !important;
    padding: 12px 14px !important;
    border: 1px solid #e1e7ef !important;
    border-radius: 9px !important;
    background: #f7f9fc !important;
    color: #273548 !important;
    font-size: 14px !important;
}
body.zalo-app-page .sf-image-label {
    border: 1px solid #cfe2ff !important;
    border-radius: 7px !important;
    background: #f0f6ff !important;
    color: #0068ff !important;
    font-size: 13px !important;
}
body.zalo-app-page .sf-primary-button {
    min-height: 38px !important;
    padding: 8px 16px !important;
    border-radius: 7px !important;
    background: #0068ff !important;
    font-size: 13px !important;
}
body.zalo-app-page .sf-feed-title { margin: 20px 2px 10px !important; }
body.zalo-app-page .sf-feed-title h2 { color: #172b4d !important; font-size: 16px !important; font-weight: 700 !important; }
body.zalo-app-page .sf-feed-title span { color: #7b8797 !important; font-size: 12px !important; }
body.zalo-app-page .sf-author-details strong { color: #172b4d !important; font-size: 14px !important; }
body.zalo-app-page .sf-author-details time { color: #8994a3 !important; font-size: 11px !important; }
body.zalo-app-page .sf-post-content { color: #273548 !important; font-size: 14px !important; line-height: 1.6 !important; }
body.zalo-app-page .sf-post-stats { color: #8792a2 !important; font-size: 12px !important; }
body.zalo-app-page .sf-post-actions { border-bottom-color: #edf0f4 !important; }
body.zalo-app-page .sf-action-button {
    min-height: 38px !important;
    padding: 7px 5px !important;
    border-radius: 7px !important;
    color: #637187 !important;
    font-size: 12px !important;
    font-weight: 600 !important;
}
body.zalo-app-page .sf-action-button:hover,
body.zalo-app-page .sf-action-button.is-selected { color: #0068ff !important; background: #edf5ff !important; }
body.zalo-app-page .sf-comment-bubble { background: #f1f4f8 !important; }
body.zalo-app-page .sf-comment-meta strong { color: #273548 !important; }
body.zalo-app-page .sf-comment-content { color: #4a586c !important; font-size: 13px !important; }
body.zalo-app-page .sf-comment-form input {
    min-height: 38px !important;
    border-color: #e1e7ef !important;
    background: #f7f9fc !important;
    font-size: 13px !important;
}
body.zalo-app-page .sf-comment-form button { background: #0068ff !important; }

@media (max-width: 768px) {
    body.zalo-app-page {
        --zalo-rail-width: 64px;
        --zalo-panel-width: min(316px, calc(100vw - 64px));
        --zalo-sidebar-width: min(380px, calc(100vw - 48px));
    }
    body.zalo-app-page #sidebar.sidebar {
        width: var(--zalo-sidebar-width) !important;
        grid-template-columns: 64px minmax(0, 1fr) !important;
        transform: translateX(-105%) !important;
        transition: transform .22s ease !important;
    }
    body.zalo-app-page #sidebar.sidebar.show { transform: translateX(0) !important; }
    body.zalo-app-page #sidebar .user-profile-section,
    body.zalo-app-page #sidebar .nav-tabs-container { width: 64px !important; }
    body.zalo-app-page #sidebar .sidebar-content { width: auto !important; }
    body.zalo-app-page .main-wrapper { inset: 0 !important; padding: 16px 12px !important; }
    body.zalo-app-page .social-feed-page { padding: 0 0 28px !important; }
    body.zalo-app-page .sf-create-card,
    body.zalo-app-page .sf-post { padding: 13px !important; }
    body.zalo-app-page .sf-post-actions { flex-wrap: wrap !important; }
}

</style>

<!-- JavaScript for sidebar functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mainWrapper = document.getElementById('mainWrapper');
    
    // Tab switching functionality
    const navTabs = document.querySelectorAll('.nav-tab');
    const tabContents = document.querySelectorAll('.tab-content');
    
    console.log('Found', navTabs.length, 'nav tabs');
    console.log('Found', tabContents.length, 'tab contents');
    
    // Initialize the same three-pane chat layout for Chats, Friends and Groups.
    const currentPage = '<?php echo basename($_SERVER['PHP_SELF']); ?>';
    const chatMain = document.querySelector('.chat-main');
    const urlParams = new URLSearchParams(window.location.search);
    if (currentPage === 'user_chatting.php' && chatMain) {
        chatMain.style.display = 'flex';
        const requestedTab = urlParams.get('group_id') ? 'groups' :
            (['chats', 'friends', 'groups'].includes(urlParams.get('tab')) ? urlParams.get('tab') : 'chats');
        navTabs.forEach(tab => tab.classList.toggle('active', tab.dataset.tab === requestedTab));
        tabContents.forEach(content => content.classList.toggle('active', content.id === requestedTab + '-content'));
    } else if (chatMain) {
        chatMain.style.display = 'none';
    }
    
    navTabs.forEach((tab, index) => {
        console.log(`Tab ${index}:`, tab.getAttribute('data-tab'), tab);
        
        // Add multiple event listeners to ensure clicks are captured
        tab.addEventListener('click', function(e) {
            console.log('Tab clicked:', this.getAttribute('data-tab'));
            const targetTab = this.getAttribute('data-tab');
            
            // Navigate to corresponding pages for different tabs
            switch(targetTab) {
                case 'chats':
                    console.log('Switching to chats tab content');
                    if (!chatMain) { window.location.href = 'user_chatting.php'; return; }
                    // For chats, switch tab content in sidebar and show main chat area
                    navTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    tabContents.forEach(content => content.classList.remove('active'));
                    const chatsTargetContent = document.getElementById(targetTab + '-content');
                    if (chatsTargetContent) {
                        chatsTargetContent.classList.add('active');
                    }
                    // Show main chat area when viewing chats
                    if (chatMain) {
                        chatMain.style.display = 'flex';
                    }
                    break;
                case 'friends':
                    console.log('Switching to friends tab content');
                    if (!chatMain) { window.location.href = 'user_chatting.php?tab=friends'; return; }
                    // Keep the shared conversation pane visible while browsing friends.
                    navTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    tabContents.forEach(content => content.classList.remove('active'));
                    const friendsTargetContent = document.getElementById(targetTab + '-content');
                    if (friendsTargetContent) {
                        friendsTargetContent.classList.add('active');
                    }
                    if (chatMain) chatMain.style.display = 'flex';
                    break;
                case 'groups':
                    console.log('Switching to groups tab content');
                    if (!chatMain) { window.location.href = 'user_chatting.php?tab=groups'; return; }
                    // Keep the shared conversation pane visible while browsing groups.
                    navTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    tabContents.forEach(content => content.classList.remove('active'));
                    const groupsTargetContent = document.getElementById(targetTab + '-content');
                    if (groupsTargetContent) {
                        groupsTargetContent.classList.add('active');
                    }
                    if (chatMain) chatMain.style.display = 'flex';
                    // Do not leave a private conversation displayed under the Groups tab.
                    // Show the shared chat pane with a group-specific empty state until a group is selected.
                    const groupMessages = document.getElementById('chatMessages');
                    if (groupMessages && window.currentChatType !== 'group') {
                        window.currentChatId = null;
                        window.currentChatType = null;
                        groupMessages.innerHTML = '<div class="welcome-screen"><div class="welcome-content"><h1>Nhóm chat</h1><p>Chọn một nhóm ở danh sách bên trái để bắt đầu trò chuyện.</p></div></div>';
                        const groupTitle = document.getElementById('chatTitle');
                        if (groupTitle) groupTitle.innerHTML = '<h5><i class="fas fa-layer-group"></i> Nhóm chat</h5><small class="text-muted">Chọn một nhóm để xem tin nhắn</small>';
                        const groupInput = document.getElementById('chatInput');
                        if (groupInput) groupInput.style.display = 'none';
                        const groupActions = document.getElementById('chatHeaderActions');
                        if (groupActions) groupActions.style.display = 'none';
                    }
                    break;
                case 'forums':
                    console.log('Switching to forums tab content');
                    // For forums, switch tab content in sidebar and hide main chat area
                    navTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    tabContents.forEach(content => content.classList.remove('active'));
                    const forumsTargetContent = document.getElementById(targetTab + '-content');
                    if (forumsTargetContent) {
                        forumsTargetContent.classList.add('active');
                    }
                    // Hide main chat area when viewing forums
                    if (chatMain) {
                        chatMain.style.display = 'none';
                    }
                    break;
                default:
                    console.log('Default behavior for tab:', targetTab);
                    // Default behavior for other tabs
                    navTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    tabContents.forEach(content => content.classList.remove('active'));
                    const defaultTargetContent = document.getElementById(targetTab + '-content');
                    if (defaultTargetContent) {
                        defaultTargetContent.classList.add('active');
                    }
                    // Hide main chat area for other tabs
                    if (chatMain) {
                        chatMain.style.display = 'none';
                    }
                    break;
            }
        });
        
        // Also add mousedown event as backup
        tab.addEventListener('mousedown', function(e) {
            console.log('Tab mousedown:', this.getAttribute('data-tab'));
        });
    });
    
    // Add Friend button functionality
    const addFriendBtn = document.querySelector('.btn-add-friend');
    if (addFriendBtn) {
        console.log('Add Friend button found and event listener added');
        addFriendBtn.addEventListener('click', function(e) {
            console.log('Add Friend button clicked');
            // If we're on group.php, go back to user_chatting.php first
            if (window.location.pathname.includes('group.php')) {
                window.location.href = 'user_chatting.php';
                return;
            }
            showAddFriendModal();
        });
    } else {
        console.log('Add Friend button not found - may not be on friends tab');
    }
    
    // Create Group button functionality
    const createGroupBtn = document.querySelector('.btn-create-group');
    if (createGroupBtn) {
        console.log('Create Group button found and event listener added');
        createGroupBtn.addEventListener('click', function(e) {
            console.log('Create Group button clicked');
            // If we're on group.php, go back to user_chatting.php first
            if (window.location.pathname.includes('group.php')) {
                window.location.href = 'user_chatting.php';
                return;
            }
            showCreateGroupModal();
        });
    } else {
        console.log('Create Group button not found - may not be on groups tab');
    }
    
    // Create Forum button functionality
    const createForumBtn = document.querySelector('.btn-create-forum');
    if (createForumBtn) {
        console.log('Create Forum button found and event listener added');
        createForumBtn.addEventListener('click', function(e) {
            console.log('Create Forum button clicked');
            showCreateForumModal();
        });
    } else {
        console.log('Create Forum button not found - may not be on forums tab');
    }
    
    // Join Forum button functionality
    const joinForumBtn = document.querySelector('.btn-join-forum');
    if (joinForumBtn) {
        console.log('Join Forum button found and event listener added');
        joinForumBtn.addEventListener('click', function(e) {
            console.log('Join Forum button clicked');
            // Show join forum modal instead of navigating
            showJoinForumModal();
        });
    } else {
        console.log('Join Forum button not found - may not be on forums tab');
    }
    
    // Add Friend modal functionality
    function showAddFriendModal() {
        // Check if Bootstrap is loaded
        if (typeof bootstrap === 'undefined') {
            alert('Bootstrap is not loaded yet. Please wait a moment and try again.');
            return;
        }
        
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
        const sendRequestButton = document.getElementById('sendFriendRequest');
        if (sendRequestButton) {
            sendRequestButton.addEventListener('click', function() {
                const username = document.getElementById('friendUsername').value.trim();
                if (username) {
                    sendFriendRequest(username);
                    bootstrapModal.hide();
                } else {
                    alert('Please enter a username');
                }
            });
        }
        
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
    
    // Create Group modal functionality
    function showCreateGroupModal() {
        // Check if Bootstrap is loaded
        if (typeof bootstrap === 'undefined') {
            alert('Bootstrap is not loaded yet. Please wait a moment and try again.');
            return;
        }
        
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'createGroupModal';
        modal.setAttribute('tabindex', '-1');
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Create Group</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="groupName" class="form-label">Group Name</label>
                            <input type="text" class="form-control" id="groupName" placeholder="Enter group name">
                        </div>
                        <div class="mb-3">
                            <label for="groupDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="groupDescription" rows="3" placeholder="Enter group description"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="createGroupBtn">Create Group</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Show modal
        const bootstrapModal = new bootstrap.Modal(modal);
        bootstrapModal.show();
        
        // Handle create group
        const createGroupButton = document.getElementById('createGroupBtn');
        if (createGroupButton) {
            createGroupButton.addEventListener('click', function() {
                console.log('Create Group button clicked'); // Debug log
                const name = document.getElementById('groupName').value.trim();
                const description = document.getElementById('groupDescription').value.trim();
                
                console.log('Form values:', { name, description }); // Debug log
                
                if (name) {
                    createGroup(name, description);
                    bootstrapModal.hide();
                } else {
                    alert('Please enter a group name');
                }
            });
        } else {
            console.error('Create Group button not found'); // Debug log
        }
        
        // Remove modal from DOM when hidden
        modal.addEventListener('hidden.bs.modal', function() {
            document.body.removeChild(modal);
        });
    }
    
    // Create group function
    function createGroup(name, description) {
        console.log('Creating group:', name, description); // Debug log
        
        fetch('ajax/group_handler.php?action=create_group', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                name: name, 
                description: description 
            })
        })
        .then(response => {
            console.log('Response status:', response.status); // Debug log
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data); // Debug log
            if (data.success) {
                alert('Group created successfully!');
                // Refresh the page to show the new group
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error creating group:', error);
            alert('Error creating group. Please try again.');
        });
    }
    
    // Create Forum modal functionality
    function showCreateForumModal() {
        // Check if Bootstrap is loaded
        if (typeof bootstrap === 'undefined') {
            alert('Bootstrap is not loaded yet. Please wait a moment and try again.');
            return;
        }
        
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'createForumModal';
        modal.setAttribute('tabindex', '-1');
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Create Forum</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="forumName" class="form-label">Forum Name</label>
                            <input type="text" class="form-control" id="forumName" placeholder="Enter forum name">
                        </div>
                        <div class="mb-3">
                            <label for="forumDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="forumDescription" rows="3" placeholder="Enter forum description"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="createForumBtn">Create Forum</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Show modal
        const bootstrapModal = new bootstrap.Modal(modal);
        bootstrapModal.show();
        
        // Handle create forum
        const createForumButton = document.getElementById('createForumBtn');
        if (createForumButton) {
            createForumButton.addEventListener('click', function() {
                const name = document.getElementById('forumName').value.trim();
                const description = document.getElementById('forumDescription').value.trim();
                
                if (name) {
                    createForum(name, description);
                    bootstrapModal.hide();
                } else {
                    alert('Please enter a forum name');
                }
            });
        }
        
        // Remove modal from DOM when hidden
        modal.addEventListener('hidden.bs.modal', function() {
            document.body.removeChild(modal);
        });
    }
    
    // Create forum function
    function createForum(name, description) {
        console.log('Creating forum:', name, description);
        
        fetch('ajax/forum_handler.php?action=create_forum', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                name: name, 
                description: description 
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Forum created successfully!');
                // Refresh the forums list instead of navigating away
                refreshForumsList();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error creating forum:', error);
            alert('Error creating forum. Please try again.');
        });
    }
    
    // Refresh forums list function
    function refreshForumsList() {
        fetch('ajax/forum_handler.php?action=get_all_forums')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const forumsList = document.querySelector('.forums-list');
                if (forumsList) {
                    forumsList.innerHTML = '';
                    
                    if (data.forums && data.forums.length > 0) {
                        data.forums.slice(0, 10).forEach(forum => {
                            const forumItem = document.createElement('div');
                            forumItem.className = 'forum-item';
                            
                            const forumName = forum.name;
                            const shortName = forumName.length > 20 ? forumName.substring(0, 20) + '...' : forumName;
                            const avatarChar = forumName.charAt(0).toUpperCase();
                            const postCount = forum.post_count || 0;
                            
                            forumItem.innerHTML = `
                                <div class="forum-main-content" onclick="location.href='forum.php?id=${forum.id}'">
                                    <div class="forum-avatar">
                                        ${forum.avatar ? 
                                            '<img src="uploads/group_avatars/' + forum.avatar + '?t=' + Date.now() + '" alt="Forum Avatar" class="avatar-circle" style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%;">' :
                                            '<div class="avatar-circle purple">' + avatarChar + '</div>'
                                        }
                                    </div>
                                    <div class="forum-details">
                                        <div class="forum-name" title="${forumName}">${shortName}</div>
                                        <div class="forum-info">${postCount} Posts</div>
                                    </div>
                                </div>
                                <button class="btn-forum-settings" onclick="event.stopPropagation(); window.location.href='forum_settings.php?id=${forum.id}';" title="Settings">
                                    <i class="fas fa-cog"></i>
                                </button>
                            `;
                            
                            forumsList.appendChild(forumItem);
                        });
                    } else {
                        forumsList.innerHTML = `
                            <div class="forum-item">
                                <div class="forum-avatar">
                                    <div class="avatar-circle purple">S</div>
                                </div>
                                <div class="forum-details">
                                    <div class="forum-name">HONKAI STAR RAIL</div>
                                    <div class="forum-info">0 Posts</div>
                                </div>
                            </div>
                        `;
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error refreshing forums list:', error);
        });
    }
    
    // Show forum settings modal
    window.showForumSettings = function(forumId, forumName, createdBy) {
        // Check if current user is the creator
        const currentUserId = window.currentUserId || <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
        
        // Debug info
        console.log('Permission check debug info:');
        console.log('currentUserId:', currentUserId, 'type:', typeof currentUserId);
        console.log('createdBy:', createdBy, 'type:', typeof createdBy);
        console.log('Comparison result (==):', currentUserId == createdBy);
        console.log('Comparison result (===):', currentUserId === createdBy);
        console.log('Numeric comparison:', Number(currentUserId) === Number(createdBy));
        
        // Use loose comparison and numeric coercion for compatibility
        if (currentUserId != createdBy && Number(currentUserId) !== Number(createdBy)) {
            alert('Only the forum creator can modify forum settings');
            console.log('Permission check failed');
            return;
        }
        
        console.log('Permission check passed, opening settings modal');
        
        // Get forum details
        fetch(`ajax/forum_handler.php?action=get_forum_info&forum_id=${forumId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showForumSettingsModal(forumId, forumName, data.forum);
            } else {
                alert('Failed to get forum info: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error fetching forum info:', error);
            // Use defaults if fetch fails
            showForumSettingsModal(forumId, forumName, { description: '' });
        });
    }
    
    function showForumSettingsModal(forumId, forumName, forumInfo) {
        
        // Create modal
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'forumSettingsModal';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Forum Settings - ${forumName}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">Forum Statistics</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="stat-item">
                                                    <div class="stat-number">${forumInfo.post_count || 0}</div>
                                                    <div class="stat-label">Posts</div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="stat-item">
                                                    <div class="stat-number">${forumInfo.member_count || 0}</div>
                                                    <div class="stat-label">Members</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">Creation Info</h6>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                Created At: ${forumInfo.created_at ? new Date(forumInfo.created_at).toLocaleDateString('en-US') : 'Unknown'}
                                            </small>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <ul class="nav nav-tabs" id="forumSettingsTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="avatar-tab" data-bs-toggle="tab" data-bs-target="#avatar" type="button" role="tab">
                                    <i class="fas fa-image"></i> Change Avatar
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit" type="button" role="tab">
                                    <i class="fas fa-edit"></i> Edit Info
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="delete-tab" data-bs-toggle="tab" data-bs-target="#delete" type="button" role="tab">
                                    <i class="fas fa-trash"></i> Delete Forum
                                </button>
                            </li>
                        </ul>
                        <div class="tab-content" id="forumSettingsTabContent">
                            <div class="tab-pane fade show active" id="avatar" role="tabpanel">
                                <div class="mt-3">
                                    <h6>Current Avatar</h6>
                                    <div class="current-avatar mb-3">
                                        <div class="avatar-circle purple" style="width: 80px; height: 80px; font-size: 2rem;">${forumName.charAt(0).toUpperCase()}</div>
                                    </div>
                                    <form id="avatarForm" enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <label for="avatarFile" class="form-label">Select New Avatar</label>
                                            <input type="file" class="form-control" id="avatarFile" name="avatar" accept="image/*">
                                        </div>
                                        <button type="submit" class="btn btn-primary">Upload Avatar</button>
                                    </form>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="edit" role="tabpanel">
                                <div class="mt-3">
                                    <form id="editForumForm">
                                        <div class="mb-3">
                                            <label for="forumName" class="form-label">Forum Name</label>
                                            <input type="text" class="form-control" id="forumName" value="${forumName}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="forumDescription" class="form-label">Forum Description</label>
                                            <textarea class="form-control" id="forumDescription" rows="3" placeholder="Enter forum description...">${forumInfo.description || ''}</textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                    </form>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="delete" role="tabpanel">
                                <div class="mt-3">
                                    <div class="alert alert-danger">
                                        <h6><i class="fas fa-exclamation-triangle"></i> Danger Zone</h6>
                                        <p>Deleting the forum will permanently remove all related data, including:</p>
                                        <ul>
                                            <li>All posts</li>
                                            <li>All replies</li>
                                            <li>All media files</li>
                                        </ul>
                                        <p class="mb-0"><strong>This action cannot be undone!</strong></p>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirmDelete" class="form-label">Enter forum name to confirm deletion:</label>
                                        <input type="text" class="form-control" id="confirmDelete" placeholder="${forumName}">
                                    </div>
                                    <button type="button" class="btn btn-danger" onclick="deleteForum(${forumId}, '${forumName}')">Delete Forum</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Show modal
        const bootstrapModal = new bootstrap.Modal(modal);
        bootstrapModal.show();
        
        // Initialize Bootstrap tabs
        const tabTriggerList = modal.querySelectorAll('[data-bs-toggle="tab"]');
        const tabList = [...tabTriggerList].map(tabTriggerEl => new bootstrap.Tab(tabTriggerEl));
        
        // Handle tab switching manually if Bootstrap tabs don't work
        const tabButtons = modal.querySelectorAll('[data-bs-toggle="tab"]');
        const tabPanes = modal.querySelectorAll('.tab-pane');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active class from all buttons and panes
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabPanes.forEach(pane => {
                    pane.classList.remove('show', 'active');
                });
                
                // Add active class to clicked button
                this.classList.add('active');
                
                // Show corresponding pane
                const targetId = this.getAttribute('data-bs-target');
                const targetPane = modal.querySelector(targetId);
                if (targetPane) {
                    targetPane.classList.add('show', 'active');
                }
            });
        });
        
        // Handle avatar upload - event delegation
        modal.addEventListener('submit', function(e) {
            if (e.target.id === 'avatarForm') {
                e.preventDefault();
                uploadForumAvatar(forumId);
            }
        });
        
        // Handle edit form - event delegation
        modal.addEventListener('submit', function(e) {
            if (e.target.id === 'editForumForm') {
                e.preventDefault();
                editForum(forumId);
            }
        });
        
        // Handle delete button - event delegation
        modal.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-danger') && e.target.textContent.includes('Delete Forum')) {
                e.preventDefault();
                deleteForum(forumId, forumName);
            }
        });
        
        // Remove modal from DOM when hidden
        modal.addEventListener('hidden.bs.modal', function() {
            document.body.removeChild(modal);
        });
    }
    
    // Upload forum avatar
    window.uploadForumAvatar = function(forumId) {
        console.log('uploadForumAvatar called with forumId:', forumId);
        
        const fileInput = document.getElementById('avatarFile');
        console.log('fileInput found:', fileInput);
        
        if (!fileInput) {
            alert('File input not found');
            return;
        }
        
        const file = fileInput.files[0];
        console.log('selected file:', file);
        
        if (!file) {
            alert('Please select an avatar file');
            return;
        }
        
        console.log('Starting avatar upload...');
        
        const formData = new FormData();
        formData.append('avatar', file);
        formData.append('forum_id', forumId);
        
        fetch('ajax/forum_handler.php?action=upload_avatar', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Upload response:', response);
            return response.json();
        })
        .then(data => {
            console.log('Upload result:', data);
            if (data.success) {
                alert('Avatar uploaded successfully!');
                location.reload();
            } else {
                alert('Avatar upload failed: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error uploading avatar:', error);
            alert('Avatar upload failed, please try again');
        });
    }
    
    // Edit forum
    window.editForum = function(forumId) {
        const name = document.getElementById('forumName').value.trim();
        const description = document.getElementById('forumDescription').value.trim();
        
        if (!name) {
            alert('Please enter forum name');
            return;
        }
        
        fetch('ajax/forum_handler.php?action=edit_forum', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                forum_id: forumId,
                name: name,
                description: description
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Forum info updated successfully!');
                location.reload();
            } else {
                alert('Update failed: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error editing forum:', error);
            alert('Update failed, please try again');
        });
    }
    
    // Delete forum
    window.deleteForum = function(forumId, forumName) {
        console.log('deleteForum called with forumId:', forumId, 'forumName:', forumName);
        
        const confirmInput = document.getElementById('confirmDelete');
        console.log('confirmInput found:', confirmInput);
        
        if (!confirmInput) {
            alert('Confirmation input not found');
            return;
        }
        
        const confirmText = confirmInput.value.trim();
        console.log('confirmText:', confirmText, 'forumName:', forumName);
        
        if (confirmText !== forumName) {
            alert('Please enter the correct forum name to confirm deletion');
            return;
        }
        
        if (!confirm('Are you sure you want to delete forum "' + forumName + '" "? This action cannot be undone!')) {
            return;
        }
        
        console.log('Starting forum deletion...');
        
        fetch('ajax/forum_handler.php?action=delete_forum', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                forum_id: forumId
            })
        })
        .then(response => {
            console.log('Delete response:', response);
            return response.json();
        })
        .then(data => {
            console.log('Delete result:', data);
            if (data.success) {
                alert('Forum deleted successfully!');
                location.reload();
            } else {
                alert('Deletion failed: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error deleting forum:', error);
            alert('Deletion failed, please try again');
        });
    }
    
    // Join Forum functionality - simply navigate to forum page
    function showJoinForumModal() {
        // Since this is an open forum system, just navigate to the forum page
        window.location.href = 'forum.php';
    }
    
    // Friend request actions
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-accept-request')) {
            const button = e.target.closest('.btn-accept-request');
            const userId = button.dataset.userId;
            handleFriendRequest(userId, 'accept');
        }
        
        if (e.target.closest('.btn-reject-request')) {
            const button = e.target.closest('.btn-reject-request');
            const userId = button.dataset.userId;
            handleFriendRequest(userId, 'reject');
        }
    });
    
    function handleFriendRequest(friendId, action) {
        const url = action === 'accept' ? 'ajax/friend_handler.php?action=accept_request' : 'ajax/friend_handler.php?action=reject_request';
        
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ friend_id: friendId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (action === 'accept') {
                    alert('Friend request accepted!');
                } else {
                    alert('Friend request rejected.');
                }
                // Refresh the page to update the friend list
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error handling friend request:', error);
            alert('Error handling friend request. Please try again.');
        });
    }
    
    // Open a one-to-one conversation from either Chats or Friends.
    function activateChatsTab() {
        document.querySelectorAll('.nav-tab').forEach(function (tab) {
            if (tab.dataset.tab !== 'social-feed') tab.classList.remove('active');
        });
        const chatsTab = document.querySelector('.nav-tab[data-tab="chats"]');
        if (chatsTab) chatsTab.classList.add('active');

        document.querySelectorAll('.tab-content').forEach(function (content) {
            content.classList.remove('active');
        });
        const chatsContent = document.getElementById('chats-content');
        if (chatsContent) chatsContent.classList.add('active');

        const chatMain = document.querySelector('.chat-main');
        if (chatMain) chatMain.style.display = 'flex';
    }

    function activateGroupsTab() {
        document.querySelectorAll('.nav-tab').forEach(function (tab) {
            tab.classList.toggle('active', tab.dataset.tab === 'groups');
        });
        document.querySelectorAll('.tab-content').forEach(function (content) {
            content.classList.toggle('active', content.id === 'groups-content');
        });
        const chatMain = document.querySelector('.chat-main');
        if (chatMain) chatMain.style.display = 'flex';
    }

    function openGroupConversation(groupId, groupName, sourceElement) {
        if (!groupId) return;
        if (!document.getElementById('chatMessages') || !document.querySelector('.chat-main')) {
            window.location.href = 'user_chatting.php?group_id=' + encodeURIComponent(groupId);
            return;
        }

        if (sourceElement && sourceElement.classList.contains('chat-item')) {
            activateChatsTab();
        } else {
            activateGroupsTab();
        }
        document.querySelectorAll('.chat-item, .friend-item, .group-item').forEach(function (item) {
            item.classList.remove('active');
        });
        const safeId = String(groupId).replace(/[^0-9]/g, '');
        document.querySelectorAll('.group-item[data-group-id="' + safeId + '"], .chat-item[data-chat-type="group"][data-group-id="' + safeId + '"]').forEach(function (item) {
            item.classList.add('active');
        });
        if (sourceElement) sourceElement.classList.add('active');

        const safeName = groupName || 'Nhóm chat';
        if (typeof window.updateChatInterface === 'function') {
            window.updateChatInterface(safeName, 'group', groupId);
            const title = document.querySelector('#chatTitle h5');
            if (title) {
                title.textContent = '';
                const icon = document.createElement('i');
                icon.className = 'fas fa-layer-group';
                title.appendChild(icon);
                title.appendChild(document.createTextNode(' ' + safeName));
            }
        }
        if (typeof window.loadMessages === 'function') {
            window.loadMessages(groupId, 'group');
        } else {
            window.location.href = 'user_chatting.php?group_id=' + encodeURIComponent(groupId);
        }
    }
    window.openGroupConversation = openGroupConversation;

    function openPrivateConversation(userId, username, displayName, sourceElement) {
        if (!userId) return;

        // Pages such as the feed do not contain the message pane. Navigate to
        // the chat page and restore the selected conversation there.
        if (!document.getElementById('chatMessages') || !document.querySelector('.chat-main')) {
            window.location.href = 'user_chatting.php?user_id=' + encodeURIComponent(userId);
            return;
        }

        activateChatsTab();
        document.querySelectorAll('.chat-item, .friend-item, .group-item').forEach(function (item) {
            item.classList.remove('active');
        });

        const chatItem = document.querySelector('.chat-item[data-chat-type="user"][data-user-id="' + String(userId).replace(/[^0-9]/g, '') + '"]');
        if (chatItem) chatItem.classList.add('active');
        if (sourceElement) sourceElement.classList.add('active');

        const safeName = displayName || username || 'Cuộc trò chuyện';
        window.currentChatId = userId;
        window.currentChatType = 'user';

        if (typeof window.updateChatInterface === 'function') {
            window.updateChatInterface(safeName, 'user', userId);
            // Set the visible title through textContent so a user's name is
            // displayed as text instead of being interpreted as HTML.
            const title = document.querySelector('#chatTitle h5');
            if (title) {
                title.textContent = '';
                const icon = document.createElement('i');
                icon.className = 'fas fa-user';
                title.appendChild(icon);
                title.appendChild(document.createTextNode(' ' + safeName));
            }
        }

        if (typeof window.loadMessages === 'function') {
            window.loadMessages(userId, 'user');
        } else {
            console.error('Chat message loader is not available on this page.');
        }
    }

    document.addEventListener('click', function (e) {
        const chatItem = e.target.closest('.chat-item');
        if (chatItem && !e.target.closest('.chat-settings-btn')) {
            const chatType = chatItem.dataset.chatType;
            if (chatType === 'user') {
                openPrivateConversation(
                    chatItem.dataset.userId,
                    chatItem.dataset.username,
                    chatItem.dataset.fullName || chatItem.querySelector('.chat-name')?.textContent,
                    chatItem
                );
            } else if (chatType === 'group') {
                const groupId = chatItem.dataset.groupId;
                if (groupId) openGroupConversation(groupId, chatItem.dataset.groupName || chatItem.querySelector('.chat-name')?.textContent, chatItem);
            }
            return;
        }

        const friendItem = e.target.closest('.friend-item');
        if (friendItem) {
            openPrivateConversation(
                friendItem.dataset.userId,
                friendItem.dataset.username,
                friendItem.dataset.fullName || friendItem.querySelector('.friend-name')?.textContent,
                friendItem
            );
        }
    });

    // Chat settings button (event delegation)
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.chat-settings-btn');
        if (!btn) return;
        const chatItem = btn.closest('.chat-item');
        if (!chatItem) return;
        e.stopPropagation();
        const chatType = chatItem.dataset.chatType;
        let chatId = chatType === 'user' ? btn.dataset.userId : btn.dataset.groupId;
        let chatName = chatType === 'user' ? btn.dataset.username : btn.dataset.groupName;
        if (chatId && typeof showChatSettingsMenu === 'function') {
            showChatSettingsMenu(chatId, chatName, chatType);
        }
    });
    
    // Initial group event binding
    bindGroupEvents();
    
    // Event delegation: every group opens inside the same Zalo-style chat pane.
    document.addEventListener('click', function(e) {
        const groupItem = e.target.closest('.group-item');
        if (!groupItem || e.target.closest('.group-settings-btn')) return;
        const groupId = groupItem.dataset.groupId;
        if (!groupId) return;
        e.preventDefault();
        e.stopPropagation();
        openGroupConversation(groupId, groupItem.dataset.groupName || groupItem.querySelector('.group-name')?.textContent, groupItem);
    });
    
    // Mobile menu toggle
    function toggleMobileSidebar() {
        sidebar.classList.toggle('show');
    }
    
    // Add mobile toggle button to pages that need it
    if (window.innerWidth <= 768) {
        const mobileToggleBtn = document.createElement('button');
        mobileToggleBtn.className = 'mobile-sidebar-toggle';
        mobileToggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
        mobileToggleBtn.style.cssText = `
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: var(--sidebar-active);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        `;
        mobileToggleBtn.addEventListener('click', toggleMobileSidebar);
        document.body.appendChild(mobileToggleBtn);
    }
    
    // User avatar dropdown functionality
    const userAvatarDropdown = document.getElementById('userAvatarDropdown');
    const userDropdownMenu = document.getElementById('userDropdownMenu');
    
    if (userAvatarDropdown && userDropdownMenu) {
        userAvatarDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdownMenu.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userAvatarDropdown.contains(e.target)) {
                userDropdownMenu.classList.remove('show');
            }
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(e.target) && 
                !e.target.closest('.mobile-sidebar-toggle')) {
                sidebar.classList.remove('show');
            }
        }
    });
    
    // Dropdown menu functions
    window.showProfile = function() {
        // If we're on group.php, go back to user_chatting.php first
        if (window.location.pathname.includes('group.php')) {
            window.location.href = 'user_chatting.php';
        } else {
            window.location.href = 'profile.php';
        }
    };
    
    window.showBlockedUsers = function() {
        // If we're on group.php, go back to user_chatting.php first
        if (window.location.pathname.includes('group.php')) {
            window.location.href = 'user_chatting.php';
        } else {
            showBlockedUsersModal();
        }
    };
    
    window.showBlacklist = function() {
        // If we're on group.php, go back to user_chatting.php first
        if (window.location.pathname.includes('group.php')) {
            window.location.href = 'user_chatting.php';
        } else {
            showBlacklistModal();
        }
    };
    
    window.showSettings = function() {
        // If we're on group.php, go back to user_chatting.php first
        if (window.location.pathname.includes('group.php')) {
            window.location.href = 'user_chatting.php';
        } else {
            showSettingsModal();
        }
    };
    
    window.logout = function() {
        if (confirm('Are you sure you want to log out?')) {
            window.location.href = 'logout.php';
        }
    };
    
    // Modal functions
    function showBlockedUsersModal() {
        // Check if Bootstrap is loaded
        if (typeof bootstrap === 'undefined') {
            alert('Bootstrap is not loaded yet. Please wait a moment and try again.');
            return;
        }
        
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'blockedUsersModal';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Block User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Blocked users will be listed here</p>
                        <div class="text-center text-muted">
                            <i class="fas fa-ban fa-3x mb-3"></i>
                            <p>No blocked users</p>
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
    
    function showBlacklistModal() {
        // Check if Bootstrap is loaded
        if (typeof bootstrap === 'undefined') {
            alert('Bootstrap is not loaded yet. Please wait a moment and try again.');
            return;
        }
        
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'blacklistModal';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Blacklist</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Blacklisted users will be listed here</p>
                        <div class="text-center text-muted">
                            <i class="fas fa-user-slash fa-3x mb-3"></i>
                            <p>Blacklist is empty</p>
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
    
    function showSettingsModal() {
        // Check if Bootstrap is loaded
        if (typeof bootstrap === 'undefined') {
            alert('Bootstrap is not loaded yet. Please wait a moment and try again.');
            return;
        }
        
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'settingsModal';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Settings</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Notification Settings</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="notifications" checked>
                                <label class="form-check-label" for="notifications">
                                    Receive message notifications
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Privacy Settings</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="onlineStatus" checked>
                                <label class="form-check-label" for="onlineStatus">
                                    Show online status
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="saveSettings()">Save</button>
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
    
    window.saveSettings = function() {
        // Here you would save the settings to the server
        alert('Settings saved!');
        if (typeof bootstrap !== 'undefined') {
            const modal = bootstrap.Modal.getInstance(document.getElementById('settingsModal'));
            if (modal) {
                modal.hide();
            }
        }
    };
    
    // Chat settings menu function
    function showChatSettingsMenu(chatId, chatName, chatType) {
        // Remove any existing settings menu
        const existingMenu = document.querySelector('.chat-settings-menu');
        if (existingMenu) {
            existingMenu.remove();
        }
        
        const menu = document.createElement('div');
        menu.className = 'chat-settings-menu';
        menu.style.cssText = `
            position: fixed;
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            min-width: 180px;
            padding: 8px 0;
        `;
        
        let menuItems = '';
        
        if (chatType === 'user') {
            menuItems = `
                <div class="menu-item" onclick="pinChat(${chatId}, 'user')">
                    <i class="fas fa-thumbtack"></i>
                    <span>Pin Chat</span>
                </div>
                <div class="menu-item" onclick="viewUserProfile(${chatId})">
                    <i class="fas fa-user"></i>
                    <span>View Profile</span>
                </div>
                <div class="menu-divider"></div>
                <div class="menu-item danger" onclick="deleteFriend(${chatId}, '${chatName}')">
                    <i class="fas fa-user-times"></i>
                    <span>Delete Friend</span>
                </div>
            `;
        } else if (chatType === 'group') {
            menuItems = `
                <div class="menu-item" onclick="pinChat(${chatId}, 'group')">
                    <i class="fas fa-thumbtack"></i>
                    <span>Pin Chat</span>
                </div>
                <div class="menu-item" onclick="viewGroupInfo(${chatId})">
                    <i class="fas fa-info-circle"></i>
                    <span>Group Info</span>
                </div>
                <div class="menu-divider"></div>
                <div class="menu-item danger" onclick="leaveGroup(${chatId}, '${chatName}')">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Leave Group</span>
                </div>
            `;
        }
        
        menu.innerHTML = menuItems;
        
        // Position menu near the clicked button
        const button = event.target.closest('.chat-settings-btn');
        const rect = button.getBoundingClientRect();
        menu.style.left = (rect.left - 150) + 'px';
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
                background-color: rgba(59, 130, 246, 0.06);
            }
            .menu-item.danger {
                color: #dc3545;
            }
            .menu-item.danger:hover {
                background-color: #f8d7da;
            }
            .menu-item i {
                width: 20px;
                margin-right: 12px;
                font-size: 14px;
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
    
    // Group settings menu function
    function showGroupSettingsMenu(groupId, groupName) {
        // Remove any existing settings menu
        const existingMenu = document.querySelector('.group-settings-menu');
        if (existingMenu) {
            existingMenu.remove();
        }
        
        const menu = document.createElement('div');
        menu.className = 'group-settings-menu';
        menu.style.cssText = `
            position: fixed;
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            min-width: 180px;
            padding: 8px 0;
        `;
        
        const menuItems = `
            <div class="menu-item" onclick="pinGroupChat(${groupId})">
                <i class="fas fa-thumbtack"></i>
                <span>Pin Group</span>
            </div>
            <div class="menu-item" onclick="viewGroupInfoFromSidebar(${groupId})">
                <i class="fas fa-info-circle"></i>
                <span>View Group Info</span>
            </div>
            <div class="menu-divider"></div>
            <div class="menu-item danger" onclick="leaveGroupFromSidebar(${groupId}, '${groupName}')">
                <i class="fas fa-sign-out-alt"></i>
                <span>Leave Group</span>
            </div>
        `;
        
        menu.innerHTML = menuItems;
        
        // Position menu near the clicked button
        const button = event.target.closest('.group-settings-btn');
        const rect = button.getBoundingClientRect();
        menu.style.left = (rect.left - 150) + 'px';
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
                background-color: rgba(59, 130, 246, 0.06);
            }
            .menu-item.danger {
                color: #dc3545;
            }
            .menu-item.danger:hover {
                background-color: #f8d7da;
            }
            .menu-item i {
                width: 20px;
                margin-right: 12px;
                font-size: 14px;
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
    
    // Chat settings functions
    window.pinChat = function(chatId, chatType) {
        fetch('ajax/chat_handler.php?action=pin_chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                chat_id: chatId,
                chat_type: chatType
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Chat pinned!');
                location.reload(); // Refresh to show pinned chats at top
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error pinning chat:', error);
            alert('Failed to pin, please try again');
        });
    };
    
    window.viewUserProfile = function(userId) {
        fetch('ajax/chat_handler.php?action=get_user_profile&user_id=' + userId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showUserProfileModal(data.user);
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error loading user profile:', error);
            alert('Failed to load profile');
        });
    };
    
    window.deleteFriend = function(friendId, friendName) {
        if (confirm(`Are you sure you want to delete friend "${friendName}" "? This action cannot be undone.`)) {
            fetch('ajax/friend_handler.php?action=delete_friend', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    friend_id: friendId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Friend deleted');
                    location.reload(); // Refresh to update friend list
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error deleting friend:', error);
                alert('Failed to delete friend, please try again');
            });
        }
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
    
    window.leaveGroup = function(groupId, groupName) {
        if (confirm(`Are you sure you want to leave group "${groupName}"?`)) {
            fetch('ajax/group_handler.php?action=leave_group', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    group_id: groupId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Left the group');
                    location.reload(); // Refresh to update group list
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error leaving group:', error);
                alert('Failed to leave group, please try again');
            });
        }
    };
    
    // Group settings functions for sidebar
    window.pinGroupChat = function(groupId) {
        fetch('ajax/chat_handler.php?action=pin_chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                chat_id: groupId,
                chat_type: 'group'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Group pinned!');
                
                // Check if currently on group.php
                if (window.location.pathname.includes('group.php')) {
                    // On group page, update sidebar only without full refresh
                    updateSidebarGroupOrder();
                } else {
                    // Otherwise refresh normally
                    location.reload();
                }
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error pinning group:', error);
            alert('Failed to pin, please try again');
        });
    };
    
    // Update sidebar group order without page refresh
    function updateSidebarGroupOrder() {
        // Re-fetch group list and update display
        fetch('ajax/group_handler.php?action=get_user_groups')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.groups) {
                updateGroupsList(data.groups);
            }
        })
        .catch(error => {
            console.error('Error updating groups list:', error);
        });
    }
    
    // Update group list display
    function updateGroupsList(groups) {
        const groupsList = document.querySelector('.groups-list');
        if (!groupsList) return;
        
        // Clear existing list
        groupsList.innerHTML = '';
        
        if (groups.length === 0) {
            groupsList.innerHTML = '<div class="no-groups">No groups yet</div>';
            return;
        }
        
        // Regenerate group list
        groups.forEach(group => {
            const groupItem = document.createElement('div');
            groupItem.className = 'group-item';
            groupItem.setAttribute('data-group-id', group.id);
            groupItem.setAttribute('data-group-name', group.name);
            
            groupItem.innerHTML = `
                <div class="group-avatar">
                    ${group.avatar && group.avatar !== 'default_group_avatar.png' ? 
                        `<img src="${group.avatar}?t=${Date.now()}" alt="Group Avatar" class="avatar-circle" style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%;">` :
                        `<div class="avatar-circle pink">${group.name.charAt(0).toUpperCase()}</div>`
                    }
                </div>
                <div class="group-details">
                    <div class="group-name">${group.name}</div>
                    <div class="group-members">${group.role === 'admin' ? 'Admin' : 'Member'}</div>
                </div>
                <div class="group-actions">
                    <button class="group-settings-btn" data-group-id="${group.id}" data-group-name="${group.name}" title="Settings">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
            `;
            
            groupsList.appendChild(groupItem);
        });
        
        // Re-bind event listeners
        bindGroupEvents();
    }
    
    // Bind group event listeners
    function bindGroupEvents() {
        // Group select event - only on non-chat pages
        if (!window.location.pathname.includes('user_chatting.php')) {
            document.querySelectorAll('.group-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    if (e.target.closest('.group-settings-btn')) {
                        return;
                    }
                    
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const groupId = this.dataset.groupId;
                    const groupName = this.dataset.groupName;
                    
                    window.location.href = `user_chatting.php?group_id=${encodeURIComponent(groupId)}`;
                });
            });
        }
        
        // Group settings button event
        document.querySelectorAll('.group-settings-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const groupId = this.dataset.groupId;
                const groupName = this.dataset.groupName;
                showGroupSettingsMenu(groupId, groupName);
            });
        });
    }
    
    window.viewGroupInfoFromSidebar = function(groupId) {
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
    
    window.leaveGroupFromSidebar = function(groupId, groupName) {
        if (confirm(`Are you sure you want to leave group "${groupName}"?`)) {
            fetch('ajax/group_handler.php?action=leave_group', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    group_id: groupId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Left the group');
                    location.reload(); // Refresh to update group list
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error leaving group:', error);
                alert('Failed to leave group, please try again');
            });
        }
    };
    
    // User profile modal. The friendship state is returned by get_user_profile.
    function showUserProfileModal(user) {
        const escapeHtml = function (value) {
            return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
            });
        };

        const oldModal = document.getElementById('userProfileModal');
        if (oldModal) oldModal.remove();

        const username = escapeHtml(user.username || '');
        const fullName = escapeHtml(user.full_name || user.username || 'Người dùng');
        const email = escapeHtml(user.email || 'Chưa cập nhật');
        const statusText = user.status === 'online' ? 'Đang hoạt động' : 'Ngoại tuyến';
        const friendshipStatus = user.friendship_status || 'none';
        const initial = escapeHtml((user.full_name || user.username || '?').charAt(0).toUpperCase());
        const avatarName = String(user.avatar || '');
        const avatarMarkup = avatarName && avatarName !== 'default_avatar.png'
            ? `<img src="uploads/avatars/${encodeURIComponent(avatarName)}" alt="Ảnh đại diện" class="profile-modal-avatar-image">`
            : `<div class="profile-modal-avatar-fallback">${initial}</div>`;

        let friendActionMarkup = '';
        if (friendshipStatus === 'accepted') {
            friendActionMarkup = '<div class="profile-friend-note is-friend"><i class="fas fa-user-check"></i> Các bạn đã là bạn bè</div>';
        } else if (friendshipStatus === 'pending_sent') {
            friendActionMarkup = '<div class="profile-friend-note"><i class="fas fa-clock"></i> Đã gửi lời mời kết bạn</div>';
        } else if (friendshipStatus === 'pending_received') {
            friendActionMarkup = '<button type="button" class="btn btn-primary" id="profileAcceptFriendBtn"><i class="fas fa-user-check"></i> Chấp nhận lời mời</button>';
        } else if (friendshipStatus === 'self') {
            friendActionMarkup = '<div class="profile-friend-note">Đây là hồ sơ của bạn</div>';
        } else if (friendshipStatus === 'blocked') {
            friendActionMarkup = '<div class="profile-friend-note">Không thể gửi lời mời kết bạn cho tài khoản này.</div>';
        } else {
            friendActionMarkup = '<button type="button" class="btn btn-primary" id="profileAddFriendBtn"><i class="fas fa-user-plus"></i> Kết bạn</button>';
        }

        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'userProfileModal';
        modal.setAttribute('tabindex', '-1');
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content profile-modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Trang cá nhân</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row align-items-center g-4">
                            <div class="col-md-4 text-center">
                                <div class="profile-modal-avatar mb-3">${avatarMarkup}</div>
                                <h4 class="mb-1">${fullName}</h4>
                                <p class="text-muted mb-2">@${username}</p>
                                <span class="badge ${user.status === 'online' ? 'bg-success' : 'bg-secondary'}">${statusText}</span>
                                <div class="profile-friend-action mt-3" id="profileFriendAction">${friendActionMarkup}</div>
                            </div>
                            <div class="col-md-8">
                                <h6 class="fw-bold mb-3">Thông tin cơ bản</h6>
                                <table class="table table-borderless mb-0">
                                    <tr><td><strong>Tên đăng nhập</strong></td><td>${username}</td></tr>
                                    <tr><td><strong>Họ tên</strong></td><td>${fullName}</td></tr>
                                    <tr><td><strong>Email</strong></td><td>${email}</td></tr>
                                    <tr><td><strong>Trạng thái</strong></td><td>${statusText}</td></tr>
                                    <tr><td><strong>Đăng ký</strong></td><td>${user.created_at ? escapeHtml(new Date(user.created_at).toLocaleDateString('vi-VN')) : 'Không rõ'}</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(modal);

        const friendAction = modal.querySelector('#profileFriendAction');
        const addFriendButton = modal.querySelector('#profileAddFriendBtn');
        if (addFriendButton) {
            addFriendButton.addEventListener('click', async function () {
                addFriendButton.disabled = true;
                addFriendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';
                try {
                    const response = await fetch('ajax/friend_handler.php?action=send_request', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ username: user.username })
                    });
                    const result = await response.json();
                    if (!result.success) throw new Error(result.error || 'Không gửi được lời mời kết bạn.');
                    friendAction.innerHTML = '<div class="profile-friend-note"><i class="fas fa-clock"></i> Đã gửi lời mời kết bạn</div>';
                } catch (error) {
                    addFriendButton.disabled = false;
                    addFriendButton.innerHTML = '<i class="fas fa-user-plus"></i> Kết bạn';
                    alert(error.message || 'Không gửi được lời mời kết bạn.');
                }
            });
        }

        const acceptFriendButton = modal.querySelector('#profileAcceptFriendBtn');
        if (acceptFriendButton) {
            acceptFriendButton.addEventListener('click', async function () {
                acceptFriendButton.disabled = true;
                try {
                    const response = await fetch('ajax/friend_handler.php?action=accept_request', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ friend_id: Number(user.id) })
                    });
                    const result = await response.json();
                    if (!result.success) throw new Error(result.error || 'Không thể chấp nhận lời mời.');
                    friendAction.innerHTML = '<div class="profile-friend-note is-friend"><i class="fas fa-user-check"></i> Các bạn đã là bạn bè</div>';
                } catch (error) {
                    acceptFriendButton.disabled = false;
                    alert(error.message || 'Không thể chấp nhận lời mời.');
                }
            });
        }

        // Bootstrap is loaded on chat pages; feed pages also load the bundle.
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const bootstrapModal = new bootstrap.Modal(modal);
            bootstrapModal.show();
            modal.addEventListener('hidden.bs.modal', function () { modal.remove(); }, { once: true });
        } else {
            // Fallback for a temporarily unavailable Bootstrap CDN.
            modal.classList.add('show');
            modal.style.display = 'block';
            modal.style.background = 'rgba(0,0,0,.45)';
            document.body.classList.add('modal-open');
            const close = function () {
                modal.remove();
                document.body.classList.remove('modal-open');
            };
            modal.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (button) {
                button.addEventListener('click', close);
            });
            modal.addEventListener('click', function (event) {
                if (event.target === modal) close();
            });
        }
    }

    // Group info modal
    function showGroupInfoModal(group) {
        // Check if Bootstrap is loaded
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
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 120px; height: 120px; font-size: 48px; margin: 0 auto;">
                                        ${group.name.charAt(0).toUpperCase()}
                                    </div>
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
    
    // Group menu functionality
    document.addEventListener('click', function(e) {
        if (e.target.closest('#groupMenuBtn')) {
            e.stopPropagation();
            const groupId = window.currentChatId;
            const titleEl = document.querySelector('#chatTitle h5') || document.querySelector('.group-header-details h5');
            const groupName = (titleEl && titleEl.textContent) ? titleEl.textContent.replace(/^[^\s]+\s/, '').trim() : 'Group';
            console.log('Group menu clicked, groupId:', groupId, 'groupName:', groupName); // Debug log
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
        
        const safeGroupName = (typeof groupName === 'string' ? groupName : 'Group').replace(/'/g, "\\'").replace(/\\/g, '\\\\');
        const menuItems = `
            <div class="menu-item" onclick="viewAllGroupMessages(${groupId})">
                <i class="fas fa-history"></i>
                <span>View All Messages</span>
            </div>
            <div class="menu-item" onclick="inviteFriendsToGroup(${groupId}, '${safeGroupName}')">
                <i class="fas fa-user-plus"></i>
                <span>Invite Friends</span>
            </div>
            <div class="menu-divider"></div>
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
        
        // Position menu near the clicked button
        const button = document.getElementById('groupMenuBtn');
        if (button) {
            const rect = button.getBoundingClientRect();
            menu.style.left = (rect.right - 200) + 'px';
            menu.style.top = (rect.bottom + 5) + 'px';
        } else {
            menu.style.left = '50%';
            menu.style.top = '50%';
            menu.style.transform = 'translate(-50%, -50%)';
        }
        
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
                background-color: rgba(59, 130, 246, 0.06);
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
    
    // Group menu functions
    window.viewAllGroupMessages = function(groupId) {
        showAllGroupMessagesModal(groupId);
    };
    
    window.inviteFriendsToGroup = function(groupId, groupName) {
        showInviteFriendsModal(groupId, groupName);
    };
    
    window.viewGroupMembers = function(groupId) {
        showGroupMembersModal(groupId);
    };
    
    // Show all group messages modal
    function showAllGroupMessagesModal(groupId) {
        // Check if Bootstrap is loaded
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
            if (!container) return;
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
            const el = document.getElementById('allMessagesContainer');
            if (el) el.innerHTML = '<div class="text-center text-danger">Failed to load messages</div>';
        });
    }
    
    // Create message element for display
    function createMessageElement(message) {
        const div = document.createElement('div');
        div.className = `message ${message.sender_id == window.currentUserId ? 'sent' : 'received'}`;
        div.style.cssText = `
            display: flex;
            margin-bottom: 15px;
            align-items: flex-end;
        `;
        
        const time = new Date(message.created_at).toLocaleString();
        const initial = (message.sender_username && typeof message.sender_username === 'string')
            ? message.sender_username.charAt(0).toUpperCase() : '?';
        
        div.innerHTML = `
            <div class="message-avatar">
                ${message.sender_avatar && message.sender_avatar !== 'default_avatar.png' ? 
                    `<img src="uploads/avatars/${message.sender_avatar}" class="message-avatar-image">` :
                    initial
                }
            </div>
            <div class="message-bubble">
                <div class="message-content">${message.content || ''}</div>
                <div class="message-meta">
                    <span class="message-sender">${message.sender_username || ''}</span>
                    <span class="message-time">${time}</span>
                </div>
            </div>
        `;
        
        return div;
    }
    
    // Show invite friends modal
    function showInviteFriendsModal(groupId, groupName) {
        // Check if Bootstrap is loaded
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
    
    // Search friends for invite
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
    
    // Invite friend to group
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
        console.log('showGroupMembersModal called with groupId:', groupId); // Debug log
        
        // Check if Bootstrap is loaded
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
        console.log('loadGroupMembers called with groupId:', groupId); // Debug log
        
        fetch(`ajax/group_handler.php?action=get_group_members&group_id=${groupId}`)
        .then(response => {
            console.log('Response status:', response.status); // Debug log
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data); // Debug log
            const container = document.getElementById('membersList');
            if (!container) return;
            if (data.success && data.members) {
                container.innerHTML = '';
                data.members.forEach(member => {
                    const initial = (member.username && typeof member.username === 'string') ? member.username.charAt(0).toUpperCase() : '?';
                    const memberDiv = document.createElement('div');
                    memberDiv.className = 'member-item d-flex align-items-center p-3 border-bottom';
                    memberDiv.innerHTML = `
                        <div class="member-avatar me-3">
                            <div class="avatar-circle ${member.status === 'online' ? 'green' : 'purple'}">
                                ${member.avatar && member.avatar !== 'default_avatar.png' ? 
                                    `<img src="uploads/avatars/${member.avatar}" class="member-avatar-image" alt="${member.username || ''}">` :
                                    initial
                                }
                            </div>
                        </div>
                        <div class="member-info flex-grow-1">
                            <div class="member-name">${member.full_name || member.username || ''}</div>
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
            const el = document.getElementById('membersList');
            if (el) el.innerHTML = '<div class="text-center text-danger">Failed to load members</div>';
        });
    }

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('show');
            const mobileToggle = document.querySelector('.mobile-sidebar-toggle');
            if (mobileToggle) {
                mobileToggle.remove();
            }
        } else {
            // Add mobile toggle if it doesn't exist
            if (!document.querySelector('.mobile-sidebar-toggle')) {
                const mobileToggleBtn = document.createElement('button');
                mobileToggleBtn.className = 'mobile-sidebar-toggle';
                mobileToggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
                mobileToggleBtn.style.cssText = `
                    position: fixed;
                    top: 20px;
                    left: 20px;
                    z-index: 1001;
                    background: var(--sidebar-active);
                    color: white;
                    border: none;
                    border-radius: 8px;
                    padding: 12px;
                    font-size: 1.2rem;
                    cursor: pointer;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                `;
                mobileToggleBtn.addEventListener('click', toggleMobileSidebar);
                document.body.appendChild(mobileToggleBtn);
            }
        }
    });
});
</script>

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

// Get user's current avatar
$stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_avatar = $stmt->fetch()['avatar'] ?? 'default_avatar.png';

// Handle avatar upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_avatar'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/avatars/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $file_size = $_FILES['avatar']['size'];
            
            if ($file_size <= 5 * 1024 * 1024) { // 5MB limit
                $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                    try {
                        // Update database
                        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                        if ($stmt->execute([$new_filename, $user_id])) {
                            $_SESSION['avatar'] = $new_filename;
                            $success = "Avatar updated successfully!";
                        } else {
                            $errors[] = "Failed to update avatar in database";
                        }
                    } catch (Exception $e) {
                        $errors[] = "Database error: " . $e->getMessage();
                    }
                } else {
                    $errors[] = "Failed to upload file";
                }
            } else {
                $errors[] = "File size must be less than 5MB";
            }
        } else {
            $errors[] = "Invalid file type. Only JPG, PNG, GIF, and WebP are allowed";
        }
    } else {
        $errors[] = "No file uploaded or upload error occurred";
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $new_full_name = trim($_POST['full_name']);
    $new_language = in_array($_POST['language'] ?? '', ['en', 'vi'], true) ? $_POST['language'] : 'en';
    
    $errors = [];
    
    // Validation
    if (empty($new_full_name)) {
        $errors[] = "Full name cannot be empty";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, language = ? WHERE id = ?");
            if ($stmt->execute([$new_full_name, $new_language, $user_id])) {
                $_SESSION['full_name'] = $new_full_name;
                $lang->setLanguage($new_language);
                $success = "Profile updated successfully!";
            } else {
                $errors[] = "Failed to update profile";
            }
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $errors[] = "All password fields are required";
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match";
    }
    
    if (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters long";
    }
    
    if (empty($errors)) {
        try {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($current_password, $user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt->execute([$hashed_password, $user_id])) {
                    $success = "Password changed successfully!";
                } else {
                    $errors[] = "Failed to change password";
                }
            } else {
                $errors[] = "Current password is incorrect";
            }
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Get user statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as friend_count FROM friends WHERE user_id = ? AND status = 'accepted'");
$stmt->execute([$user_id]);
$friend_count = $stmt->fetch()['friend_count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as group_count FROM group_members WHERE user_id = ?");
$stmt->execute([$user_id]);
$group_count = $stmt->fetch()['group_count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as message_count FROM messages WHERE sender_id = ?");
$stmt->execute([$user_id]);
$message_count = $stmt->fetch()['message_count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as post_count FROM social_posts WHERE user_id = ?");
$stmt->execute([$user_id]);
$post_count = $stmt->fetch()['post_count'];
?>

<!DOCTYPE html>
<html lang="<?php echo $lang->getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Chat System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/theme.css" rel="stylesheet">
    <style>
        .main-content {
            padding: 2rem;
            min-height: calc(100vh - 4rem);
            position: relative;
            z-index: 1;
        }
        .profile-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .profile-header {
            background: var(--gradient-primary);
            color: white;
            padding: 2rem;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        .profile-content {
            background: var(--surface);
            backdrop-filter: blur(12px);
            padding: 2rem;
            border-radius: 0 0 15px 15px;
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-md);
        }
        .stats-card {
            background: var(--surface-solid);
            border-radius: var(--radius-sm);
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-sm);
            text-align: center;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--accent);
        }
        .nav-pills .nav-link.active {
            background: var(--gradient-primary);
        }
        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            border-radius: var(--radius-sm);
            padding: 12px 24px;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.35);
        }
        .language-selector {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .avatar-section {
            text-align: center;
            margin-bottom: 2rem;
        }
        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 3rem;
            color: white;
            position: relative;
            cursor: pointer;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.35);
        }
        
        .avatar-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .avatar-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 50%;
            font-size: 0.9rem;
        }
        
        .avatar-overlay i {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        
        .avatar:hover .avatar-overlay {
            opacity: 1;
        }
        
        .avatar-overlay span {
            font-size: 0.8rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="profile-container">
        <div class="profile-header">
            <div class="avatar-section">
                <div class="avatar" id="avatarContainer" onclick="document.getElementById('avatarInput').click()">
                    <?php if ($user_avatar && $user_avatar !== 'default_avatar.png' && file_exists('uploads/avatars/' . $user_avatar)): ?>
                        <img src="uploads/avatars/<?php echo htmlspecialchars($user_avatar); ?>?t=<?php echo time(); ?>" alt="Avatar" class="avatar-image">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                    <div class="avatar-overlay">
                        <i class="fas fa-camera"></i>
                        <span>Click to change avatar</span>
                    </div>
                </div>
                <h3><?php echo htmlspecialchars($full_name); ?></h3>
                <p class="mb-0">@<?php echo htmlspecialchars($username); ?></p>
                
                <!-- Hidden file input -->
                <form id="avatarForm" method="POST" enctype="multipart/form-data" style="display: none;">
                    <input type="file" id="avatarInput" name="avatar" accept="image/*" onchange="uploadAvatar()">
                    <input type="hidden" name="upload_avatar" value="1">
                </form>
            </div>
        </div>

        <div class="profile-content">
            <!-- Navigation -->
            <ul class="nav nav-pills mb-4" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="overview-tab" data-bs-toggle="pill" data-bs-target="#overview" type="button">
                        <i class="fas fa-chart-bar"></i> Overview
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="edit-profile-tab" data-bs-toggle="pill" data-bs-target="#edit-profile" type="button">
                        <i class="fas fa-edit"></i> Edit Profile
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="change-password-tab" data-bs-toggle="pill" data-bs-target="#change-password" type="button">
                        <i class="fas fa-lock"></i> Change Password
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="profileTabContent">
                <!-- Overview Tab -->
                <div class="tab-pane fade show active" id="overview" role="tabpanel">
                    <h4><i class="fas fa-chart-bar"></i> Account Statistics</h4>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number"><?php echo $friend_count; ?></div>
                                <div class="text-muted"><?php echo $lang->get('friends'); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number"><?php echo $group_count; ?></div>
                                <div class="text-muted"><?php echo $lang->get('groups'); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number"><?php echo $message_count; ?></div>
                                <div class="text-muted">Messages Sent</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number"><?php echo $post_count; ?></div>
                                <div class="text-muted">Bài đăng</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h5><i class="fas fa-info-circle"></i> Account Information</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Username:</strong> <?php echo htmlspecialchars($username); ?></p>
                                <p><strong>Full Name:</strong> <?php echo htmlspecialchars($full_name); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Language:</strong> <?php echo ucfirst($lang->getCurrentLanguage()); ?></p>
                                <p><strong>Status:</strong> <span class="badge bg-success">Online</span></p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <a href="user_chatting.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Chat
                        </a>
                        <a href="social_feed.php" class="btn btn-outline-primary">
                            <i class="fas fa-forum"></i> Go to Feed
                        </a>
                    </div>
                </div>

                <!-- Edit Profile Tab -->
                <div class="tab-pane fade" id="edit-profile" role="tabpanel">
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($errors) && !empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <h4><i class="fas fa-edit"></i> Edit Profile</h4>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($username); ?>" disabled>
                            <div class="form-text">Username cannot be changed</div>
                        </div>
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="language" class="form-label">Language</label>
                            <select class="form-select" id="language" name="language">
                                <option value="en" <?php echo $lang->getCurrentLanguage() == 'en' ? 'selected' : ''; ?>><?php echo $lang->get('english'); ?></option>
                                <option value="vi" <?php echo $lang->getCurrentLanguage() == 'vi' ? 'selected' : ''; ?>><?php echo $lang->get('vietnamese'); ?></option>
                            </select>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </div>

                <!-- Change Password Tab -->
                <div class="tab-pane fade" id="change-password" role="tabpanel">
                    <?php if (isset($success) && isset($_POST['change_password'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($errors) && isset($_POST['change_password']) && !empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <h4><i class="fas fa-lock"></i> Change Password</h4>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function uploadAvatar() {
            const fileInput = document.getElementById('avatarInput');
            const form = document.getElementById('avatarForm');
            
            if (fileInput.files && fileInput.files[0]) {
                const file = fileInput.files[0];
                
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPG, PNG, GIF, WebP)');
                    return;
                }
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size cannot exceed 5MB');
                    return;
                }
                
                // Show loading state
                const avatarContainer = document.getElementById('avatarContainer');
                avatarContainer.style.opacity = '0.7';
                avatarContainer.style.pointerEvents = 'none';
                
                // Submit form
                form.submit();
            }
        }
        
        // Show success/error messages
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($success)): ?>
                // Show success message
                const successAlert = document.createElement('div');
                successAlert.className = 'alert alert-success alert-dismissible fade show';
                successAlert.style.position = 'fixed';
                successAlert.style.top = '20px';
                successAlert.style.right = '20px';
                successAlert.style.zIndex = '9999';
                successAlert.innerHTML = `
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.body.appendChild(successAlert);
                
                // Auto remove after 3 seconds
                setTimeout(() => {
                    if (successAlert.parentNode) {
                        successAlert.parentNode.removeChild(successAlert);
                    }
                }, 3000);
            <?php endif; ?>
            
            <?php if (isset($errors) && !empty($errors)): ?>
                // Show error message
                const errorAlert = document.createElement('div');
                errorAlert.className = 'alert alert-danger alert-dismissible fade show';
                errorAlert.style.position = 'fixed';
                errorAlert.style.top = '20px';
                errorAlert.style.right = '20px';
                errorAlert.style.zIndex = '9999';
                errorAlert.innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.body.appendChild(errorAlert);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (errorAlert.parentNode) {
                        errorAlert.parentNode.removeChild(errorAlert);
                    }
                }, 5000);
            <?php endif; ?>
        });
    </script>
    <?php include_once 'includes/copyright_footer.php'; ?>
</body>
</html>

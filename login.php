<?php
require_once 'config/database.php';
require_once 'includes/language.php';

// Handle login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
            
            // Update user status to online
            $stmt = $pdo->prepare("UPDATE users SET status = 'online', last_seen = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            header('Location: user_chatting.php');
            exit;
        } else {
            $error = "Invalid username or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang->getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang->get('login'); ?> - Chat System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body class="page-auth">
    <div class="main-content w-100">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <div class="auth-card">
                        <div class="auth-card-header">
                            <h2><i class="fas fa-comments"></i> Chat System</h2>
                            <p><?php echo $lang->get('login'); ?></p>
                        </div>
                        <div class="auth-card-body">
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="username" class="form-label">
                                        <i class="fas fa-user"></i> <?php echo $lang->get('username'); ?>
                                    </label>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? 'admin'); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock"></i> <?php echo $lang->get('password'); ?>
                                    </label>
                                    <input type="password" class="form-control" id="password" name="password" value="<?php echo htmlspecialchars($_POST['password'] ?? 'admin123'); ?>" required>
                                </div>
                                <button type="submit" name="login" class="btn btn-primary btn-theme w-100">
                                    <i class="fas fa-sign-in-alt"></i> <?php echo $lang->get('login_button'); ?>
                                </button>
                            </form>
                            
                            <div class="text-center mt-3">
                                <p><?php echo $lang->get('register'); ?>? <a href="register.php"><?php echo $lang->get('register'); ?></a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php include_once 'includes/copyright_footer.php'; ?>
</body>
</html>

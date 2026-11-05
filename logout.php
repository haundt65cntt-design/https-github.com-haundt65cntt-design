<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    try {
        // Update user status to offline and set last_seen timestamp
        $stmt = $pdo->prepare("UPDATE users SET status = 'offline', last_seen = NOW() WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Log the logout activity (optional)
        error_log("User " . $_SESSION['username'] . " (ID: " . $user_id . ") logged out at " . date('Y-m-d H:i:s'));
        
    } catch (Exception $e) {
        // Log the error but don't prevent logout
        error_log("Error updating user status during logout: " . $e->getMessage());
    }
}

// Clear all session data
$_SESSION = array();

// Destroy the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;
?>

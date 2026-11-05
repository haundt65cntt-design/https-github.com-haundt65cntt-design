<?php
// Path configuration
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__ . '/..');
}

// Database configuration
require_once __DIR__ . '/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

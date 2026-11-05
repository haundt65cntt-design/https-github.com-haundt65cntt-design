<?php
/**
 * Central PDO connection for Advanced Chat System.
 * Database structure is managed only by the individual SQL files in database/tables/.
 * This file deliberately does not create, alter, or recreate application tables.
 */

$host     = 'localhost';
$dbname   = 'chatting_system';
$username = 'root';
$password = '';

$localConfig = __DIR__ . '/database.local.php';
if (file_exists($localConfig)) {
    $local = include $localConfig;
    if (is_array($local)) {
        $host     = isset($local['host'])     ? $local['host']     : $host;
        $dbname   = isset($local['dbname'])   ? $local['dbname']   : $dbname;
        $username = isset($local['username']) ? $local['username'] : $username;
        $password = isset($local['password']) ? $local['password'] : $password;
    }
}

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        )
    );
    $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
} catch (PDOException $e) {
    error_log('[database] ' . $e->getMessage());
    http_response_code(500);
    die('Không thể kết nối cơ sở dữ liệu. Hãy kiểm tra MySQL trong XAMPP và cấu hình config/database.local.php.');
}
?>

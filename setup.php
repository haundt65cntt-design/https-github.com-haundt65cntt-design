<?php
/**
 * Database setup script - create chatting_system database and import schema
 * Run once via localhost/chatting_system/setup.php
 * Delete or rename this file after installation for security
 */

$host = 'localhost';
$username = 'root';
$password = '';

// Connect to MySQL without specifying database
$mysqli = new mysqli($host, $username, $password);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$mysqli->set_charset("utf8mb4");

$mysqli->query("CREATE DATABASE IF NOT EXISTS chatting_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$mysqli->select_db('chatting_system');

// Read and execute schema.sql
$schemaPath = __DIR__ . '/database/schema.sql';
if (!file_exists($schemaPath)) {
    die("Error: database/schema.sql file not found");
}

$sql = file_get_contents($schemaPath);

// Execute multiple SQL statements
if ($mysqli->multi_query($sql)) {
    do {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
    } while ($mysqli->next_result());
}

if ($mysqli->error) {
    die("SQL execution error: " . $mysqli->error);
}

$mysqli->close();

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Installation Complete</title>";
echo "<link href='assets/css/theme.css' rel='stylesheet'>";
echo "<style>.setup-wrap{max-width:480px;margin:4rem auto;padding:2rem;text-align:center;position:relative;z-index:1}.setup-card{background:var(--surface);backdrop-filter:blur(14px);border:1px solid var(--border-light);border-radius:var(--radius);padding:2rem;box-shadow:var(--shadow-lg)}.setup-card h2{color:var(--accent);margin-bottom:1rem}.setup-card a{color:var(--accent);font-weight:600;text-decoration:none}</style>";
echo "</head><body class='page-auth'><div class='setup-wrap'><div class='setup-card'>";
echo "<h2>✓ Database installed successfully</h2>";
echo "<p>Database <strong>chatting_system</strong> and all tables have been created.</p>";
echo "<p><a href='login.php'>Go to Login Page</a></p>";
echo "</div></div>";
include_once __DIR__ . '/includes/copyright_footer.php';
echo "</body></html>";
?>

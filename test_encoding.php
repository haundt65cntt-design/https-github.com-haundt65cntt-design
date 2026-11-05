<?php
// Encoding test script
require_once 'config/paths.php';

echo "<!DOCTYPE html>";
echo "<html lang='en'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>Encoding Test</title>";
echo "</head>";
echo "<body>";
echo "<h1>UTF-8 Encoding Test</h1>";

// Test character display
echo "<h2>Chinese character test:</h2>";
echo "<p>Forum Settings - Only the forum creator can modify forum settings</p>";
echo "<p>Failed to get forum info</p>";
echo "<p>Forum name cannot be empty</p>";
echo "<p>Forum name already exists</p>";
echo "<p>Forum info updated successfully!</p>";

// Test database connection encoding
echo "<h2>Database encoding test:</h2>";
try {
    $stmt = $pdo->query("SELECT @@character_set_connection, @@collation_connection");
    $result = $stmt->fetch();
    echo "<p>Database connection charset: " . $result['@@character_set_connection'] . "</p>";
    echo "<p>Database connection collation: " . $result['@@collation_connection'] . "</p>";
} catch (Exception $e) {
    echo "<p>Database connection test failed: " . $e->getMessage() . "</p>";
}

// Test PHP internal encoding
echo "<h2>PHP encoding test:</h2>";
echo "<p>PHP internal encoding: " . mb_internal_encoding() . "</p>";
echo "<p>Default charset: " . ini_get('default_charset') . "</p>";

// Test JSON encoding
echo "<h2>JSON encoding test:</h2>";
$test_data = [
    'message' => 'Test Chinese message',
    'error' => 'Only the forum creator can modify forum settings',
    'success' => 'Forum info updated successfully!'
];
echo "<p>JSON encoding result: " . json_encode($test_data, JSON_UNESCAPED_UNICODE) . "</p>";

include_once __DIR__ . '/includes/copyright_footer.php';
echo "</body>";
echo "</html>";
?>

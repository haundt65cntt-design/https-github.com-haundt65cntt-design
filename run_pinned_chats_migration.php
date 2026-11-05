<?php
/**
 * One-time migration: create pinned_chats table
 * Visit once in browser. Delete this file after success.
 */
require_once __DIR__ . '/config/database.php';

$sqlPath = __DIR__ . '/database/pinned_chats_table.sql';
if (!file_exists($sqlPath)) {
    die("Error: database/pinned_chats_table.sql file not found");
}

$sql = file_get_contents($sqlPath);

try {
    $pdo->exec($sql);
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage());
}

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Migration Complete</title></head><body>";
echo "<h2>✓ pinned_chats table created</h2>";
echo "<p><a href='user_chatting.php'>Back to chat</a></p>";
include_once __DIR__ . '/includes/copyright_footer.php';
echo "</body></html>";

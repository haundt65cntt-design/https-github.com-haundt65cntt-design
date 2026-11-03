<?php
// Multi-forum feature has been retired. Social posts are handled by social_feed.php.
header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode([
    'success' => false,
    'error' => 'Forum đã được thay thế bằng Bảng tin.',
    'redirect' => 'social_feed.php'
], JSON_UNESCAPED_UNICODE);

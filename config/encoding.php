<?php
/**
 * Unified encoding configuration
 * Ensure all pages use UTF-8 encoding
 */

// Set PHP internal encoding to UTF-8
mb_internal_encoding('UTF-8');

// Set default timezone
date_default_timezone_set('Asia/Shanghai');

// Set HTTP response headers
if (!headers_sent()) {
    // Set appropriate Content-Type based on request type
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        header('Content-Type: application/json; charset=UTF-8');
    } else {
        header('Content-Type: text/html; charset=UTF-8');
    }
}

// Set error reporting encoding
ini_set('default_charset', 'UTF-8');

// Ensure JSON encoding uses UTF-8
if (function_exists('json_encode')) {
    // Set JSON encoding options for proper Unicode display
    if (!defined('JSON_UNESCAPED_UNICODE')) {
        define('JSON_UNESCAPED_UNICODE', 256);
    }
}
?>

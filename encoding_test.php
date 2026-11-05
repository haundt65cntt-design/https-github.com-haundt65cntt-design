<?php
// Encoding test page
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Encoding Test</title>
</head>
<body>
    <h1>UTF-8 Encoding Test</h1>
    <p>If you can see the following English text correctly, encoding is configured properly:</p>
    <ul>
        <li>Forum Settings</li>
        <li>Only the forum creator can modify forum settings</li>
        <li>Failed to get forum info</li>
        <li>Forum name cannot be empty</li>
        <li>Forum info updated successfully!</li>
    </ul>
    
    <h2>PHP Encoding Info:</h2>
    <p>PHP internal encoding: <?php echo mb_internal_encoding(); ?></p>
    <p>Default charset: <?php echo ini_get('default_charset'); ?></p>
    
    <h2>Test Links:</h2>
    <p><a href="forum_settings.php?id=18">Test forum settings page</a></p>
    <?php include_once 'includes/copyright_footer.php'; ?>
</body>
</html>

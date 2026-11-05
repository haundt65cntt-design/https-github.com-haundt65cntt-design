<?php
/**
 * Site entry: redirect root to login page to avoid directory listing
 */
header('Location: login.php', true, 302);
exit;

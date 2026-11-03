<?php
/**
 * Compatibility route: all group conversations use the same Zalo-style chat UI.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$groupId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0);
if ($groupId <= 0) {
    header('Location: user_chatting.php?tab=groups');
    exit;
}

require_once __DIR__ . '/config/database.php';
try {
    $stmt = $pdo->prepare(
        'SELECT g.id FROM `groups` g '
        . 'INNER JOIN group_members gm ON gm.group_id = g.id '
        . 'WHERE g.id = ? AND gm.user_id = ? LIMIT 1'
    );
    $stmt->execute([$groupId, (int)$_SESSION['user_id']]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        header('Location: user_chatting.php?tab=groups');
        exit;
    }
    header('Location: user_chatting.php?group_id=' . $groupId);
    exit;
} catch (Throwable $e) {
    error_log('[group redirect] ' . $e->getMessage());
    header('Location: user_chatting.php?tab=groups');
    exit;
}

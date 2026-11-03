<?php
/**
 * Fixed bottom-of-page link to the User Manual.
 * Included once per page (via copyright_footer.php).
 */
if (defined('GLOBAL_USER_MANUAL_LINK_RENDERED')) {
    return;
}
define('GLOBAL_USER_MANUAL_LINK_RENDERED', true);

$manual_script = basename($_SERVER['PHP_SELF'] ?? '');
$is_manual_page = ($manual_script === 'user_manual.php');
?>
<style>
    :root {
        --user-manual-bar-space: 44px;
    }

    body {
        padding-bottom: calc(var(--user-manual-bar-space) + 28px);
        box-sizing: border-box;
    }

    /* Reserve space at the bottom for the manual bar without covering the app. */
    .sidebar {
        bottom: var(--user-manual-bar-space) !important;
        height: calc(100vh - var(--user-manual-bar-space)) !important;
        min-height: calc(100vh - var(--user-manual-bar-space)) !important;
    }

    body.chat-page {
        height: 100vh !important;
        padding-top: 0 !important;
        padding-bottom: var(--user-manual-bar-space) !important;
        box-sizing: border-box !important;
        overflow: hidden;
    }

    body.chat-page #sidebar.sidebar {
        top: 0 !important;
        bottom: var(--user-manual-bar-space) !important;
        height: auto !important;
        min-height: 0 !important;
    }

    body.chat-page #sidebar #userDropdownMenu {
        top: 8px !important;
    }

    body.chat-page .chat-container {
        top: 0 !important;
        bottom: var(--user-manual-bar-space) !important;
        height: auto !important;
    }

    body.chat-page .chat-main {
        height: 100% !important;
    }

    .global-user-manual-bar {
        position: fixed;
        top: auto;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        min-height: var(--user-manual-bar-space);
        padding: 6px 16px;
        background: rgba(255, 255, 255, 0.92);
        backdrop-filter: blur(10px);
        border-top: 1px solid rgba(147, 197, 253, 0.45);
        box-shadow: 0 2px 12px rgba(59, 130, 246, 0.08);
    }

    .global-user-manual-bar a {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        color: #fff;
        background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.25);
        transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
    }

    .global-user-manual-bar a:hover {
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(59, 130, 246, 0.35);
        opacity: 0.95;
    }

    .global-user-manual-bar a.is-current {
        background: #1e3a5f;
        pointer-events: none;
    }

    .global-user-manual-bar .manual-hint {
        font-size: 12px;
        color: #64748b;
        margin-right: auto;
    }

    @media (max-width: 576px) {
        .global-user-manual-bar .manual-hint {
            display: none;
        }
    }
</style>
<div class="global-user-manual-bar">
    <span class="manual-hint">Need help? Open the step-by-step guide.</span>
    <?php if ($is_manual_page): ?>
        <a href="user_chatting.php" title="Go back to chat" class="manual-back-to-chat">
            <i class="fas fa-comments"></i>
            <span>Go Back to Chat</span>
        </a>
        <a href="user_manual.php" class="is-current" title="User Manual">
            <i class="fas fa-book-open"></i>
            <span>User Manual</span>
        </a>
    <?php else: ?>
        <a href="user_manual.php" title="Open User Manual">
            <i class="fas fa-book-open"></i>
            <span>User Manual</span>
        </a>
    <?php endif; ?>
</div>

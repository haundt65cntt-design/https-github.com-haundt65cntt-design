<?php
if (defined('GLOBAL_COPYRIGHT_FOOTER_RENDERED')) {
    return;
}
define('GLOBAL_COPYRIGHT_FOOTER_RENDERED', true);

// Top-of-page User Manual link (one click on every page that includes this footer)
include_once __DIR__ . '/user_manual_link.php';
?>
<style>
    :root {
        --copyright-footer-space: 72px;
    }

    body {
        padding-bottom: var(--copyright-footer-space);
    }

    .chat-input {
        margin-bottom: var(--copyright-footer-space);
    }

    .global-copyright-footer {
        position: fixed;
        left: 50%;
        bottom: 48px;
        transform: translateX(-50%);
        z-index: 9999;
        font-size: 12px;
        color: #6c757d;
        text-align: center;
        background: rgba(255, 255, 255, 0.9);
        padding: 4px 10px;
        border-radius: 999px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        pointer-events: none;
        user-select: none;
        white-space: nowrap;
    }
</style>
<div class="global-copyright-footer">Copyright © 2026 Eng Choon Hao. All Rights Reserved.</div>

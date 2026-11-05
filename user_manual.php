<?php
/**
 * User Manual — step-by-step guide for using the Chat System (English).
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/language.php';

$is_logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang->getCurrentLanguage(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Manual - Chat System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/theme.css" rel="stylesheet">
    <style>
        .manual-page {
            position: relative;
            z-index: 1;
            padding: 1.5rem 1rem 3rem;
        }

        .manual-wrap {
            max-width: 860px;
            margin: 0 auto;
        }

        .manual-hero {
            background: var(--surface);
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            padding: 2rem;
            margin-bottom: 1.5rem;
        }

        .manual-hero h1 {
            font-size: 1.85rem;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .manual-hero p {
            color: var(--text-secondary);
            margin-bottom: 0;
        }

        .manual-toc {
            background: var(--surface);
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .manual-toc h2 {
            font-size: 1.1rem;
            margin-bottom: 0.75rem;
        }

        .manual-toc ol {
            margin-bottom: 0;
            padding-left: 1.25rem;
        }

        .manual-toc a {
            color: var(--accent-dark);
            text-decoration: none;
        }

        .manual-toc a:hover {
            text-decoration: underline;
        }

        .manual-section {
            background: var(--surface);
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-sm);
        }

        .manual-section h2 {
            font-size: 1.25rem;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-light);
        }

        .manual-section h3 {
            font-size: 1.05rem;
            margin-top: 1.1rem;
            margin-bottom: 0.5rem;
            color: var(--accent-dark);
        }

        .manual-section ol,
        .manual-section ul {
            padding-left: 1.25rem;
        }

        .manual-section li {
            margin-bottom: 0.4rem;
            color: var(--text-secondary);
        }

        .manual-section p {
            color: var(--text-secondary);
        }

        .manual-step {
            background: var(--accent-bg);
            border-left: 3px solid var(--accent);
            border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
            padding: 0.75rem 1rem;
            margin: 0.75rem 0;
            color: var(--text-primary);
        }

        .manual-actions {
            display: flex !important;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
            position: relative;
            z-index: 20;
        }

        .manual-actions .manual-chat-return {
            display: inline-flex !important;
            align-items: center;
            visibility: visible !important;
            opacity: 1 !important;
            position: relative;
            z-index: 21;
            padding: 0.55rem 1rem;
            font-weight: 600;
        }

        .manual-copyright {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <div class="manual-page">
        <div class="manual-wrap">
            <div class="manual-hero">
                <h1><i class="fas fa-book-open"></i> Chat System — User Manual</h1>
                <p>
                    This guide explains how to use the Chat System step by step.
                    Follow the sections below to register, chat, manage friends and groups, and use the forum.
                </p>
                <div class="manual-actions">
                    <?php if ($is_logged_in): ?>
                        <a href="user_chatting.php" class="btn btn-primary btn-theme btn-sm manual-chat-return">
                            <i class="fas fa-comments"></i> Go Back to Chat
                        </a>
                        <a href="profile.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-user"></i> My Profile
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary btn-theme btn-sm">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                        <a href="register.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-user-plus"></i> Register
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <nav class="manual-toc" aria-label="Table of contents">
                <h2><i class="fas fa-list"></i> Contents</h2>
                <ol>
                    <li><a href="#getting-started">Getting Started</a></li>
                    <li><a href="#login-register">Register and Login</a></li>
                    <li><a href="#main-interface">Main Interface Overview</a></li>
                    <li><a href="#friends">Adding and Managing Friends</a></li>
                    <li><a href="#private-chat">Private Chatting</a></li>
                    <li><a href="#groups">Group Chats</a></li>
                    <li><a href="#forum">Using the Forum</a></li>
                    <li><a href="#profile">Profile and Settings</a></li>
                    <li><a href="#advanced">Advanced Messaging Features</a></li>
                    <li><a href="#calls">Voice and Video Calls</a></li>
                    <li><a href="#tips">Tips and Troubleshooting</a></li>
                    <li><a href="#copyright">Copyright</a></li>
                </ol>
            </nav>

            <section class="manual-section" id="getting-started">
                <h2>1. Getting Started</h2>
                <p>The Chat System is a web application for messaging, friends, groups, and community forums.</p>
                <div class="manual-step">
                    <strong>Quick start:</strong> Open the site → click <strong>User Manual</strong> at the top anytime for help → register or log in → start chatting.
                </div>
                <ol>
                    <li>Open your browser and go to the Chat System URL (for example: <code>http://localhost/chatting_system/</code>).</li>
                    <li>You will be redirected to the login page.</li>
                    <li>If you do not have an account yet, create one on the Register page.</li>
                    <li>After login, you enter the main chat interface.</li>
                </ol>
            </section>

            <section class="manual-section" id="login-register">
                <h2>2. Register and Login</h2>

                <h3>Create an account</h3>
                <ol>
                    <li>On the login page, click the <strong>Register</strong> link.</li>
                    <li>Enter a username (at least 3 characters).</li>
                    <li>Enter a password (at least 6 characters).</li>
                    <li>Confirm your password.</li>
                    <li>Click the register button.</li>
                    <li>When registration succeeds, go back to the login page.</li>
                </ol>

                <h3>Log in</h3>
                <ol>
                    <li>Open <strong>Login</strong>.</li>
                    <li>Enter your username and password.</li>
                    <li>Click the login button.</li>
                    <li>You will be taken to the main chat page.</li>
                </ol>

                <h3>Log out</h3>
                <ol>
                    <li>In the left sidebar, click your avatar to open the menu, then choose <strong>Logout</strong>.</li>
                    <li>Or click the logout icon next to your name.</li>
                </ol>
            </section>

            <section class="manual-section" id="main-interface">
                <h2>3. Main Interface Overview</h2>
                <p>After login, the left sidebar is your main navigation.</p>
                <ul>
                    <li><strong>Chats</strong> — Your private and group conversations (search and open chats here).</li>
                    <li><strong>Friends</strong> — Your friend list and options to add friends.</li>
                    <li><strong>Groups</strong> — Groups you belong to; create or open a group chat.</li>
                    <li><strong>Forums</strong> — Community forum posts and discussions.</li>
                </ul>
                <div class="manual-step">
                    Click a tab at the top of the sidebar to switch between Chats, Friends, Groups, and Forums.
                </div>
                <p>Click your avatar (top of the sidebar) to open:</p>
                <ul>
                    <li>Profile</li>
                    <li>Block User</li>
                    <li>Blacklist</li>
                    <li>Settings</li>
                    <li>Logout</li>
                </ul>
            </section>

            <section class="manual-section" id="friends">
                <h2>4. Adding and Managing Friends</h2>

                <h3>Send a friend request</h3>
                <ol>
                    <li>Open the <strong>Friends</strong> tab in the sidebar.</li>
                    <li>Use the option to add a friend (for example, enter their username).</li>
                    <li>Send the request and wait for them to accept.</li>
                </ol>

                <h3>Accept or decline requests</h3>
                <ol>
                    <li>Check pending friend requests in the sidebar (requests area).</li>
                    <li>Accept to become friends, or decline if you do not want to connect.</li>
                </ol>

                <h3>Start chatting with a friend</h3>
                <ol>
                    <li>Go to the <strong>Chats</strong> or <strong>Friends</strong> tab.</li>
                    <li>Click a friend’s name.</li>
                    <li>The chat window opens on the right — type and send messages.</li>
                </ol>

                <h3>Block / blacklist</h3>
                <ol>
                    <li>Open your avatar menu.</li>
                    <li>Choose <strong>Block User</strong> or <strong>Blacklist</strong> to manage users you do not want to interact with.</li>
                </ol>
            </section>

            <section class="manual-section" id="private-chat">
                <h2>5. Private Chatting</h2>
                <ol>
                    <li>Select a friend from the chat list.</li>
                    <li>Type your message in the input box at the bottom.</li>
                    <li>Press <strong>Enter</strong> or click the send button.</li>
                </ol>
                <p>You can also:</p>
                <ul>
                    <li>Attach files with the paperclip / file button.</li>
                    <li>Record a voice message with the microphone button.</li>
                    <li>Reply, forward, edit, delete, like, or pin messages using the actions on each message.</li>
                </ul>
                <div class="manual-step">
                    <strong>Note:</strong> Edit and delete/recall are usually available only for a short time after sending (about 2 minutes).
                </div>
            </section>

            <section class="manual-section" id="groups">
                <h2>6. Group Chats</h2>
                <ol>
                    <li>Open the <strong>Groups</strong> tab.</li>
                    <li>Click <strong>Create Group</strong> (or the equivalent create option).</li>
                    <li>Enter a group name and description.</li>
                    <li>Invite members from your friends list.</li>
                    <li>Open the group from the list to start messaging everyone in it.</li>
                </ol>
                <p>Pinned chats appear at the top of your list so important conversations are easier to find.</p>
            </section>

            <section class="manual-section" id="forum">
                <h2>7. Using the Forum</h2>
                <ol>
                    <li>Open the <strong>Forums</strong> tab or go to the forum page.</li>
                    <li>Browse existing posts and categories.</li>
                    <li>Create a new post when you want to start a discussion.</li>
                    <li>Open a post to read details and leave comments.</li>
                    <li>Use forum settings (if available for your forum) to manage avatar or forum details.</li>
                </ol>
            </section>

            <section class="manual-section" id="profile">
                <h2>8. Profile and Settings</h2>
                <ol>
                    <li>Click your avatar → <strong>Profile</strong>, or open the profile page directly.</li>
                    <li>Upload or change your avatar (JPG, PNG, GIF, or WebP; size limits apply).</li>
                    <li>Update your full name and preferred language.</li>
                    <li>Change your password when needed.</li>
                    <li>Review account statistics such as friends, groups, messages, and posts.</li>
                </ol>
                <p>Supported interface languages are English and Vietnamese.</p>
            </section>

            <section class="manual-section" id="advanced">
                <h2>9. Advanced Messaging Features</h2>
                <ul>
                    <li><strong>Reply</strong> — Respond to a specific message in the conversation.</li>
                    <li><strong>Forward</strong> — Share a message with another friend or chat.</li>
                    <li><strong>Edit</strong> — Change your own message shortly after sending.</li>
                    <li><strong>Delete / Recall</strong> — Remove a message within the allowed time window.</li>
                    <li><strong>Like / React</strong> — React to messages you receive.</li>
                    <li><strong>Pin</strong> — Pin important messages so they stay visible at the top of the chat.</li>
                    <li><strong>Mentions</strong> — Use @username to mention someone in a message (where supported).</li>
                    <li><strong>Voice messages</strong> — Record and send audio clips.</li>
                    <li><strong>File sharing</strong> — Send images, documents, and other allowed files.</li>
                </ul>
            </section>

            <section class="manual-section" id="calls">
                <h2>10. Voice and Video Calls</h2>
                <ol>
                    <li>Open a private chat with a friend.</li>
                    <li>In the chat header, click the <strong>Voice Call</strong> or <strong>Video Call</strong> button.</li>
                    <li>Allow microphone/camera access in your browser when prompted.</li>
                    <li>End the call when you are finished.</li>
                </ol>
                <div class="manual-step">
                    For best results, use a modern browser (Chrome, Firefox, Edge, or Safari) and grant media permissions. On some servers, HTTPS may be required for microphone access.
                </div>
            </section>

            <section class="manual-section" id="tips">
                <h2>11. Tips and Troubleshooting</h2>
                <ul>
                    <li>If messages do not appear, refresh the page and check your internet connection.</li>
                    <li>If voice recording fails, check browser microphone permissions.</li>
                    <li>If file upload fails, check the file type and size, then try again.</li>
                    <li>Use the search box in the Chats tab to find friends or conversations quickly.</li>
                    <li>On mobile, use the menu/toggle to open the sidebar if it is hidden.</li>
                    <li>Click <strong>User Manual</strong> at the top of any page to return to this guide anytime.</li>
                </ul>
            </section>

            <section class="manual-section" id="copyright">
                <h2>12. Copyright</h2>
                <p>
                    <strong>Copyright © 2026 Eng Choon Hao. All Rights Reserved.</strong>
                </p>
                <p>
                    This Chat System and its documentation are protected by copyright.
                    Unauthorized copying, redistribution, or commercial use without permission is not allowed,
                    except where separately licensed by the copyright holder.
                </p>
            </section>

            <p class="manual-copyright">
                Copyright © 2026 Eng Choon Hao. All Rights Reserved.
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php include_once __DIR__ . '/includes/copyright_footer.php'; ?>
</body>
</html>

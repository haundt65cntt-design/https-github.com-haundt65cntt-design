<?php
/**
 * Personal social feed for NakiriFubuki/chatting-system.
 * PHP 7.4+ / PDO / MySQL. Uses the existing config and session conventions.
 */
require_once __DIR__ . '/config/paths.php';
require_once __DIR__ . '/includes/language.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
if (empty($_SESSION['social_feed_csrf'])) {
    $_SESSION['social_feed_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['social_feed_csrf'];
$errorMessage = '';
$successMessage = '';
$uploadDirectory = __DIR__ . '/uploads/social_posts';
$uploadWebDirectory = 'uploads/social_posts';

$reactionMeta = array(
    'like'  => array('emoji' => '👍', 'label' => 'Thích'),
    'love'  => array('emoji' => '❤️', 'label' => 'Yêu thích'),
    'laugh' => array('emoji' => '😂', 'label' => 'Haha'),
    'angry' => array('emoji' => '😠', 'label' => 'Phẫn nộ'),
    'sad'   => array('emoji' => '😢', 'label' => 'Buồn')
);

function socialFeedEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function socialFeedLength($value)
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function socialFeedRedirect($query = '')
{
    header('Location: social_feed.php' . $query);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!hash_equals($csrfToken, $submittedToken)) {
        $errorMessage = 'Phiên gửi biểu mẫu không hợp lệ. Hãy tải lại trang và thử lại.';
    } else {
        $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
        try {
            if ($action === 'create') {
                $content = trim(isset($_POST['content']) ? (string) $_POST['content'] : '');
                if (socialFeedLength($content) > 5000) {
                    throw new RuntimeException('Nội dung bài viết không được vượt quá 5.000 ký tự.');
                }

                $imageFilename = null;
                $imageWasMoved = false;
                $storedImagePath = null;

                if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                        throw new RuntimeException('Tải ảnh lên thất bại. Hãy thử lại.');
                    }
                    if ((int) $_FILES['image']['size'] > 5 * 1024 * 1024) {
                        throw new RuntimeException('Ảnh phải có dung lượng không quá 5 MB.');
                    }
                    if (!is_uploaded_file($_FILES['image']['tmp_name'])) {
                        throw new RuntimeException('Tệp tải lên không hợp lệ.');
                    }
                    if (!class_exists('finfo')) {
                        throw new RuntimeException('PHP cần bật extension fileinfo để kiểm tra ảnh tải lên.');
                    }
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($_FILES['image']['tmp_name']);
                    $allowedTypes = array(
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp',
                        'image/gif'  => 'gif'
                    );
                    if (!isset($allowedTypes[$mimeType])) {
                        throw new RuntimeException('Chỉ chấp nhận ảnh JPG, PNG, WEBP hoặc GIF.');
                    }
                    if (!function_exists('getimagesize') || @getimagesize($_FILES['image']['tmp_name']) === false) {
                        throw new RuntimeException('Tệp tải lên không phải ảnh hợp lệ.');
                    }
                    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                        throw new RuntimeException('Không thể tạo thư mục uploads/social_posts.');
                    }
                    if (!is_writable($uploadDirectory)) {
                        throw new RuntimeException('Thư mục uploads/social_posts không có quyền ghi.');
                    }
                    $imageFilename = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
                    $storedImagePath = $uploadDirectory . DIRECTORY_SEPARATOR . $imageFilename;
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $storedImagePath)) {
                        throw new RuntimeException('Không thể lưu ảnh. Hãy kiểm tra quyền thư mục uploads.');
                    }
                    $imageWasMoved = true;
                }

                if ($content === '' && $imageFilename === null) {
                    throw new RuntimeException('Bạn hãy nhập nội dung hoặc chọn một ảnh để đăng.');
                }

                try {
                    $stmt = $pdo->prepare('INSERT INTO social_posts (user_id, content, image_filename) VALUES (?, ?, ?)');
                    $stmt->execute(array($userId, $content, $imageFilename));
                } catch (Throwable $insertError) {
                    if ($imageWasMoved && $storedImagePath && is_file($storedImagePath)) {
                        @unlink($storedImagePath);
                    }
                    throw $insertError;
                }
                socialFeedRedirect('?posted=1');

            } elseif ($action === 'delete_post') {
                $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
                $stmt = $pdo->prepare('SELECT image_filename FROM social_posts WHERE id = ? AND user_id = ?');
                $stmt->execute(array($postId, $userId));
                $ownedPost = $stmt->fetch();
                if (!$ownedPost) {
                    throw new RuntimeException('Không tìm thấy bài viết hoặc bạn không có quyền xóa.');
                }
                $stmt = $pdo->prepare('DELETE FROM social_posts WHERE id = ? AND user_id = ?');
                $stmt->execute(array($postId, $userId));
                if (!empty($ownedPost['image_filename'])) {
                    $oldImagePath = $uploadDirectory . DIRECTORY_SEPARATOR . basename($ownedPost['image_filename']);
                    if (is_file($oldImagePath)) {
                        @unlink($oldImagePath);
                    }
                }
                socialFeedRedirect('?deleted=1');

            } elseif ($action === 'add_comment') {
                $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
                $commentContent = trim(isset($_POST['comment_content']) ? (string) $_POST['comment_content'] : '');
                if ($postId < 1 || $commentContent === '') {
                    throw new RuntimeException('Nội dung bình luận không được để trống.');
                }
                if (socialFeedLength($commentContent) > 2000) {
                    throw new RuntimeException('Bình luận không được vượt quá 2.000 ký tự.');
                }
                $checkPost = $pdo->prepare('SELECT id FROM social_posts WHERE id = ?');
                $checkPost->execute(array($postId));
                if (!$checkPost->fetchColumn()) {
                    throw new RuntimeException('Bài viết không còn tồn tại.');
                }
                $stmt = $pdo->prepare('INSERT INTO social_post_comments (post_id, user_id, content) VALUES (?, ?, ?)');
                $stmt->execute(array($postId, $userId, $commentContent));
                socialFeedRedirect('?commented=1#post-' . $postId);

            } elseif ($action === 'delete_comment') {
                $commentId = isset($_POST['comment_id']) ? (int) $_POST['comment_id'] : 0;
                $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
                $stmt = $pdo->prepare('DELETE FROM social_post_comments WHERE id = ? AND user_id = ? AND post_id = ?');
                $stmt->execute(array($commentId, $userId, $postId));
                if ($stmt->rowCount() < 1) {
                    throw new RuntimeException('Không tìm thấy bình luận hoặc bạn không có quyền xóa.');
                }
                socialFeedRedirect('?comment_deleted=1#post-' . $postId);

            } elseif ($action === 'toggle_reaction') {
                $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
                $reactionType = isset($_POST['reaction_type']) ? (string) $_POST['reaction_type'] : '';
                if (!isset($reactionMeta[$reactionType])) {
                    throw new RuntimeException('Loại cảm xúc không hợp lệ.');
                }
                $checkPost = $pdo->prepare('SELECT id FROM social_posts WHERE id = ?');
                $checkPost->execute(array($postId));
                if (!$checkPost->fetchColumn()) {
                    throw new RuntimeException('Bài viết không còn tồn tại.');
                }
                $current = $pdo->prepare('SELECT reaction_type FROM social_post_reactions WHERE post_id = ? AND user_id = ?');
                $current->execute(array($postId, $userId));
                $currentType = $current->fetchColumn();
                if ($currentType === $reactionType) {
                    $remove = $pdo->prepare('DELETE FROM social_post_reactions WHERE post_id = ? AND user_id = ?');
                    $remove->execute(array($postId, $userId));
                } else {
                    $upsert = $pdo->prepare(
                        'INSERT INTO social_post_reactions (post_id, user_id, reaction_type) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE reaction_type = VALUES(reaction_type), created_at = CURRENT_TIMESTAMP'
                    );
                    $upsert->execute(array($postId, $userId, $reactionType));
                }
                socialFeedRedirect('#post-' . $postId);
            } else {
                throw new RuntimeException('Thao tác không hợp lệ.');
            }
        } catch (Throwable $e) {
            error_log('[social_feed] ' . $e->getMessage());
            $errorMessage = ($e instanceof RuntimeException)
                ? $e->getMessage()
                : 'Có lỗi khi xử lý. Hãy kiểm tra các bảng social_posts, social_post_reactions và social_post_comments đã được tạo chưa.';
        }
    }
}

if (isset($_GET['posted'])) $successMessage = 'Đăng bài thành công.';
elseif (isset($_GET['deleted'])) $successMessage = 'Đã xóa bài viết.';
elseif (isset($_GET['commented'])) $successMessage = 'Đã thêm bình luận.';
elseif (isset($_GET['comment_deleted'])) $successMessage = 'Đã xóa bình luận.';

try {
    $stmt = $pdo->prepare('SELECT id, username, full_name, avatar FROM users WHERE id = ?');
    $stmt->execute(array($userId));
    $currentUser = $stmt->fetch();
    if (!$currentUser) throw new RuntimeException('Không tìm thấy tài khoản đang đăng nhập.');

    $stmt = $pdo->prepare(
        'SELECT p.id, p.user_id, p.content, p.image_filename, p.created_at,
                u.username, u.full_name, u.avatar,
                (SELECT COUNT(*) FROM social_post_reactions r WHERE r.post_id = p.id) AS reaction_count,
                (SELECT COUNT(*) FROM social_post_comments c WHERE c.post_id = p.id) AS comment_count,
                (SELECT r2.reaction_type FROM social_post_reactions r2 WHERE r2.post_id = p.id AND r2.user_id = ? LIMIT 1) AS my_reaction
         FROM social_posts p
         INNER JOIN users u ON u.id = p.user_id
         ORDER BY p.created_at DESC, p.id DESC
         LIMIT 50'
    );
    $stmt->execute(array($userId));
    $posts = $stmt->fetchAll();

    $commentsByPost = array();
    $reactionsByPost = array();
    if (!empty($posts)) {
        $postIds = array_map('intval', array_column($posts, 'id'));
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));

        $commentStmt = $pdo->prepare(
            "SELECT c.id, c.post_id, c.user_id, c.content, c.created_at,
                    u.username, u.full_name, u.avatar
             FROM social_post_comments c
             INNER JOIN users u ON u.id = c.user_id
             WHERE c.post_id IN ($placeholders)
             ORDER BY c.created_at ASC, c.id ASC"
        );
        $commentStmt->execute($postIds);
        foreach ($commentStmt->fetchAll() as $comment) {
            $pid = (int) $comment['post_id'];
            if (!isset($commentsByPost[$pid])) $commentsByPost[$pid] = array();
            $commentsByPost[$pid][] = $comment;
        }

        $reactionStmt = $pdo->prepare(
            "SELECT post_id, reaction_type, COUNT(*) AS total
             FROM social_post_reactions
             WHERE post_id IN ($placeholders)
             GROUP BY post_id, reaction_type"
        );
        $reactionStmt->execute($postIds);
        foreach ($reactionStmt->fetchAll() as $reaction) {
            $pid = (int) $reaction['post_id'];
            if (!isset($reactionsByPost[$pid])) $reactionsByPost[$pid] = array();
            $reactionsByPost[$pid][$reaction['reaction_type']] = (int) $reaction['total'];
        }
    }
} catch (Throwable $e) {
    error_log('[social_feed] ' . $e->getMessage());
    $posts = array();
    $commentsByPost = array();
    $reactionsByPost = array();
    $currentUser = array(
        'username' => isset($_SESSION['username']) ? $_SESSION['username'] : 'Bạn',
        'full_name' => isset($_SESSION['full_name']) ? $_SESSION['full_name'] : '',
        'avatar' => 'default_avatar.png'
    );
    if ($errorMessage === '') {
        $errorMessage = 'Không đọc được bảng tin. Hãy kiểm tra các bảng social_posts, social_post_reactions và social_post_comments đã được tạo.';
    }
}

$currentDisplayName = !empty($currentUser['full_name']) ? $currentUser['full_name'] : $currentUser['username'];
$currentAvatar = isset($currentUser['avatar']) ? $currentUser['avatar'] : 'default_avatar.png';
$currentAvatarPath = 'uploads/avatars/' . basename((string) $currentAvatar);
$currentAvatarExists = $currentAvatar && $currentAvatar !== 'default_avatar.png' && is_file(__DIR__ . '/' . $currentAvatarPath);

ob_start();
?>
<div class="social-feed-page">
    <div class="social-feed-header">
        <div><h1><i class="fas fa-newspaper"></i> Bảng tin</h1><p>Chia sẻ khoảnh khắc và cập nhật với mọi người.</p></div>
    </div>

    <?php if ($successMessage !== ''): ?><div class="sf-alert sf-alert-success"><?php echo socialFeedEscape($successMessage); ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="sf-alert sf-alert-error"><?php echo socialFeedEscape($errorMessage); ?></div><?php endif; ?>

    <section class="sf-card sf-create-card">
        <div class="sf-create-user">
            <?php if ($currentAvatarExists): ?><img class="sf-avatar" src="<?php echo socialFeedEscape($currentAvatarPath); ?>" alt="Ảnh đại diện">
            <?php else: ?><div class="sf-avatar sf-avatar-letter"><?php echo socialFeedEscape(strtoupper(substr($currentDisplayName, 0, 1))); ?></div><?php endif; ?>
            <strong><?php echo socialFeedEscape($currentDisplayName); ?></strong>
        </div>
        <form method="post" enctype="multipart/form-data" class="sf-post-form">
            <input type="hidden" name="csrf_token" value="<?php echo socialFeedEscape($csrfToken); ?>">
            <input type="hidden" name="action" value="create">
            <label class="sf-sr-only" for="sf-content">Nội dung bài viết</label>
            <textarea id="sf-content" name="content" maxlength="5000" placeholder="Bạn đang nghĩ gì?" rows="4"></textarea>
            <div class="sf-form-bottom">
                <label for="sf-image" class="sf-image-label"><i class="fas fa-image"></i> Thêm ảnh</label>
                <input id="sf-image" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
                <span class="sf-file-name" id="sf-file-name">JPG, PNG, WEBP, GIF · tối đa 5 MB</span>
                <button type="submit" class="sf-primary-button"><i class="fas fa-paper-plane"></i> Đăng bài</button>
            </div>
            <div id="sf-image-preview-wrap" class="sf-image-preview-wrap" hidden>
                <img id="sf-image-preview" alt="Xem trước ảnh">
                <button type="button" id="sf-remove-image" class="sf-remove-image" aria-label="Bỏ ảnh">×</button>
            </div>
        </form>
    </section>

    <div class="sf-feed-title"><h2>Bài viết mới nhất</h2><span><?php echo count($posts); ?> bài gần đây</span></div>

    <div class="sf-post-list">
        <?php if (empty($posts)): ?>
            <section class="sf-card sf-empty"><i class="fas fa-comments"></i><h3>Chưa có bài viết nào</h3><p>Hãy là người đầu tiên chia sẻ điều gì đó.</p></section>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <?php
                    $postId = (int) $post['id'];
                    $displayName = !empty($post['full_name']) ? $post['full_name'] : $post['username'];
                    $avatar = isset($post['avatar']) ? $post['avatar'] : 'default_avatar.png';
                    $avatarPath = 'uploads/avatars/' . basename((string) $avatar);
                    $avatarExists = $avatar && $avatar !== 'default_avatar.png' && is_file(__DIR__ . '/' . $avatarPath);
                    $imagePath = !empty($post['image_filename']) ? $uploadWebDirectory . '/' . basename($post['image_filename']) : '';
                    $myReaction = isset($post['my_reaction']) ? $post['my_reaction'] : null;
                    $postComments = isset($commentsByPost[$postId]) ? $commentsByPost[$postId] : array();
                    $reactionCounts = isset($reactionsByPost[$postId]) ? $reactionsByPost[$postId] : array();
                ?>
                <article class="sf-card sf-post" id="post-<?php echo $postId; ?>">
                    <div class="sf-post-header">
                        <button type="button" class="sf-profile-avatar-trigger" onclick="window.viewUserProfile(<?php echo (int) $post['user_id']; ?>)" aria-label="Xem hồ sơ <?php echo socialFeedEscape($displayName); ?>">
                            <?php if ($avatarExists): ?><img class="sf-avatar" src="<?php echo socialFeedEscape($avatarPath); ?>" alt="Ảnh đại diện">
                            <?php else: ?><span class="sf-avatar sf-avatar-letter"><?php echo socialFeedEscape(strtoupper(substr($displayName, 0, 1))); ?></span><?php endif; ?>
                        </button>
                        <div class="sf-author-details">
                            <button type="button" class="sf-profile-name-trigger" onclick="window.viewUserProfile(<?php echo (int) $post['user_id']; ?>)"><?php echo socialFeedEscape($displayName); ?></button>
                            <time datetime="<?php echo socialFeedEscape($post['created_at']); ?>"><?php echo socialFeedEscape(date('d/m/Y H:i', strtotime($post['created_at']))); ?></time>
                        </div>
                        <?php if ((int) $post['user_id'] === $userId): ?>
                            <form method="post" class="sf-delete-form" onsubmit="return confirm('Bạn có chắc muốn xóa bài viết này?');">
                                <input type="hidden" name="csrf_token" value="<?php echo socialFeedEscape($csrfToken); ?>">
                                <input type="hidden" name="action" value="delete_post">
                                <input type="hidden" name="post_id" value="<?php echo $postId; ?>">
                                <button type="submit" class="sf-delete-button" title="Xóa bài viết"><i class="fas fa-trash-alt"></i></button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php if (trim((string) $post['content']) !== ''): ?><div class="sf-post-content"><?php echo nl2br(socialFeedEscape($post['content'])); ?></div><?php endif; ?>
                    <?php if ($imagePath !== ''): ?><a href="<?php echo socialFeedEscape($imagePath); ?>" target="_blank" rel="noopener" class="sf-post-image-link"><img class="sf-post-image" src="<?php echo socialFeedEscape($imagePath); ?>" alt="Ảnh trong bài viết" loading="lazy"></a><?php endif; ?>

                    <div class="sf-post-stats">
                        <div class="sf-reaction-summary">
                            <?php foreach ($reactionMeta as $type => $meta): if (!empty($reactionCounts[$type])): ?>
                                <span title="<?php echo socialFeedEscape($meta['label']); ?>"><?php echo $meta['emoji']; ?> <b><?php echo (int) $reactionCounts[$type]; ?></b></span>
                            <?php endif; endforeach; ?>
                        </div>
                        <span><?php echo (int) $post['comment_count']; ?> bình luận</span>
                    </div>

                    <div class="sf-post-actions">
                        <?php foreach ($reactionMeta as $type => $meta): ?>
                            <form method="post" class="sf-reaction-form">
                                <input type="hidden" name="csrf_token" value="<?php echo socialFeedEscape($csrfToken); ?>">
                                <input type="hidden" name="action" value="toggle_reaction">
                                <input type="hidden" name="post_id" value="<?php echo $postId; ?>">
                                <input type="hidden" name="reaction_type" value="<?php echo socialFeedEscape($type); ?>">
                                <button type="submit" class="sf-action-button<?php echo ($myReaction === $type) ? ' is-selected' : ''; ?>" title="<?php echo socialFeedEscape($meta['label']); ?>">
                                    <span><?php echo $meta['emoji']; ?></span><span><?php echo socialFeedEscape($meta['label']); ?></span>
                                </button>
                            </form>
                        <?php endforeach; ?>
                        <a class="sf-action-button sf-comment-jump" href="#comment-form-<?php echo $postId; ?>"><i class="far fa-comment"></i><span>Bình luận</span></a>
                    </div>

                    <div class="sf-comments-section">
                        <?php if (!empty($postComments)): ?>
                            <div class="sf-comments-list">
                                <?php foreach ($postComments as $comment): ?>
                                    <?php
                                        $commentName = !empty($comment['full_name']) ? $comment['full_name'] : $comment['username'];
                                        $commentAvatar = !empty($comment['avatar']) ? $comment['avatar'] : 'default_avatar.png';
                                        $commentAvatarPath = 'uploads/avatars/' . basename((string) $commentAvatar);
                                        $commentAvatarExists = $commentAvatar !== 'default_avatar.png' && is_file(__DIR__ . '/' . $commentAvatarPath);
                                    ?>
                                    <div class="sf-comment-item">
                                        <button type="button" class="sf-profile-avatar-trigger sf-comment-profile-trigger" onclick="window.viewUserProfile(<?php echo (int) $comment['user_id']; ?>)" aria-label="Xem hồ sơ <?php echo socialFeedEscape($commentName); ?>">
                                            <?php if ($commentAvatarExists): ?><img class="sf-comment-avatar" src="<?php echo socialFeedEscape($commentAvatarPath); ?>" alt="">
                                            <?php else: ?><span class="sf-comment-avatar sf-avatar-letter"><?php echo socialFeedEscape(strtoupper(substr($commentName, 0, 1))); ?></span><?php endif; ?>
                                        </button>
                                        <div class="sf-comment-bubble">
                                            <div class="sf-comment-meta"><button type="button" class="sf-profile-name-trigger sf-comment-name-trigger" onclick="window.viewUserProfile(<?php echo (int) $comment['user_id']; ?>)"><?php echo socialFeedEscape($commentName); ?></button><time><?php echo socialFeedEscape(date('d/m/Y H:i', strtotime($comment['created_at']))); ?></time></div>
                                            <div class="sf-comment-content"><?php echo nl2br(socialFeedEscape($comment['content'])); ?></div>
                                        </div>
                                        <?php if ((int) $comment['user_id'] === $userId): ?>
                                            <form method="post" class="sf-comment-delete-form" onsubmit="return confirm('Xóa bình luận này?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo socialFeedEscape($csrfToken); ?>">
                                                <input type="hidden" name="action" value="delete_comment">
                                                <input type="hidden" name="post_id" value="<?php echo $postId; ?>">
                                                <input type="hidden" name="comment_id" value="<?php echo (int) $comment['id']; ?>">
                                                <button type="submit" title="Xóa bình luận" aria-label="Xóa bình luận">×</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="sf-no-comments">Chưa có bình luận. Hãy chia sẻ suy nghĩ của bạn.</div>
                        <?php endif; ?>

                        <form method="post" class="sf-comment-form" id="comment-form-<?php echo $postId; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo socialFeedEscape($csrfToken); ?>">
                            <input type="hidden" name="action" value="add_comment">
                            <input type="hidden" name="post_id" value="<?php echo $postId; ?>">
                            <label class="sf-sr-only" for="comment-content-<?php echo $postId; ?>">Viết bình luận</label>
                            <input id="comment-content-<?php echo $postId; ?>" type="text" name="comment_content" maxlength="2000" placeholder="Viết bình luận..." required>
                            <button type="submit" title="Gửi bình luận"><i class="fas fa-paper-plane"></i></button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php $mainContent = ob_get_clean(); ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng tin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/theme.css" rel="stylesheet">
    <link href="assets/css/social_feed.css" rel="stylesheet">
    <style>
        /* Reset native button chrome on clickable profile names and avatars. */
        .social-feed-view .sf-post-header button.sf-profile-avatar-trigger,
        .social-feed-view .sf-comment-item button.sf-profile-avatar-trigger,
        .social-feed-view button.sf-profile-name-trigger {
            -webkit-appearance: none !important;
            appearance: none !important;
            border: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 !important;
            color: inherit;
            font: inherit;
            text-align: left;
            text-decoration: none;
            cursor: pointer;
        }
        .social-feed-view .sf-post-header button.sf-profile-avatar-trigger {
            display: inline-flex !important;
            flex: 0 0 44px;
            width: 44px;
            height: 44px;
            align-items: center;
            justify-content: center;
            border-radius: 50% !important;
            line-height: 0;
        }
        .social-feed-view .sf-comment-item button.sf-profile-avatar-trigger {
            display: inline-flex !important;
            flex: 0 0 34px;
            width: 34px;
            height: 34px;
            align-items: center;
            justify-content: center;
            border-radius: 50% !important;
            line-height: 0;
        }
        .social-feed-view button.sf-profile-name-trigger {
            display: inline-block !important;
            width: auto !important;
            min-width: 0 !important;
            min-height: 0 !important;
            font-weight: 700;
            line-height: 1.35;
            vertical-align: baseline;
        }
        .social-feed-view button.sf-profile-name-trigger:hover {
            color: #0877d1 !important;
            text-decoration: underline;
        }
        .social-feed-view button.sf-profile-avatar-trigger:focus-visible,
        .social-feed-view button.sf-profile-name-trigger:focus-visible {
            outline: 2px solid #1684e8 !important;
            outline-offset: 3px;
        }
    </style>
</head>
<body class="zalo-app-page social-feed-view">
<?php include __DIR__ . '/navbar.php'; ?>
<script>
(function () {
    var main = document.getElementById('mainWrapper');
    if (main) main.innerHTML = <?php echo json_encode($mainContent, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    var fileInput = document.getElementById('sf-image');
    var fileName = document.getElementById('sf-file-name');
    var previewWrap = document.getElementById('sf-image-preview-wrap');
    var preview = document.getElementById('sf-image-preview');
    var removeButton = document.getElementById('sf-remove-image');
    if (fileInput) fileInput.addEventListener('change', function () {
        var file = fileInput.files && fileInput.files[0];
        if (!file) return;
        fileName.textContent = file.name;
        if (file.size > 5 * 1024 * 1024 || file.type.indexOf('image/') !== 0) {
            alert(file.size > 5 * 1024 * 1024 ? 'Ảnh phải không quá 5 MB.' : 'Vui lòng chọn một tệp ảnh.');
            fileInput.value = ''; fileName.textContent = 'JPG, PNG, WEBP, GIF · tối đa 5 MB'; previewWrap.hidden = true; return;
        }
        preview.src = URL.createObjectURL(file); previewWrap.hidden = false;
    });
    if (removeButton) removeButton.addEventListener('click', function () {
        fileInput.value = ''; preview.removeAttribute('src'); previewWrap.hidden = true;
        fileName.textContent = 'JPG, PNG, WEBP, GIF · tối đa 5 MB';
    });
})();
</script>
</body>
</html>

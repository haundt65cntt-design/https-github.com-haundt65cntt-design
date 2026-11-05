-- =============================================================
-- DEMO DATA for chatting_system (15-table slim database)
-- Compatible with MySQL 5.7+ / MariaDB on XAMPP.
-- Safe to run more than once: INSERTs are guarded by unique keys
-- or NOT EXISTS checks. This script does NOT DROP or UPDATE real data.
--
-- Demo account password: Demo@123
-- Main account is expected to be username 'admin'. If your account
-- has another username, change the value in the @main_user_id query.
-- =============================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

START TRANSACTION;

-- 1. Demo accounts (existing usernames are left unchanged).
-- password_hash('Demo@123', PASSWORD_DEFAULT)
INSERT IGNORE INTO users
    (username, email, password, full_name, avatar, status, language)
VALUES
    ('demo_an',   'demo_an@example.test',   '$2y$12$zgGTabQtrA/AmS.J5lIQzOZx5YEdm7iTvOUDUR0ED5WFxZbZ5gate', 'Nguyễn An',   'default_avatar.png', 'offline', 'vi'),
    ('demo_binh', 'demo_binh@example.test', '$2y$12$zgGTabQtrA/AmS.J5lIQzOZx5YEdm7iTvOUDUR0ED5WFxZbZ5gate', 'Trần Bình',   'default_avatar.png', 'offline', 'vi'),
    ('demo_chi',  'demo_chi@example.test',  '$2y$12$zgGTabQtrA/AmS.J5lIQzOZx5YEdm7iTvOUDUR0ED5WFxZbZ5gate', 'Lê Chi',      'default_avatar.png', 'offline', 'vi'),
    ('demo_dung', 'demo_dung@example.test', '$2y$12$zgGTabQtrA/AmS.J5lIQzOZx5YEdm7iTvOUDUR0ED5WFxZbZ5gate', 'Phạm Dũng',   'default_avatar.png', 'offline', 'vi'),
    ('demo_huy',  'demo_huy@example.test',  '$2y$12$zgGTabQtrA/AmS.J5lIQzOZx5YEdm7iTvOUDUR0ED5WFxZbZ5gate', 'Hoàng Huy',   'default_avatar.png', 'offline', 'vi'),
    ('demo_lan',  'demo_lan@example.test',  '$2y$12$zgGTabQtrA/AmS.J5lIQzOZx5YEdm7iTvOUDUR0ED5WFxZbZ5gate', 'Nguyễn Lan',  'default_avatar.png', 'offline', 'vi');

-- Resolve IDs by username: never assumes AUTO_INCREMENT values.
SET @main_user_id := (SELECT id FROM users WHERE username = 'admin' LIMIT 1);
SET @an_id        := (SELECT id FROM users WHERE username = 'demo_an' LIMIT 1);
SET @binh_id      := (SELECT id FROM users WHERE username = 'demo_binh' LIMIT 1);
SET @chi_id       := (SELECT id FROM users WHERE username = 'demo_chi' LIMIT 1);
SET @dung_id      := (SELECT id FROM users WHERE username = 'demo_dung' LIMIT 1);
SET @huy_id       := (SELECT id FROM users WHERE username = 'demo_huy' LIMIT 1);
SET @lan_id       := (SELECT id FROM users WHERE username = 'demo_lan' LIMIT 1);

-- If username 'admin' does not exist, use demo_an as the main sample account.
SET @main_user_id := COALESCE(@main_user_id, @an_id);

-- 2. Friendships. Reciprocal rows match the application's friend-list logic.
INSERT IGNORE INTO friends (user_id, friend_id, status)
SELECT @main_user_id, u.id, 'accepted'
FROM users u
WHERE @main_user_id IS NOT NULL
  AND u.username IN ('demo_an', 'demo_binh', 'demo_chi', 'demo_dung', 'demo_lan')
  AND u.id <> @main_user_id;

INSERT IGNORE INTO friends (user_id, friend_id, status)
SELECT u.id, @main_user_id, 'accepted'
FROM users u
WHERE @main_user_id IS NOT NULL
  AND u.username IN ('demo_an', 'demo_binh', 'demo_chi', 'demo_dung', 'demo_lan')
  AND u.id <> @main_user_id;

-- Extra friendships between demo users.
INSERT IGNORE INTO friends (user_id, friend_id, status)
SELECT @an_id, @binh_id, 'accepted' WHERE @an_id IS NOT NULL AND @binh_id IS NOT NULL AND @an_id <> @binh_id;
INSERT IGNORE INTO friends (user_id, friend_id, status)
SELECT @binh_id, @an_id, 'accepted' WHERE @an_id IS NOT NULL AND @binh_id IS NOT NULL AND @an_id <> @binh_id;
INSERT IGNORE INTO friends (user_id, friend_id, status)
SELECT @chi_id, @dung_id, 'accepted' WHERE @chi_id IS NOT NULL AND @dung_id IS NOT NULL AND @chi_id <> @dung_id;
INSERT IGNORE INTO friends (user_id, friend_id, status)
SELECT @dung_id, @chi_id, 'accepted' WHERE @chi_id IS NOT NULL AND @dung_id IS NOT NULL AND @chi_id <> @dung_id;

-- One pending request for testing the accept/reject interface.
-- When admin is the main account, demo_huy will appear in pending requests.
INSERT IGNORE INTO friends (user_id, friend_id, status)
SELECT @huy_id, @main_user_id, 'pending'
WHERE @huy_id IS NOT NULL AND @main_user_id IS NOT NULL AND @huy_id <> @main_user_id;

-- 3. Demo groups and memberships.
INSERT INTO `groups` (name, description, avatar, created_by)
SELECT 'Nhóm bạn học - Demo', 'Nhóm mẫu để kiểm tra chat nhóm, thành viên và ghim nhóm.', 'default_group_avatar.png', @main_user_id
WHERE @main_user_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `groups`
      WHERE name = 'Nhóm bạn học - Demo' AND created_by = @main_user_id
  );

SET @study_group_id := (
    SELECT id FROM `groups`
    WHERE name = 'Nhóm bạn học - Demo' AND created_by = @main_user_id
    ORDER BY id LIMIT 1
);

INSERT IGNORE INTO group_members (group_id, user_id, role)
SELECT @study_group_id, @main_user_id, 'owner'
WHERE @study_group_id IS NOT NULL AND @main_user_id IS NOT NULL;
INSERT IGNORE INTO group_members (group_id, user_id, role)
SELECT @study_group_id, u.id, 'member'
FROM users u
WHERE @study_group_id IS NOT NULL
  AND u.username IN ('demo_an', 'demo_binh', 'demo_chi', 'demo_dung', 'demo_lan')
  AND u.id <> @main_user_id;

INSERT INTO `groups` (name, description, avatar, created_by)
SELECT 'Team dự án PHP - Demo', 'Nhóm trao đổi bài tập PHP thuần, MySQL và XAMPP.', 'default_group_avatar.png', @binh_id
WHERE @binh_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `groups`
      WHERE name = 'Team dự án PHP - Demo' AND created_by = @binh_id
  );

SET @project_group_id := (
    SELECT id FROM `groups`
    WHERE name = 'Team dự án PHP - Demo' AND created_by = @binh_id
    ORDER BY id LIMIT 1
);

INSERT IGNORE INTO group_members (group_id, user_id, role)
SELECT @project_group_id, @binh_id, 'owner'
WHERE @project_group_id IS NOT NULL AND @binh_id IS NOT NULL;
INSERT IGNORE INTO group_members (group_id, user_id, role)
SELECT @project_group_id, u.id,
       CASE WHEN u.id = @main_user_id THEN 'admin' ELSE 'member' END
FROM users u
WHERE @project_group_id IS NOT NULL
  AND u.username IN ('admin', 'demo_an', 'demo_chi', 'demo_dung', 'demo_lan')
  AND u.id <> @binh_id;

-- 4. Private messages. These are visible to the admin account and each demo friend.
INSERT INTO messages (sender_id, receiver_id, group_id, message, message_type, created_at)
SELECT @main_user_id, @an_id, NULL, 'Chào An! Mình đang kiểm tra chức năng nhắn tin riêng.', 'text', NOW() - INTERVAL 55 MINUTE
WHERE @main_user_id IS NOT NULL AND @an_id IS NOT NULL AND @main_user_id <> @an_id
  AND NOT EXISTS (SELECT 1 FROM messages WHERE sender_id = @main_user_id AND receiver_id = @an_id AND message = 'Chào An! Mình đang kiểm tra chức năng nhắn tin riêng.');
INSERT INTO messages (sender_id, receiver_id, group_id, message, message_type, created_at)
SELECT @an_id, @main_user_id, NULL, 'Chào bạn! Mình đã nhận được tin nhắn nhé 😊', 'text', NOW() - INTERVAL 52 MINUTE
WHERE @main_user_id IS NOT NULL AND @an_id IS NOT NULL AND @main_user_id <> @an_id
  AND NOT EXISTS (SELECT 1 FROM messages WHERE sender_id = @an_id AND receiver_id = @main_user_id AND message = 'Chào bạn! Mình đã nhận được tin nhắn nhé 😊');
INSERT INTO messages (sender_id, receiver_id, group_id, message, message_type, is_edited, edited_at, created_at)
SELECT @main_user_id, @an_id, NULL, 'Bạn thử gửi một tin nhắn có dấu tiếng Việt và emoji xem nhé 😊.', 'text', 1, NOW() - INTERVAL 47 MINUTE, NOW() - INTERVAL 48 MINUTE
WHERE @main_user_id IS NOT NULL AND @an_id IS NOT NULL AND @main_user_id <> @an_id
  AND NOT EXISTS (SELECT 1 FROM messages WHERE sender_id = @main_user_id AND receiver_id = @an_id AND message = 'Bạn thử gửi một tin nhắn có dấu tiếng Việt và emoji xem nhé 😊.');

INSERT INTO messages (sender_id, receiver_id, group_id, message, message_type, created_at)
SELECT @binh_id, @main_user_id, NULL, 'Mình đã tạo nhóm Team dự án PHP, bạn vào xem nhé.', 'text', NOW() - INTERVAL 42 MINUTE
WHERE @main_user_id IS NOT NULL AND @binh_id IS NOT NULL AND @main_user_id <> @binh_id
  AND NOT EXISTS (SELECT 1 FROM messages WHERE sender_id = @binh_id AND receiver_id = @main_user_id AND message = 'Mình đã tạo nhóm Team dự án PHP, bạn vào xem nhé.');
INSERT INTO messages (sender_id, receiver_id, group_id, message, message_type, created_at)
SELECT @main_user_id, @binh_id, NULL, 'Mình thấy rồi. Giao diện chat đang được hoàn thiện.', 'text', NOW() - INTERVAL 39 MINUTE
WHERE @main_user_id IS NOT NULL AND @binh_id IS NOT NULL AND @main_user_id <> @binh_id
  AND NOT EXISTS (SELECT 1 FROM messages WHERE sender_id = @main_user_id AND receiver_id = @binh_id AND message = 'Mình thấy rồi. Giao diện chat đang được hoàn thiện.');

INSERT INTO messages (sender_id, receiver_id, group_id, message, message_type, created_at)
SELECT @chi_id, @main_user_id, NULL, 'Mình vừa đăng một bài mới trên Bảng tin.', 'text', NOW() - INTERVAL 31 MINUTE
WHERE @main_user_id IS NOT NULL AND @chi_id IS NOT NULL AND @main_user_id <> @chi_id
  AND NOT EXISTS (SELECT 1 FROM messages WHERE sender_id = @chi_id AND receiver_id = @main_user_id AND message = 'Mình vừa đăng một bài mới trên Bảng tin.');
INSERT INTO messages (sender_id, receiver_id, group_id, message, message_type, created_at)
SELECT @main_user_id, @chi_id, NULL, 'Mình sẽ vào xem và để lại bình luận nhé!', 'text', NOW() - INTERVAL 28 MINUTE
WHERE @main_user_id IS NOT NULL AND @chi_id IS NOT NULL AND @main_user_id <> @chi_id
  AND NOT EXISTS (SELECT 1 FROM messages WHERE sender_id = @main_user_id AND receiver_id = @chi_id AND message = 'Mình sẽ vào xem và để lại bình luận nhé!');

-- 5. Group messages for the sample groups.
INSERT INTO messages (sender_id, receiver_id, group_id, message, message_type, created_at)
SELECT @main_user_id, NULL, @study_group_id, 'Chào mọi người! Đây là nhóm chat mẫu.', 'text', NOW() - INTERVAL 24 MINUTE
WHERE @main_user_id IS NOT NULL AND @study_group_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM messages WHERE sender_id = @main_user_id AND group_id = @study_group_id AND message = 'Chào mọi người! Đây là nhóm chat mẫu.');
INSERT INTO messages (sender_id, receiver_id, group_id, message, message_type, created_at)
SELECT @an_id, NULL, @study_group_id, 'Mình đã tham gia nhóm. Rất vui được gặp mọi người!', 'text', NOW() - INTERVAL 21 MINUTE
WHERE @an_id IS NOT NULL AND @study_group_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM messages WHERE sender_id = @an_id AND group_id = @study_group_id AND message = 'Mình đã tham gia nhóm. Rất vui được gặp mọi người!');
INSERT INTO messages (sender_id, receiver_id, group_id, message, message_type, created_at)
SELECT @binh_id, NULL, @study_group_id, 'Chúng ta có thể trao đổi về PHP và MySQL ở đây.', 'text', NOW() - INTERVAL 18 MINUTE
WHERE @binh_id IS NOT NULL AND @study_group_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM messages WHERE sender_id = @binh_id AND group_id = @study_group_id AND message = 'Chúng ta có thể trao đổi về PHP và MySQL ở đây.');
INSERT INTO messages (sender_id, receiver_id, group_id, message, message_type, created_at)
SELECT @chi_id, NULL, @study_group_id, 'Mình sẽ kiểm tra phần giao diện trên XAMPP.', 'text', NOW() - INTERVAL 15 MINUTE
WHERE @chi_id IS NOT NULL AND @study_group_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM messages WHERE sender_id = @chi_id AND group_id = @study_group_id AND message = 'Mình sẽ kiểm tra phần giao diện trên XAMPP.');
INSERT INTO messages (sender_id, receiver_id, group_id, message, message_type, created_at)
SELECT @dung_id, NULL, @project_group_id, 'Mọi người nhớ kiểm tra khóa ngoại trước khi import nhé.', 'text', NOW() - INTERVAL 12 MINUTE
WHERE @dung_id IS NOT NULL AND @project_group_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM messages WHERE sender_id = @dung_id AND group_id = @project_group_id AND message = 'Mọi người nhớ kiểm tra khóa ngoại trước khi import nhé.');

-- 6. A sample message edit, mention, tag and pinned message.
SET @edited_message_id := (
    SELECT id FROM messages
    WHERE sender_id = @main_user_id AND receiver_id = @an_id
      AND message = 'Bạn thử gửi một tin nhắn có dấu tiếng Việt và emoji xem nhé 😊.'
    ORDER BY id LIMIT 1
);
INSERT INTO message_edits (message_id, old_content, new_content)
SELECT @edited_message_id,
       'Bạn thử gửi một tin nhắn có dấu tiếng Việt xem nhé.',
       'Bạn thử gửi một tin nhắn có dấu tiếng Việt và emoji xem nhé 😊.'
WHERE @edited_message_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM message_edits WHERE message_id = @edited_message_id AND new_content = 'Bạn thử gửi một tin nhắn có dấu tiếng Việt và emoji xem nhé 😊.');

SET @tag_message_id := (
    SELECT id FROM messages
    WHERE sender_id = @main_user_id AND group_id = @study_group_id
      AND message = 'Chào mọi người! Đây là nhóm chat mẫu.'
    ORDER BY id LIMIT 1
);
INSERT IGNORE INTO message_tags (message_id, tagged_user_id)
SELECT @tag_message_id, @an_id
WHERE @tag_message_id IS NOT NULL AND @an_id IS NOT NULL;
INSERT INTO user_mentions (message_id, mentioned_user_id)
SELECT @tag_message_id, @binh_id
WHERE @tag_message_id IS NOT NULL AND @binh_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM user_mentions WHERE message_id = @tag_message_id AND mentioned_user_id = @binh_id);

INSERT IGNORE INTO pinned_chats (user_id, chat_id, chat_type)
SELECT @main_user_id, @an_id, 'user'
WHERE @main_user_id IS NOT NULL AND @an_id IS NOT NULL AND @main_user_id <> @an_id;
INSERT IGNORE INTO pinned_chats (user_id, chat_id, chat_type)
SELECT @main_user_id, @study_group_id, 'group'
WHERE @main_user_id IS NOT NULL AND @study_group_id IS NOT NULL;
INSERT IGNORE INTO pinned_messages (message_id, user_id)
SELECT @tag_message_id, @main_user_id
WHERE @tag_message_id IS NOT NULL AND @main_user_id IS NOT NULL;

-- 7. Social Feed posts. Posts are text-first; images can be uploaded from the UI.
INSERT INTO social_posts (user_id, content, image_filename, created_at, updated_at)
SELECT @main_user_id, 'Chào mọi người! Mình đang thử nghiệm Bảng tin của ứng dụng chat PHP. 👋', NULL, NOW() - INTERVAL 3 HOUR, NOW() - INTERVAL 3 HOUR
WHERE @main_user_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM social_posts WHERE user_id = @main_user_id AND content = 'Chào mọi người! Mình đang thử nghiệm Bảng tin của ứng dụng chat PHP. 👋');
INSERT INTO social_posts (user_id, content, image_filename, created_at, updated_at)
SELECT @an_id, 'Cuối tuần dành một chút thời gian học thêm điều mới. Chúc mọi người một ngày vui vẻ! ☀️', NULL, NOW() - INTERVAL 2 HOUR, NOW() - INTERVAL 2 HOUR
WHERE @an_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM social_posts WHERE user_id = @an_id AND content = 'Cuối tuần dành một chút thời gian học thêm điều mới. Chúc mọi người một ngày vui vẻ! ☀️');
INSERT INTO social_posts (user_id, content, image_filename, created_at, updated_at)
SELECT @binh_id, 'Đang hoàn thiện một ứng dụng nhắn tin bằng PHP thuần, MySQL và XAMPP.', NULL, NOW() - INTERVAL 95 MINUTE, NOW() - INTERVAL 95 MINUTE
WHERE @binh_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM social_posts WHERE user_id = @binh_id AND content = 'Đang hoàn thiện một ứng dụng nhắn tin bằng PHP thuần, MySQL và XAMPP.');
INSERT INTO social_posts (user_id, content, image_filename, created_at, updated_at)
SELECT @chi_id, 'Một góc nhỏ để chia sẻ những điều tích cực mỗi ngày. 🌷', NULL, NOW() - INTERVAL 70 MINUTE, NOW() - INTERVAL 70 MINUTE
WHERE @chi_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM social_posts WHERE user_id = @chi_id AND content = 'Một góc nhỏ để chia sẻ những điều tích cực mỗi ngày. 🌷');
INSERT INTO social_posts (user_id, content, image_filename, created_at, updated_at)
SELECT @dung_id, 'Hôm nay mình đã hoàn thành phần cơ sở dữ liệu. Tiếp theo là kiểm thử các chức năng!', NULL, NOW() - INTERVAL 45 MINUTE, NOW() - INTERVAL 45 MINUTE
WHERE @dung_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM social_posts WHERE user_id = @dung_id AND content = 'Hôm nay mình đã hoàn thành phần cơ sở dữ liệu. Tiếp theo là kiểm thử các chức năng!');
INSERT INTO social_posts (user_id, content, image_filename, created_at, updated_at)
SELECT @lan_id, 'Mọi người thích giao diện sáng hay giao diện tối hơn? 💬', NULL, NOW() - INTERVAL 20 MINUTE, NOW() - INTERVAL 20 MINUTE
WHERE @lan_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM social_posts WHERE user_id = @lan_id AND content = 'Mọi người thích giao diện sáng hay giao diện tối hơn? 💬');

-- Resolve sample post IDs by exact author/content pairs.
SET @post_main := (SELECT id FROM social_posts WHERE user_id = @main_user_id AND content = 'Chào mọi người! Mình đang thử nghiệm Bảng tin của ứng dụng chat PHP. 👋' ORDER BY id LIMIT 1);
SET @post_an   := (SELECT id FROM social_posts WHERE user_id = @an_id AND content = 'Cuối tuần dành một chút thời gian học thêm điều mới. Chúc mọi người một ngày vui vẻ! ☀️' ORDER BY id LIMIT 1);
SET @post_binh := (SELECT id FROM social_posts WHERE user_id = @binh_id AND content = 'Đang hoàn thiện một ứng dụng nhắn tin bằng PHP thuần, MySQL và XAMPP.' ORDER BY id LIMIT 1);
SET @post_chi  := (SELECT id FROM social_posts WHERE user_id = @chi_id AND content = 'Một góc nhỏ để chia sẻ những điều tích cực mỗi ngày. 🌷' ORDER BY id LIMIT 1);
SET @post_dung := (SELECT id FROM social_posts WHERE user_id = @dung_id AND content = 'Hôm nay mình đã hoàn thành phần cơ sở dữ liệu. Tiếp theo là kiểm thử các chức năng!' ORDER BY id LIMIT 1);

-- 8. Reactions (one reaction per user per post, as enforced by the table).
INSERT IGNORE INTO social_post_reactions (post_id, user_id, reaction_type)
SELECT @post_main, @an_id, 'love' WHERE @post_main IS NOT NULL AND @an_id IS NOT NULL;
INSERT IGNORE INTO social_post_reactions (post_id, user_id, reaction_type)
SELECT @post_main, @binh_id, 'like' WHERE @post_main IS NOT NULL AND @binh_id IS NOT NULL;
INSERT IGNORE INTO social_post_reactions (post_id, user_id, reaction_type)
SELECT @post_main, @chi_id, 'laugh' WHERE @post_main IS NOT NULL AND @chi_id IS NOT NULL;
INSERT IGNORE INTO social_post_reactions (post_id, user_id, reaction_type)
SELECT @post_an, @dung_id, 'love' WHERE @post_an IS NOT NULL AND @dung_id IS NOT NULL;
INSERT IGNORE INTO social_post_reactions (post_id, user_id, reaction_type)
SELECT @post_binh, @main_user_id, 'like' WHERE @post_binh IS NOT NULL AND @main_user_id IS NOT NULL;
INSERT IGNORE INTO social_post_reactions (post_id, user_id, reaction_type)
SELECT @post_chi, @lan_id, 'sad' WHERE @post_chi IS NOT NULL AND @lan_id IS NOT NULL;
INSERT IGNORE INTO social_post_reactions (post_id, user_id, reaction_type)
SELECT @post_dung, @main_user_id, 'love' WHERE @post_dung IS NOT NULL AND @main_user_id IS NOT NULL;

-- 9. Comments on the sample posts.
INSERT INTO social_post_comments (post_id, user_id, content)
SELECT @post_main, @an_id, 'Chào bạn! Bảng tin hiển thị rất ổn nhé.'
WHERE @post_main IS NOT NULL AND @an_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM social_post_comments WHERE post_id = @post_main AND user_id = @an_id AND content = 'Chào bạn! Bảng tin hiển thị rất ổn nhé.');
INSERT INTO social_post_comments (post_id, user_id, content)
SELECT @post_main, @chi_id, 'Mình thích giao diện mới này 😊'
WHERE @post_main IS NOT NULL AND @chi_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM social_post_comments WHERE post_id = @post_main AND user_id = @chi_id AND content = 'Mình thích giao diện mới này 😊');
INSERT INTO social_post_comments (post_id, user_id, content)
SELECT @post_an, @main_user_id, 'Chúc An học tập hiệu quả nhé!'
WHERE @post_an IS NOT NULL AND @main_user_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM social_post_comments WHERE post_id = @post_an AND user_id = @main_user_id AND content = 'Chúc An học tập hiệu quả nhé!');
INSERT INTO social_post_comments (post_id, user_id, content)
SELECT @post_binh, @dung_id, 'Phần PHP thuần rất phù hợp để học cách tổ chức dự án.'
WHERE @post_binh IS NOT NULL AND @dung_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM social_post_comments WHERE post_id = @post_binh AND user_id = @dung_id AND content = 'Phần PHP thuần rất phù hợp để học cách tổ chức dự án.');
INSERT INTO social_post_comments (post_id, user_id, content)
SELECT @post_chi, @binh_id, 'Đồng ý, mỗi ngày một chút tiến bộ!'
WHERE @post_chi IS NOT NULL AND @binh_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM social_post_comments WHERE post_id = @post_chi AND user_id = @binh_id AND content = 'Đồng ý, mỗi ngày một chút tiến bộ!');
INSERT INTO social_post_comments (post_id, user_id, content)
SELECT @post_dung, @lan_id, 'Chờ xem bản hoàn thiện nhé!'
WHERE @post_dung IS NOT NULL AND @lan_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM social_post_comments WHERE post_id = @post_dung AND user_id = @lan_id AND content = 'Chờ xem bản hoàn thiện nhé!');

COMMIT;

-- Quick verification after import.
SELECT 'users' AS table_name, COUNT(*) AS sample_rows FROM users WHERE username LIKE 'demo\_%';
SELECT 'friends' AS table_name, COUNT(*) AS sample_rows FROM friends WHERE user_id IN (@main_user_id, @an_id, @binh_id, @chi_id, @dung_id, @huy_id, @lan_id);
SELECT 'groups' AS table_name, COUNT(*) AS sample_rows FROM `groups` WHERE name IN ('Nhóm bạn học - Demo', 'Team dự án PHP - Demo');
SELECT 'messages' AS table_name, COUNT(*) AS sample_rows FROM messages WHERE message LIKE '%mẫu%' OR message LIKE '%kiểm tra%' OR message LIKE '%Bảng tin%';
SELECT 'social_posts' AS table_name, COUNT(*) AS sample_rows FROM social_posts WHERE content LIKE '%Bảng tin%' OR content LIKE '%ứng dụng nhắn tin%' OR content LIKE '%cơ sở dữ liệu%';

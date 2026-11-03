-- Advanced Chat System: individual table definition
-- MySQL 5.7+ / MariaDB / XAMPP
-- Select the chatting_system database in phpMyAdmin before importing.
-- Idempotent: CREATE TABLE IF NOT EXISTS; does not erase existing data.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS forum_replies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    reply_to_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reply_to_id) REFERENCES forum_replies(id) ON DELETE SET NULL,
    KEY idx_forum_replies_post (post_id),
    KEY idx_forum_replies_parent (reply_to_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

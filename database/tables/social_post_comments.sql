-- Advanced Chat System: individual table definition
-- MySQL 5.7+ / MariaDB / XAMPP
-- Select the chatting_system database in phpMyAdmin before importing.
-- Idempotent: CREATE TABLE IF NOT EXISTS; does not erase existing data.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS social_post_comments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id INT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_social_comments_post_created (post_id, created_at),
    KEY idx_social_comments_user (user_id),
    CONSTRAINT fk_social_comments_post
        FOREIGN KEY (post_id) REFERENCES social_posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_social_comments_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

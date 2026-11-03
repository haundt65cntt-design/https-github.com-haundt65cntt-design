-- Import this file into an existing chatting_system database.
-- It adds the personal feed, post reactions, and post comments tables only.
-- Existing chat/forum tables and rows are not dropped or modified.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS social_posts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    image_filename VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_social_posts_created_at (created_at),
    KEY idx_social_posts_user_id (user_id),
    CONSTRAINT fk_social_posts_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_post_reactions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id INT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    reaction_type ENUM('like', 'love', 'laugh', 'angry', 'sad') NOT NULL DEFAULT 'like',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_post_user_reaction (post_id, user_id),
    KEY idx_social_reactions_user (user_id),
    CONSTRAINT fk_social_reactions_post
        FOREIGN KEY (post_id) REFERENCES social_posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_social_reactions_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

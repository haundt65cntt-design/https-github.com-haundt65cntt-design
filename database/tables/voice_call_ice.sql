-- Advanced Chat System: individual table definition
-- MySQL 5.7+ / MariaDB / XAMPP
-- Select the chatting_system database in phpMyAdmin before importing.
-- Idempotent: CREATE TABLE IF NOT EXISTS; does not erase existing data.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS voice_call_ice (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id VARCHAR(64) NOT NULL,
    user_id INT NOT NULL,
    candidate TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_voice_call_ice_room (room_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

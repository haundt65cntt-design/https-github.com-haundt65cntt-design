-- Advanced Chat System: individual table definition
-- MySQL 5.7+ / MariaDB / XAMPP
-- Select the chatting_system database in phpMyAdmin before importing.
-- Idempotent: CREATE TABLE IF NOT EXISTS; does not erase existing data.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS voice_calls (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id VARCHAR(64) UNIQUE NOT NULL,
    caller_id INT NOT NULL,
    callee_id INT NOT NULL,
    status ENUM('pending','accepted','rejected','ended') DEFAULT 'pending',
    offer_sdp TEXT NULL,
    answer_sdp TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (caller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (callee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

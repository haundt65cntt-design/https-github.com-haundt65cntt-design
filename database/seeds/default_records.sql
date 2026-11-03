-- Default records (safe to run repeatedly)
SET NAMES utf8mb4;

INSERT INTO forum_categories (name, description)
SELECT 'General Discussion', 'General topics and discussions'
WHERE NOT EXISTS (SELECT 1 FROM forum_categories WHERE name = 'General Discussion');

INSERT INTO forum_categories (name, description)
SELECT 'Technical Support', 'Technical questions and support'
WHERE NOT EXISTS (SELECT 1 FROM forum_categories WHERE name = 'Technical Support');

INSERT INTO forum_categories (name, description)
SELECT 'Announcements', 'Important announcements and updates'
WHERE NOT EXISTS (SELECT 1 FROM forum_categories WHERE name = 'Announcements');

-- Default administrator account from the upstream repository:
-- username: admin | password: admin123
INSERT IGNORE INTO users (username, password, full_name, status, language)
VALUES ('admin', '$2y$10$gl6laLL2gd14Efe26jkHJuqtvEhSbdim.OP.rCClP/6W2Sl8Uo0cC', 'Administrator', 'offline', 'en');

-- Ensure users.status exists on production databases created before user management.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER timezone;

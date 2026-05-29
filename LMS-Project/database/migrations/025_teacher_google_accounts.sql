-- Dedicated per-teacher Google OAuth token storage for LearnWise.

CREATE TABLE IF NOT EXISTS teacher_google_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT UNSIGNED NOT NULL,
    google_email VARCHAR(255) NULL,
    access_token TEXT NULL,
    refresh_token TEXT NULL,
    token_expiry DATETIME NULL,
    connected_at DATETIME NULL,
    status ENUM('active', 'disconnected', 'error') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tga_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_tga_teacher (teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE class_sessions
    ADD COLUMN IF NOT EXISTS teacher_google_email VARCHAR(255) NULL AFTER google_event_id;

-- Migrate any legacy encrypted refresh tokens from teachers.google_refresh_token.
INSERT INTO teacher_google_accounts (teacher_id, refresh_token, connected_at, status)
SELECT t.user_id, t.google_refresh_token, COALESCE(t.google_connected_at, NOW()), 'active'
FROM teachers t
WHERE t.google_refresh_token IS NOT NULL
  AND TRIM(t.google_refresh_token) <> ''
ON DUPLICATE KEY UPDATE
    refresh_token = COALESCE(teacher_google_accounts.refresh_token, VALUES(refresh_token)),
    connected_at = COALESCE(teacher_google_accounts.connected_at, VALUES(connected_at)),
    status = 'active',
    updated_at = NOW();

-- UAT enhancements: password reset, late join tracking, recurrence, notification settings

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_password_reset_user (user_id),
    INDEX idx_password_reset_token (token_hash),
    INDEX idx_password_reset_expires (expires_at),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE class_sessions
    ADD COLUMN teacher_join_delay_minutes INT NULL AFTER teacher_joined_at,
    ADD COLUMN recurrence_parent_id INT UNSIGNED NULL AFTER description,
    ADD COLUMN recurrence_rule VARCHAR(32) NULL AFTER recurrence_parent_id,
    ADD COLUMN recurrence_end_date DATE NULL AFTER recurrence_rule;

INSERT IGNORE INTO system_config (`key`, `value`, updated_at) VALUES
    ('notify_admin_class_scheduled', '1', NOW()),
    ('notify_admin_reschedule', '1', NOW()),
    ('notify_teacher_student_assigned', '1', NOW()),
    ('admin_notification_email', '', NOW());

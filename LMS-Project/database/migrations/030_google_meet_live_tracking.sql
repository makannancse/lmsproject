ALTER TABLE teacher_google_accounts
    ADD COLUMN IF NOT EXISTS google_person_resource_name VARCHAR(191) NULL AFTER google_email,
    ADD COLUMN IF NOT EXISTS google_person_id VARCHAR(191) NULL AFTER google_person_resource_name;

ALTER TABLE class_sessions
    ADD COLUMN IF NOT EXISTS google_meet_space_name VARCHAR(191) NULL AFTER teacher_google_email,
    ADD COLUMN IF NOT EXISTS google_meeting_code VARCHAR(128) NULL AFTER google_meet_space_name,
    ADD COLUMN IF NOT EXISTS google_conference_id VARCHAR(255) NULL AFTER google_meeting_code,
    ADD COLUMN IF NOT EXISTS meeting_live_status ENUM('pending', 'active', 'ended', 'sync_error') NOT NULL DEFAULT 'pending' AFTER google_conference_id,
    ADD COLUMN IF NOT EXISTS meeting_participant_count INT NULL AFTER meeting_live_status;

CREATE TABLE IF NOT EXISTS meeting_activity_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    google_participant_name VARCHAR(255) NULL,
    google_participant_session_name VARCHAR(255) NULL,
    role ENUM('teacher', 'student', 'unknown') NOT NULL DEFAULT 'unknown',
    joined_at DATETIME NOT NULL,
    left_at DATETIME NULL,
    duration_minutes INT NULL,
    source ENUM('google_meet_api', 'workspace_events', 'manual') NOT NULL DEFAULT 'google_meet_api',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_meeting_activity_logs_class FOREIGN KEY (class_id) REFERENCES class_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_meeting_activity_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_meeting_activity_session (google_participant_session_name),
    KEY idx_meeting_activity_class_role_join (class_id, role, joined_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE class_sessions
SET meeting_live_status = CASE
        WHEN actual_end_time IS NOT NULL OR status = 'completed' THEN 'ended'
        WHEN actual_start_time IS NOT NULL OR teacher_joined_at IS NOT NULL OR status = 'ongoing' THEN 'active'
        ELSE 'pending'
    END
WHERE meeting_live_status IS NULL
   OR meeting_live_status = ''
   OR meeting_live_status = 'pending';

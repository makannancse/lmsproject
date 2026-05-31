ALTER TABLE class_sessions
    ADD COLUMN IF NOT EXISTS actual_duration INT NULL AFTER actual_end_time,
    ADD COLUMN IF NOT EXISTS recording_sync_status ENUM('pending', 'processing', 'ready', 'failed') NOT NULL DEFAULT 'pending' AFTER recording_enabled,
    ADD COLUMN IF NOT EXISTS recording_sync_error TEXT NULL AFTER recording_sync_status,
    ADD COLUMN IF NOT EXISTS recording_synced_at DATETIME NULL AFTER recording_sync_error;

CREATE TABLE IF NOT EXISTS class_attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role ENUM('teacher', 'student') NOT NULL,
    joined_at DATETIME NULL,
    left_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_class_attendance_class FOREIGN KEY (class_id) REFERENCES class_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_class_attendance_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_class_attendance_user (class_id, user_id, role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_recordings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    teacher_id INT UNSIGNED NOT NULL,
    recording_url TEXT NULL,
    recording_file_id VARCHAR(255) NULL,
    recording_title VARCHAR(255) NULL,
    recording_duration INT NULL,
    visible_to_student ENUM('yes', 'no') NOT NULL DEFAULT 'no',
    sync_status ENUM('pending', 'processing', 'ready', 'failed') NOT NULL DEFAULT 'pending',
    source ENUM('google_drive', 'manual') NOT NULL DEFAULT 'google_drive',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_class_recordings_class FOREIGN KEY (class_id) REFERENCES class_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_class_recordings_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_class_recordings_class (class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

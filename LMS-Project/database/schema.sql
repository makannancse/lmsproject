-- Core users table with roles
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
    timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional extended teacher profile
CREATE TABLE IF NOT EXISTS teachers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    employment_type ENUM('full_time', 'part_time') NOT NULL DEFAULT 'part_time',
    hourly_rate DECIMAL(10,2) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_teachers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-teacher Google OAuth account connection
CREATE TABLE IF NOT EXISTS teacher_google_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT UNSIGNED NOT NULL,
    google_email VARCHAR(255) NULL,
    google_person_resource_name VARCHAR(191) NULL,
    google_person_id VARCHAR(191) NULL,
    google_user_id VARCHAR(64) NULL,
    account_type ENUM('workspace', 'personal') NOT NULL DEFAULT 'workspace',
    recording_supported TINYINT(1) NOT NULL DEFAULT 1,
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

-- Optional extended student profile
CREATE TABLE IF NOT EXISTS students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    parent_email VARCHAR(255) NULL,
    country VARCHAR(100) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin-managed class catalog (optional link from class_sessions)
CREATE TABLE IF NOT EXISTS class_master (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Class sessions (live sessions)
CREATE TABLE IF NOT EXISTS class_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT UNSIGNED NOT NULL,
    class_master_id INT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    start_datetime DATETIME NOT NULL,
    scheduled_time_utc DATETIME NULL,
    start_time_utc DATETIME NULL,
    end_datetime DATETIME NOT NULL,
    end_time_utc DATETIME NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
    scheduled_timezone VARCHAR(100) NOT NULL DEFAULT 'UTC',
    status ENUM('scheduled', 'ongoing', 'completed', 'cancelled', 'rescheduled') NOT NULL DEFAULT 'scheduled',
    meeting_link VARCHAR(255) NULL,
    google_event_id VARCHAR(255) NULL,
    teacher_google_email VARCHAR(255) NULL,
    google_meet_space_name VARCHAR(191) NULL,
    google_meeting_code VARCHAR(128) NULL,
    google_conference_id VARCHAR(255) NULL,
    meeting_live_status ENUM('pending', 'active', 'ended', 'sync_error') NOT NULL DEFAULT 'pending',
    meeting_participant_count INT NULL,
    payout_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    zoom_meeting_id VARCHAR(191) NULL,
    zoom_join_url TEXT NULL,
    zoom_start_url TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    actual_start_time DATETIME NULL,
    actual_end_time DATETIME NULL,
    actual_duration INT NULL,
    actual_duration_minutes INT NULL,
    recording_url TEXT NULL,
    recording_enabled TINYINT(1) NOT NULL DEFAULT 0,
    recording_sync_status ENUM('pending', 'processing', 'ready', 'failed') NOT NULL DEFAULT 'pending',
    recording_sync_error TEXT NULL,
    recording_synced_at DATETIME NULL,
    teacher_joined_at DATETIME NULL,
    recording_acknowledged_at DATETIME NULL,
    recording_acknowledged_by INT UNSIGNED NULL,
    student_joined_at DATETIME NULL,
    CONSTRAINT fk_class_sessions_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_class_sessions_class_master FOREIGN KEY (class_master_id) REFERENCES class_master(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Student enrollments for classes
CREATE TABLE IF NOT EXISTS enrollments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    status ENUM('active', 'cancelled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_enrollments_class FOREIGN KEY (class_id) REFERENCES class_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_enrollments_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_enrollment (class_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teacher availability configuration
CREATE TABLE IF NOT EXISTS teacher_availability (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL COMMENT '0=Sunday, 6=Saturday',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_teacher_availability_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teacher payout records
CREATE TABLE IF NOT EXISTS teacher_payouts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending', 'paid') NOT NULL DEFAULT 'pending',
    calculated_at DATETIME NOT NULL,
    paid_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_teacher_payouts_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_teacher_payouts_class FOREIGN KEY (class_id) REFERENCES class_sessions(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_payout_per_class (teacher_id, class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System configuration key/value store
CREATE TABLE IF NOT EXISTS system_config (
    `key` VARCHAR(191) PRIMARY KEY,
    `value` TEXT NULL,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reschedule requests (student or teacher initiated)
CREATE TABLE IF NOT EXISTS reschedule_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    teacher_id INT UNSIGNED NOT NULL,
    requested_by ENUM('student', 'teacher', 'admin') NOT NULL DEFAULT 'student',
    initiated_by ENUM('student', 'teacher', 'admin') NOT NULL DEFAULT 'student',
    requested_date DATE NOT NULL,
    requested_time TIME NOT NULL,
    old_timezone VARCHAR(100) NOT NULL DEFAULT 'UTC',
    new_timezone VARCHAR(100) NOT NULL DEFAULT 'UTC',
    reason TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    teacher_comment TEXT NULL,
    admin_comment TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rr_class FOREIGN KEY (class_id) REFERENCES class_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_rr_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_rr_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teacher-student mapping for access control
CREATE TABLE IF NOT EXISTS teacher_students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ts_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ts_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_teacher_student (teacher_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Homework assignments
CREATE TABLE IF NOT EXISTS homeworks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    due_date DATETIME NULL,
    due_timezone VARCHAR(100) NOT NULL DEFAULT 'UTC',
    status ENUM('pending', 'completed') NOT NULL DEFAULT 'pending',
    completed_at DATETIME NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_homeworks_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_homeworks_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Homework attachments (multiple files per homework)
CREATE TABLE IF NOT EXISTS homework_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    homework_id INT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(512) NOT NULL,
    uploaded_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ha_hw FOREIGN KEY (homework_id) REFERENCES homeworks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Homework -> assigned students (targeted homework, not all enrollments)
CREATE TABLE IF NOT EXISTS homework_assigned_students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    homework_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_has_hw FOREIGN KEY (homework_id) REFERENCES homeworks(id) ON DELETE CASCADE,
    CONSTRAINT fk_has_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_hw_student (homework_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student submissions (multiple files allowed)
CREATE TABLE IF NOT EXISTS homework_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    homework_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(512) NOT NULL,
    submitted_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    uploaded_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hs_hw FOREIGN KEY (homework_id) REFERENCES homeworks(id) ON DELETE CASCADE,
    CONSTRAINT fk_hs_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student report cards submitted by teachers/admin
CREATE TABLE IF NOT EXISTS student_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    teacher_id INT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    student_name VARCHAR(255) NOT NULL,
    teacher_name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    overall_performance VARCHAR(100) NOT NULL,
    concept_understanding VARCHAR(100) NOT NULL,
    application_ability VARCHAR(100) NOT NULL,
    homework_completion VARCHAR(100) NOT NULL,
    attention_level VARCHAR(100) NOT NULL,
    participation_level VARCHAR(100) NOT NULL,
    behaviour VARCHAR(100) NOT NULL,
    subjects_addressed TEXT NOT NULL,
    future_focus TEXT NOT NULL,
    recommended_focus TEXT NOT NULL,
    study_strategies TEXT NOT NULL,
    additional_support TEXT NOT NULL,
    overall_feedback TEXT NOT NULL,
    report_date DATE NOT NULL,
    pdf_path VARCHAR(512) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_student_reports_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_reports_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_student_reports_student_date (student_id, report_date),
    INDEX idx_student_reports_teacher_date (teacher_id, report_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teacher payment summary entries for dashboard actions
CREATE TABLE IF NOT EXISTS teacher_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT UNSIGNED NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    balance_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_status ENUM('pending', 'partial', 'paid', 'advance') NOT NULL DEFAULT 'pending',
    payment_date DATETIME NULL,
    remarks VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_teacher_payments_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Class-level payment logs used to track pending vs paid units
CREATE TABLE IF NOT EXISTS teacher_payment_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending', 'paid') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tpl_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tpl_class FOREIGN KEY (class_id) REFERENCES class_sessions(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_payment_log_class (class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teacher feedback (one row per teacher–student pair)
CREATE TABLE IF NOT EXISTS feedback (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    teacher_id INT UNSIGNED NOT NULL,
    comments TEXT NOT NULL,
    rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fb_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_fb_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_feedback_pair (student_id, teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

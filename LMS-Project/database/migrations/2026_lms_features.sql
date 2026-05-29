-- LMS advanced features migration — run manually in MySQL (phpMyAdmin / CLI).
-- If a column already exists, skip that line or comment it out.

-- 1) Class catalog (must exist before FK on class_sessions)
CREATE TABLE IF NOT EXISTS class_master (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Extend class_sessions (run each ALTER; ignore "Duplicate column" if re-run)
ALTER TABLE class_sessions ADD COLUMN payout_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Teacher payout when class completed' AFTER meeting_link;
ALTER TABLE class_sessions ADD COLUMN class_master_id INT UNSIGNED NULL AFTER teacher_id;
ALTER TABLE class_sessions ADD COLUMN actual_start_time DATETIME NULL AFTER completed_at;
ALTER TABLE class_sessions ADD COLUMN actual_end_time DATETIME NULL AFTER actual_start_time;
ALTER TABLE class_sessions ADD COLUMN recording_url TEXT NULL AFTER actual_end_time;
ALTER TABLE class_sessions ADD COLUMN teacher_joined_at DATETIME NULL AFTER recording_url;
ALTER TABLE class_sessions ADD COLUMN student_joined_at DATETIME NULL AFTER teacher_joined_at;

-- FK to class_master (skip if duplicate constraint name)
ALTER TABLE class_sessions
    ADD CONSTRAINT fk_class_sessions_class_master
    FOREIGN KEY (class_master_id) REFERENCES class_master(id) ON DELETE SET NULL;

-- 3) Reschedule requests
CREATE TABLE IF NOT EXISTS reschedule_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    teacher_id INT UNSIGNED NOT NULL,
    initiated_by ENUM('student', 'teacher', 'admin') NOT NULL DEFAULT 'student',
    requested_date DATE NOT NULL,
    requested_time TIME NOT NULL,
    reason TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    teacher_comment TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rr_class FOREIGN KEY (class_id) REFERENCES class_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_rr_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_rr_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Homework + submissions
CREATE TABLE IF NOT EXISTS homeworks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    due_date DATETIME NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_homeworks_class FOREIGN KEY (class_id) REFERENCES class_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_homeworks_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homework_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    homework_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    file_path VARCHAR(512) NOT NULL,
    original_name VARCHAR(255) NULL,
    uploaded_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hs_class FOREIGN KEY (class_id) REFERENCES class_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_hs_hw FOREIGN KEY (homework_id) REFERENCES homeworks(id) ON DELETE CASCADE,
    CONSTRAINT fk_hs_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_student_homework (homework_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) Feedback (one row per teacher–student pair)
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

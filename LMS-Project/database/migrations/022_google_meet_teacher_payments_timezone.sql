-- Google Meet + INR + recording visibility + timezone standardization

ALTER TABLE class_sessions
    ADD COLUMN IF NOT EXISTS teacher_payout DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER student_fee,
    ADD COLUMN IF NOT EXISTS scheduled_time_utc DATETIME NULL AFTER start_datetime,
    ADD COLUMN IF NOT EXISTS timezone VARCHAR(100) NOT NULL DEFAULT 'UTC' AFTER scheduled_time_utc,
    ADD COLUMN IF NOT EXISTS recording_url TEXT NULL AFTER actual_end_time,
    ADD COLUMN IF NOT EXISTS recording_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER recording_url,
    ADD COLUMN IF NOT EXISTS meeting_link TEXT NULL AFTER timezone,
    ADD COLUMN IF NOT EXISTS google_event_id VARCHAR(255) NULL AFTER meeting_link;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS timezone VARCHAR(100) NOT NULL DEFAULT 'UTC';

ALTER TABLE class_sessions
    MODIFY COLUMN student_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN payout_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00;

CREATE TABLE IF NOT EXISTS student_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'INR',
    status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
    payment_date DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sp_student (student_id),
    INDEX idx_sp_class (class_id),
    CONSTRAINT fk_sp_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_sp_class FOREIGN KEY (class_id) REFERENCES class_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at DATETIME NULL,
    UNIQUE KEY uniq_teacher_class (teacher_id, class_id),
    INDEX idx_tp_teacher (teacher_id),
    INDEX idx_tp_status (status),
    CONSTRAINT fk_tp_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tp_class FOREIGN KEY (class_id) REFERENCES class_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- LMS feature upgrade: INR payments, recording visibility, timezone columns, branding helpers.

ALTER TABLE class_sessions
    ADD COLUMN IF NOT EXISTS student_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER payout_amount,
    ADD COLUMN IF NOT EXISTS recording_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER recording_url,
    ADD COLUMN IF NOT EXISTS scheduled_time_utc DATETIME NULL AFTER start_datetime;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS timezone VARCHAR(100) NOT NULL DEFAULT 'UTC';

CREATE TABLE IF NOT EXISTS student_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'INR',
    status ENUM('pending', 'paid') NOT NULL DEFAULT 'pending',
    payment_date DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student_payments_student (student_id),
    INDEX idx_student_payments_class (class_id),
    INDEX idx_student_payments_status (status),
    CONSTRAINT fk_student_payments_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_payments_class FOREIGN KEY (class_id) REFERENCES class_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


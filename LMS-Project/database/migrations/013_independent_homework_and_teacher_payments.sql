-- Migration: independent homework + teacher payments dashboard tables

-- 1) Homework independent from classes
ALTER TABLE homeworks
    DROP COLUMN IF EXISTS class_id,
    ADD COLUMN IF NOT EXISTS status ENUM('pending','completed') NOT NULL DEFAULT 'pending' AFTER due_date,
    ADD COLUMN IF NOT EXISTS completed_at DATETIME NULL AFTER status;

ALTER TABLE homework_submissions
    DROP COLUMN IF EXISTS class_id,
    DROP COLUMN IF EXISTS original_name;

-- 2) Teacher payment aggregate entries
CREATE TABLE IF NOT EXISTS teacher_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    balance_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_status ENUM('pending','partial','paid','advance') NOT NULL DEFAULT 'pending',
    payment_date DATETIME NULL,
    remarks VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_teacher_payments_teacher (teacher_id),
    INDEX idx_teacher_payments_status (payment_status),
    CONSTRAINT fk_teacher_payments_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Teacher payment class-wise logs
CREATE TABLE IF NOT EXISTS teacher_payment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    class_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payment_logs_teacher (teacher_id),
    INDEX idx_payment_logs_class (class_id),
    INDEX idx_payment_logs_status (status),
    CONSTRAINT fk_payment_logs_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_logs_class FOREIGN KEY (class_id) REFERENCES class_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

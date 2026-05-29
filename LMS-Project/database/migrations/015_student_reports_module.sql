-- Student report card module (exact form fields) + students.parent_email
-- If `student_reports` already existed from an older build, CREATE TABLE IF NOT EXISTS will NOT add new columns.
-- Run `database/migrations/016_student_reports_schema_align.sql` or `php database/run_align_student_reports.php` once.
ALTER TABLE students
    ADD COLUMN IF NOT EXISTS parent_email VARCHAR(255) NULL AFTER user_id;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

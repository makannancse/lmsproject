-- Align legacy `student_reports` with the current app schema.
-- If the table already existed before 015 ran, CREATE TABLE IF NOT EXISTS left it unchanged
-- and columns like `email` were never added — causing "Unknown column 'email'" errors.
-- Safe to run multiple times (ADD COLUMN IF NOT EXISTS).
--
-- Requires MySQL 8.0.12+ or MariaDB 10.3.3+ for IF NOT EXISTS on ADD COLUMN.
-- If your server is older, run the equivalent ALTERs manually in phpMyAdmin (skip columns that already exist).

ALTER TABLE students
    ADD COLUMN IF NOT EXISTS parent_email VARCHAR(255) NULL AFTER country;

ALTER TABLE student_reports
    ADD COLUMN IF NOT EXISTS email VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS student_name VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS teacher_name VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS subject VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS overall_performance VARCHAR(100) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS concept_understanding VARCHAR(100) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS application_ability VARCHAR(100) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS homework_completion VARCHAR(100) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS attention_level VARCHAR(100) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS participation_level VARCHAR(100) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS behaviour VARCHAR(100) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS subjects_addressed TEXT NULL,
    ADD COLUMN IF NOT EXISTS future_focus TEXT NULL,
    ADD COLUMN IF NOT EXISTS recommended_focus TEXT NULL,
    ADD COLUMN IF NOT EXISTS study_strategies TEXT NULL,
    ADD COLUMN IF NOT EXISTS additional_support TEXT NULL,
    ADD COLUMN IF NOT EXISTS overall_feedback TEXT NULL,
    ADD COLUMN IF NOT EXISTS report_date DATE NULL,
    ADD COLUMN IF NOT EXISTS pdf_path VARCHAR(512) NULL,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;

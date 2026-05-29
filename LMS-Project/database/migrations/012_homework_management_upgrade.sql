-- Homework management upgrade for Core PHP LMS.
-- Adds teacher/student mapping, homework attachments, targeted assignments,
-- multi-file submissions, and teacher_id on homeworks.

CREATE TABLE IF NOT EXISTS teacher_students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ts_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ts_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_teacher_student (teacher_id, student_id)
);

ALTER TABLE homeworks
    ADD COLUMN teacher_id INT UNSIGNED NULL AFTER class_id;

ALTER TABLE homeworks
    ADD CONSTRAINT fk_homeworks_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE RESTRICT;

CREATE TABLE IF NOT EXISTS homework_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    homework_id INT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(512) NOT NULL,
    uploaded_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ha_hw FOREIGN KEY (homework_id) REFERENCES homeworks(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS homework_assigned_students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    homework_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_has_hw FOREIGN KEY (homework_id) REFERENCES homeworks(id) ON DELETE CASCADE,
    CONSTRAINT fk_has_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_hw_student (homework_id, student_id)
);

ALTER TABLE homework_submissions
    ADD COLUMN file_name VARCHAR(255) NULL AFTER student_id,
    ADD COLUMN submitted_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER original_name;

-- If previous schema has one-file-per-student unique constraint, drop it manually if present:
-- ALTER TABLE homework_submissions DROP INDEX uniq_student_homework;

-- Backfill teacher_id for existing homeworks from class_sessions.teacher_id
UPDATE homeworks h
INNER JOIN class_sessions cs ON cs.id = h.class_id
SET h.teacher_id = cs.teacher_id
WHERE h.teacher_id IS NULL;

ALTER TABLE homeworks
    MODIFY COLUMN teacher_id INT UNSIGNED NOT NULL;

-- Ensure one row per teacher+student (matches schema.sql). Safe to run once; skip if you already have uniq_teacher_student.
-- If this errors with "Duplicate key name", the index already exists.

ALTER TABLE teacher_students
    ADD UNIQUE KEY uniq_teacher_student (teacher_id, student_id);

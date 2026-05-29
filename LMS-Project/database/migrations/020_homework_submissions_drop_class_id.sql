-- Student submissions are per homework_id + student_id (no class).
-- Legacy homework_submissions.class_id + fk_hs_class breaks INSERT when the app omits class_id.

ALTER TABLE homework_submissions
    DROP FOREIGN KEY fk_hs_class;

ALTER TABLE homework_submissions
    DROP COLUMN class_id;

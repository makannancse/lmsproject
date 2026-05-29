-- Homework is class-independent: inserts use only teacher_id, title, etc.
-- Older DBs still had class_id + fk_homeworks_class → class_sessions, which breaks INSERT when no class is sent.
-- For student upload errors on homework_submissions, also run 020_homework_submissions_drop_class_id.sql
--   (or `php database/run_fix_homeworks_class_fk.php`, which fixes both tables).

ALTER TABLE homeworks
    DROP FOREIGN KEY fk_homeworks_class;

ALTER TABLE homeworks
    DROP COLUMN class_id;

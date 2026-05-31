-- Parent/guardian contact for report cards and family notifications.
-- Student login email remains on `users.email`; guardian email is stored on `students.parent_email`.
-- (If your column already exists from earlier migrations, this is a no-op — run only missing parts manually.)

ALTER TABLE students
    ADD COLUMN IF NOT EXISTS parent_email VARCHAR(255) NULL AFTER user_id;

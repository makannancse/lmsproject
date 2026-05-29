-- Add requested_by/admin_comment columns for full reschedule workflow
ALTER TABLE reschedule_requests
    ADD COLUMN IF NOT EXISTS requested_by ENUM('student','teacher','admin') NOT NULL DEFAULT 'student' AFTER teacher_id,
    ADD COLUMN IF NOT EXISTS admin_comment TEXT NULL AFTER teacher_comment;

-- Backfill requested_by from legacy initiated_by when present
UPDATE reschedule_requests
SET requested_by = initiated_by
WHERE requested_by = 'student' AND initiated_by IN ('teacher','admin');

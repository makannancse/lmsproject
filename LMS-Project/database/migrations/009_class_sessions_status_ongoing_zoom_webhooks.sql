-- Add "ongoing" for Zoom webhooks (meeting.started). Run once; ignore duplicate ENUM errors if already applied.

ALTER TABLE class_sessions
    MODIFY COLUMN status ENUM('scheduled', 'ongoing', 'completed', 'cancelled', 'rescheduled')
    NOT NULL DEFAULT 'scheduled';

-- Legacy table name `classes` (if present in your install):
-- ALTER TABLE classes
--     MODIFY COLUMN status ENUM('scheduled', 'ongoing', 'completed') NOT NULL DEFAULT 'scheduled';
-- ALTER TABLE classes ADD COLUMN zoom_meeting_id VARCHAR(50) NULL;
-- ALTER TABLE classes ADD COLUMN actual_start_time DATETIME NULL;
-- ALTER TABLE classes ADD COLUMN actual_end_time DATETIME NULL;
-- ALTER TABLE classes ADD COLUMN recording_url TEXT NULL;

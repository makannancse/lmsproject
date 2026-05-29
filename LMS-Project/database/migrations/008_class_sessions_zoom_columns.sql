-- Zoom columns for class_sessions (your app stores scheduled classes here, not a generic `classes` table).
-- Safe to run once on older databases. If MySQL reports "Duplicate column", those columns already exist.

ALTER TABLE class_sessions ADD COLUMN zoom_meeting_id VARCHAR(191) NULL;
ALTER TABLE class_sessions ADD COLUMN zoom_join_url TEXT NULL;
ALTER TABLE class_sessions ADD COLUMN zoom_start_url TEXT NULL;

-- If you maintain a legacy table literally named `classes`, add similar columns there:
-- ALTER TABLE classes ADD COLUMN zoom_meeting_id VARCHAR(50) NULL;
-- ALTER TABLE classes ADD COLUMN zoom_join_url TEXT NULL;
-- ALTER TABLE classes ADD COLUMN zoom_start_url TEXT NULL;

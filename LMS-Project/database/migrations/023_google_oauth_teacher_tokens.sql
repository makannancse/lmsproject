-- Google OAuth2 per-teacher token storage and Google event tracking.

ALTER TABLE teachers
    ADD COLUMN IF NOT EXISTS google_refresh_token TEXT NULL AFTER notes,
    ADD COLUMN IF NOT EXISTS google_connected_at DATETIME NULL AFTER google_refresh_token;

ALTER TABLE class_sessions
    ADD COLUMN IF NOT EXISTS google_event_id VARCHAR(255) NULL AFTER meeting_link;


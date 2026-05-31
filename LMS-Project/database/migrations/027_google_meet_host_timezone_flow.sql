-- Teacher-hosted Google Meet flow, UTC scheduling columns, and recording acknowledgement metadata.

ALTER TABLE class_sessions
    ADD COLUMN IF NOT EXISTS start_time_utc DATETIME NULL AFTER scheduled_time_utc,
    ADD COLUMN IF NOT EXISTS end_time_utc DATETIME NULL AFTER end_datetime,
    ADD COLUMN IF NOT EXISTS scheduled_timezone VARCHAR(100) NOT NULL DEFAULT 'UTC' AFTER timezone,
    ADD COLUMN IF NOT EXISTS recording_acknowledged_at DATETIME NULL AFTER teacher_joined_at,
    ADD COLUMN IF NOT EXISTS recording_acknowledged_by INT UNSIGNED NULL AFTER recording_acknowledged_at;

UPDATE class_sessions
SET scheduled_time_utc = COALESCE(scheduled_time_utc, start_datetime),
    start_time_utc = COALESCE(start_time_utc, start_datetime, scheduled_time_utc),
    end_time_utc = COALESCE(end_time_utc, end_datetime),
    scheduled_timezone = CASE
        WHEN scheduled_timezone IS NULL OR TRIM(scheduled_timezone) = ''
            THEN COALESCE(NULLIF(timezone, ''), 'UTC')
        ELSE scheduled_timezone
    END;

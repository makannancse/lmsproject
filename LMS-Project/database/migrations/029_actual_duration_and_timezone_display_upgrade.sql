-- Actual duration minutes and stable timezone metadata for reschedules and homework due dates.

ALTER TABLE class_sessions
    ADD COLUMN IF NOT EXISTS actual_duration_minutes INT NULL AFTER actual_duration;

UPDATE class_sessions
SET actual_duration_minutes = COALESCE(
        actual_duration_minutes,
        actual_duration,
        CASE
            WHEN actual_start_time IS NOT NULL AND actual_end_time IS NOT NULL
                THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, actual_start_time, actual_end_time))
            ELSE NULL
        END
    ),
    actual_duration = COALESCE(
        actual_duration,
        actual_duration_minutes,
        CASE
            WHEN actual_start_time IS NOT NULL AND actual_end_time IS NOT NULL
                THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, actual_start_time, actual_end_time))
            ELSE NULL
        END
    );

ALTER TABLE reschedule_requests
    ADD COLUMN IF NOT EXISTS old_timezone VARCHAR(100) NOT NULL DEFAULT 'UTC' AFTER requested_time,
    ADD COLUMN IF NOT EXISTS new_timezone VARCHAR(100) NOT NULL DEFAULT 'UTC' AFTER old_timezone;

UPDATE reschedule_requests rr
INNER JOIN class_sessions cs ON cs.id = rr.class_id
SET rr.old_timezone = CASE
        WHEN rr.old_timezone IS NULL OR TRIM(rr.old_timezone) = ''
            THEN COALESCE(NULLIF(cs.scheduled_timezone, ''), NULLIF(cs.timezone, ''), 'UTC')
        ELSE rr.old_timezone
    END,
    rr.new_timezone = CASE
        WHEN rr.new_timezone IS NULL OR TRIM(rr.new_timezone) = ''
            THEN COALESCE(NULLIF(rr.old_timezone, ''), NULLIF(cs.scheduled_timezone, ''), NULLIF(cs.timezone, ''), 'UTC')
        ELSE rr.new_timezone
    END;

ALTER TABLE homeworks
    ADD COLUMN IF NOT EXISTS due_timezone VARCHAR(100) NOT NULL DEFAULT 'UTC' AFTER due_date;

UPDATE homeworks h
INNER JOIN users u ON u.id = h.teacher_id
SET h.due_timezone = CASE
        WHEN h.due_timezone IS NULL OR TRIM(h.due_timezone) = ''
            THEN COALESCE(NULLIF(u.timezone, ''), 'UTC')
        ELSE h.due_timezone
    END;

-- This LMS stores classes in `class_sessions` (not a minimal `classes` table).
-- Zoom webhooks update status / times:
--   meeting.started  → status = 'ongoing', actual_start_time
--   meeting.ended    → status = 'completed', actual_end_time, completed_at
-- The FullCalendar UI reads these columns via GET /calendar/events or get_classes.php.

-- Example equivalent to "UPDATE classes SET status='completed', actual_end_time=NOW() WHERE zoom_meeting_id=?":
-- UPDATE class_sessions
-- SET status = 'completed', actual_end_time = UTC_TIMESTAMP(), completed_at = UTC_TIMESTAMP()
-- WHERE TRIM(zoom_meeting_id) = :zoom_meeting_id AND status != 'cancelled';

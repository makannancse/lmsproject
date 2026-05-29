-- Remove dummy recording URLs so dashboards show "No recording available yet" until Zoom sends a real URL.
-- Real URLs are set by zoom_webhook.php on event recording.completed (Cloud Recording must be enabled in Zoom).

UPDATE class_sessions
SET recording_url = NULL
WHERE recording_url LIKE '%example.com/recording-placeholder%'
   OR recording_url LIKE '%/recording-placeholder%';

-- Equivalent if your table is named `classes`:
-- UPDATE classes SET recording_url = NULL WHERE recording_url LIKE '%example.com/recording-placeholder%';

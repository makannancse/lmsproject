-- Remove legacy Zoom columns and keep generic meeting_link only.

ALTER TABLE class_sessions
    DROP COLUMN IF EXISTS zoom_meeting_id,
    DROP COLUMN IF EXISTS zoom_join_url,
    DROP COLUMN IF EXISTS zoom_start_url;

-- Optional cleanup of obsolete system config keys.
DELETE FROM system_config WHERE `key` IN ('zoom_api_key', 'static_zoom_meeting_link');


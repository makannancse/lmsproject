-- Workspace vs personal Google account (Meet works for both; Drive recording sync only for Workspace-type accounts).
ALTER TABLE teacher_google_accounts
    ADD COLUMN account_type ENUM('workspace', 'personal') NOT NULL DEFAULT 'workspace'
        AFTER google_email,
    ADD COLUMN recording_supported TINYINT(1) NOT NULL DEFAULT 1
        AFTER account_type;

UPDATE teacher_google_accounts
SET account_type = 'personal',
    recording_supported = 0
WHERE google_email IS NOT NULL
  AND (
    LOWER(TRIM(google_email)) LIKE '%@gmail.com'
    OR LOWER(TRIM(google_email)) LIKE '%@googlemail.com'
);

UPDATE teacher_google_accounts
SET account_type = 'workspace',
    recording_supported = 1
WHERE google_email IS NULL
   OR NOT (
    LOWER(TRIM(google_email)) LIKE '%@gmail.com'
    OR LOWER(TRIM(google_email)) LIKE '%@googlemail.com'
);

-- Meet Conference Records API identifies signed-in users as users/{id} (OAuth sub),
-- not people/{person_id} from the People API.
ALTER TABLE teacher_google_accounts
    ADD COLUMN IF NOT EXISTS google_user_id VARCHAR(64) NULL AFTER google_person_id;

UPDATE teacher_google_accounts
SET google_user_id = google_person_id
WHERE google_user_id IS NULL
  AND google_person_id IS NOT NULL
  AND google_person_id REGEXP '^[0-9]+$';

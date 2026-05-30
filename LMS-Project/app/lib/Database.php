<?php

declare(strict_types=1);

class Database
{
    private static ?\PDO $pdo = null;
    private static bool $runtimeSchemaChecked = false;

    public static function connection(): \PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                env('DB_HOST', '127.0.0.1'),
                env('DB_PORT', '3306'),
                env('DB_DATABASE', 'lms_db')
            );

            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];

            self::$pdo = new \PDO(
                $dsn,
                env('DB_USERNAME', 'root'),
                env('DB_PASSWORD', ''),
                $options
            );
        }

        self::ensureRuntimeSchemaCompatibility(self::$pdo);

        return self::$pdo;
    }

    private static function ensureRuntimeSchemaCompatibility(\PDO $pdo): void
    {
        if (self::$runtimeSchemaChecked) {
            return;
        }

        self::$runtimeSchemaChecked = true;

        $databaseName = (string) env('DB_DATABASE', 'lms_db');
        if ($databaseName === '') {
            return;
        }

        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'class_sessions',
            'scheduled_time_utc',
            'ALTER TABLE class_sessions
             ADD COLUMN scheduled_time_utc DATETIME NULL AFTER start_datetime'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'class_sessions',
            'start_time_utc',
            'ALTER TABLE class_sessions
             ADD COLUMN start_time_utc DATETIME NULL AFTER scheduled_time_utc'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'class_sessions',
            'end_time_utc',
            'ALTER TABLE class_sessions
             ADD COLUMN end_time_utc DATETIME NULL AFTER end_datetime'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'class_sessions',
            'scheduled_timezone',
            'ALTER TABLE class_sessions
             ADD COLUMN scheduled_timezone VARCHAR(100) NOT NULL DEFAULT "UTC" AFTER timezone'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'class_sessions',
            'recording_enabled',
            'ALTER TABLE class_sessions
             ADD COLUMN recording_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER recording_url'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'class_sessions',
            'teacher_google_email',
            'ALTER TABLE class_sessions
             ADD COLUMN teacher_google_email VARCHAR(255) NULL AFTER google_event_id'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'class_sessions',
            'google_meet_space_name',
            'ALTER TABLE class_sessions
             ADD COLUMN google_meet_space_name VARCHAR(191) NULL AFTER teacher_google_email'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'class_sessions',
            'google_meeting_code',
            'ALTER TABLE class_sessions
             ADD COLUMN google_meeting_code VARCHAR(128) NULL AFTER google_meet_space_name'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'class_sessions',
            'google_conference_id',
            'ALTER TABLE class_sessions
             ADD COLUMN google_conference_id VARCHAR(255) NULL AFTER google_meeting_code'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'class_sessions',
            'meeting_live_status',
            'ALTER TABLE class_sessions
             ADD COLUMN meeting_live_status ENUM("pending","active","ended","sync_error") NOT NULL DEFAULT "pending" AFTER google_conference_id'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'class_sessions',
            'meeting_participant_count',
            'ALTER TABLE class_sessions
             ADD COLUMN meeting_participant_count INT NULL AFTER meeting_live_status'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'class_sessions',
            'actual_duration',
            'ALTER TABLE class_sessions
             ADD COLUMN actual_duration INT NULL AFTER actual_end_time'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'class_sessions',
            'actual_duration_minutes',
            'ALTER TABLE class_sessions
             ADD COLUMN actual_duration_minutes INT NULL AFTER actual_duration'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'class_sessions',
            'recording_acknowledged_at',
            'ALTER TABLE class_sessions
             ADD COLUMN recording_acknowledged_at DATETIME NULL AFTER teacher_joined_at'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'class_sessions',
            'recording_acknowledged_by',
            'ALTER TABLE class_sessions
             ADD COLUMN recording_acknowledged_by INT UNSIGNED NULL AFTER recording_acknowledged_at'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'class_sessions',
            'recording_sync_status',
            'ALTER TABLE class_sessions
             ADD COLUMN recording_sync_status ENUM("pending","processing","ready","failed") NOT NULL DEFAULT "pending" AFTER recording_enabled'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'class_sessions',
            'teacher_joined_at',
            'ALTER TABLE class_sessions
             ADD COLUMN teacher_joined_at DATETIME NULL AFTER recording_synced_at'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'class_sessions',
            'student_joined_at',
            'ALTER TABLE class_sessions
             ADD COLUMN student_joined_at DATETIME NULL AFTER teacher_joined_at'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'class_sessions',
            'recording_sync_error',
            'ALTER TABLE class_sessions
             ADD COLUMN recording_sync_error TEXT NULL AFTER recording_sync_status'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'class_sessions',
            'recording_synced_at',
            'ALTER TABLE class_sessions
             ADD COLUMN recording_synced_at DATETIME NULL AFTER recording_sync_error'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'reschedule_requests',
            'old_timezone',
            'ALTER TABLE reschedule_requests
             ADD COLUMN old_timezone VARCHAR(100) NOT NULL DEFAULT "UTC" AFTER requested_time'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'reschedule_requests',
            'new_timezone',
            'ALTER TABLE reschedule_requests
             ADD COLUMN new_timezone VARCHAR(100) NOT NULL DEFAULT "UTC" AFTER old_timezone'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'homeworks',
            'due_timezone',
            'ALTER TABLE homeworks
             ADD COLUMN due_timezone VARCHAR(100) NOT NULL DEFAULT "UTC" AFTER due_date'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'teacher_google_accounts',
            'google_person_resource_name',
            'ALTER TABLE teacher_google_accounts
             ADD COLUMN google_person_resource_name VARCHAR(191) NULL AFTER google_email'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'teacher_google_accounts',
            'google_person_id',
            'ALTER TABLE teacher_google_accounts
             ADD COLUMN google_person_id VARCHAR(191) NULL AFTER google_person_resource_name'
        );
        self::ensureColumnExists(
            $pdo,
            $databaseName,
            'teacher_google_accounts',
            'google_user_id',
            'ALTER TABLE teacher_google_accounts
             ADD COLUMN google_user_id VARCHAR(64) NULL AFTER google_person_id'
        );

        $pdo->exec(
            'UPDATE class_sessions
             SET scheduled_time_utc = COALESCE(scheduled_time_utc, start_datetime),
                 start_time_utc = COALESCE(start_time_utc, start_datetime, scheduled_time_utc),
                 end_time_utc = COALESCE(end_time_utc, end_datetime),
                 actual_duration_minutes = COALESCE(
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
                 ),
                 scheduled_timezone = CASE
                     WHEN scheduled_timezone IS NULL OR TRIM(scheduled_timezone) = ""
                         THEN COALESCE(NULLIF(timezone, ""), "UTC")
                     ELSE scheduled_timezone
                 END,
                 meeting_live_status = CASE
                     WHEN actual_end_time IS NOT NULL OR status = "completed" THEN "ended"
                     WHEN actual_start_time IS NOT NULL OR teacher_joined_at IS NOT NULL OR status = "ongoing" THEN "active"
                     WHEN meeting_live_status IS NULL OR TRIM(meeting_live_status) = "" THEN "pending"
                     ELSE meeting_live_status
                 END'
        );
        $pdo->exec(
            'UPDATE reschedule_requests rr
             INNER JOIN class_sessions cs ON cs.id = rr.class_id
             SET rr.old_timezone = CASE
                     WHEN rr.old_timezone IS NULL OR TRIM(rr.old_timezone) = ""
                         THEN COALESCE(NULLIF(cs.scheduled_timezone, ""), NULLIF(cs.timezone, ""), "UTC")
                     ELSE rr.old_timezone
                 END,
                 rr.new_timezone = CASE
                     WHEN rr.new_timezone IS NULL OR TRIM(rr.new_timezone) = ""
                         THEN COALESCE(NULLIF(rr.old_timezone, ""), NULLIF(cs.scheduled_timezone, ""), NULLIF(cs.timezone, ""), "UTC")
                     ELSE rr.new_timezone
                 END'
        );
        $pdo->exec(
            'UPDATE homeworks h
             INNER JOIN users u ON u.id = h.teacher_id
             SET h.due_timezone = CASE
                     WHEN h.due_timezone IS NULL OR TRIM(h.due_timezone) = ""
                         THEN COALESCE(NULLIF(u.timezone, ""), "UTC")
                     ELSE h.due_timezone
                 END'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS class_attendance (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                class_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                role ENUM("teacher","student") NOT NULL,
                joined_at DATETIME NULL,
                left_at DATETIME NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_class_attendance_class FOREIGN KEY (class_id) REFERENCES class_sessions(id) ON DELETE CASCADE,
                CONSTRAINT fk_class_attendance_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY uniq_class_attendance_user (class_id, user_id, role)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS class_recordings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                class_id INT UNSIGNED NOT NULL,
                teacher_id INT UNSIGNED NOT NULL,
                recording_url TEXT NULL,
                recording_file_id VARCHAR(255) NULL,
                recording_title VARCHAR(255) NULL,
                recording_duration INT NULL,
                visible_to_student ENUM("yes","no") NOT NULL DEFAULT "no",
                sync_status ENUM("pending","processing","ready","failed") NOT NULL DEFAULT "pending",
                source ENUM("google_drive","manual") NOT NULL DEFAULT "google_drive",
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_class_recordings_class FOREIGN KEY (class_id) REFERENCES class_sessions(id) ON DELETE CASCADE,
                CONSTRAINT fk_class_recordings_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY uniq_class_recordings_class (class_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS meeting_activity_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                class_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NULL,
                google_participant_name VARCHAR(255) NULL,
                google_participant_session_name VARCHAR(255) NULL,
                role ENUM("teacher","student","unknown") NOT NULL DEFAULT "unknown",
                joined_at DATETIME NOT NULL,
                left_at DATETIME NULL,
                duration_minutes INT NULL,
                source ENUM("google_meet_api","workspace_events","manual") NOT NULL DEFAULT "google_meet_api",
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_meeting_activity_logs_class FOREIGN KEY (class_id) REFERENCES class_sessions(id) ON DELETE CASCADE,
                CONSTRAINT fk_meeting_activity_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                UNIQUE KEY uniq_meeting_activity_session (google_participant_session_name),
                KEY idx_meeting_activity_class_role_join (class_id, role, joined_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private static function ensureColumnExists(
        \PDO $pdo,
        string $databaseName,
        string $tableName,
        string $columnName,
        string $alterSql
    ): void {
        $columnExists = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :schema
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name'
        );
        $columnExists->execute([
            'schema' => $databaseName,
            'table_name' => $tableName,
            'column_name' => $columnName,
        ]);

        if ((int) $columnExists->fetchColumn() > 0) {
            return;
        }

        $pdo->exec($alterSql);
    }
}

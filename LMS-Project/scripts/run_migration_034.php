<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/lib/Database.php';

$pdo = Database::connection();

function migration034ColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column LIMIT 1'
    );
    $stmt->execute(['table' => $table, 'column' => $column]);

    return (bool) $stmt->fetchColumn();
}

function migration034TableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1'
    );
    $stmt->execute(['table' => $table]);

    return (bool) $stmt->fetchColumn();
}

function migration034AddColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    if (migration034ColumnExists($pdo, $table, $column)) {
        echo "SKIP: {$table}.{$column}\n";
        return;
    }
    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    echo "OK: {$table}.{$column}\n";
}

if (!migration034TableExists($pdo, 'recurring_series')) {
    $pdo->exec(
        'CREATE TABLE recurring_series (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT UNSIGNED NOT NULL,
            class_master_id INT UNSIGNED NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            subject VARCHAR(255) NULL,
            meeting_link VARCHAR(512) NULL,
            google_event_id VARCHAR(255) NULL,
            google_meet_space_name VARCHAR(191) NULL,
            google_meeting_code VARCHAR(128) NULL,
            teacher_google_email VARCHAR(255) NULL,
            start_date DATE NOT NULL,
            end_date DATE NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            timezone VARCHAR(64) NOT NULL DEFAULT "UTC",
            scheduled_timezone VARCHAR(64) NOT NULL DEFAULT "UTC",
            frequency ENUM("daily", "weekly", "monthly") NOT NULL,
            recurrence_end_date DATE NULL,
            occurrence_count INT UNSIGNED NULL,
            teacher_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            student_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status ENUM("active", "cancelled", "completed") NOT NULL DEFAULT "active",
            recording_enabled TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_recurring_series_teacher (teacher_id),
            INDEX idx_recurring_series_dates (start_date, end_date),
            CONSTRAINT fk_recurring_series_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    echo "OK: recurring_series\n";
} else {
    echo "SKIP: recurring_series\n";
}

if (!migration034TableExists($pdo, 'recurring_series_students')) {
    $pdo->exec(
        'CREATE TABLE recurring_series_students (
            series_id INT UNSIGNED NOT NULL,
            student_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (series_id, student_id),
            CONSTRAINT fk_rss_series FOREIGN KEY (series_id) REFERENCES recurring_series(id) ON DELETE CASCADE,
            CONSTRAINT fk_rss_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    echo "OK: recurring_series_students\n";
} else {
    echo "SKIP: recurring_series_students\n";
}

if (!migration034TableExists($pdo, 'recurring_occurrences')) {
    $pdo->exec(
        'CREATE TABLE recurring_occurrences (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            series_id INT UNSIGNED NOT NULL,
            occurrence_date DATE NOT NULL,
            scheduled_start_utc DATETIME NOT NULL,
            scheduled_end_utc DATETIME NOT NULL,
            actual_start_utc DATETIME NULL,
            actual_end_utc DATETIME NULL,
            duration_minutes INT NULL,
            status ENUM("scheduled", "ongoing", "completed", "cancelled", "missed", "rescheduled") NOT NULL DEFAULT "scheduled",
            teacher_payment DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            class_session_id INT UNSIGNED NULL,
            google_conference_id VARCHAR(255) NULL,
            teacher_joined_at DATETIME NULL,
            teacher_join_delay_minutes INT NULL,
            student_joined_at DATETIME NULL,
            meeting_live_status ENUM("pending", "active", "ended", "sync_error") NOT NULL DEFAULT "pending",
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_series_occurrence_date (series_id, occurrence_date),
            INDEX idx_recurring_occurrences_series (series_id),
            INDEX idx_recurring_occurrences_start (scheduled_start_utc),
            INDEX idx_recurring_occurrences_status (status),
            CONSTRAINT fk_recurring_occurrences_series FOREIGN KEY (series_id) REFERENCES recurring_series(id) ON DELETE CASCADE,
            CONSTRAINT fk_recurring_occurrences_class FOREIGN KEY (class_session_id) REFERENCES class_sessions(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    echo "OK: recurring_occurrences\n";
} else {
    echo "SKIP: recurring_occurrences\n";
}

migration034AddColumn($pdo, 'class_sessions', 'recurring_series_id', 'INT UNSIGNED NULL');
migration034AddColumn($pdo, 'class_sessions', 'recurring_occurrence_id', 'INT UNSIGNED NULL AFTER recurring_series_id');
migration034AddColumn($pdo, 'student_payments', 'recurring_series_id', 'INT UNSIGNED NULL AFTER class_id');
migration034AddColumn($pdo, 'teacher_payouts', 'recurring_occurrence_id', 'INT UNSIGNED NULL AFTER class_id');

echo "Migration 034 complete.\n";

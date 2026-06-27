<?php



declare(strict_types=1);



/**

 * Run migration 033 (idempotent on MySQL/MariaDB without ADD COLUMN IF NOT EXISTS).

 */

require_once __DIR__ . '/../app/config/config.php';

require_once __DIR__ . '/../app/lib/Database.php';



$pdo = Database::connection();



function migration033ColumnExists(PDO $pdo, string $table, string $column): bool

{

    $stmt = $pdo->prepare(

        'SELECT 1 FROM information_schema.COLUMNS

         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column

         LIMIT 1'

    );

    $stmt->execute(['table' => $table, 'column' => $column]);



    return (bool) $stmt->fetchColumn();

}



function migration033AddColumn(PDO $pdo, string $table, string $column, string $definition): void

{

    if (migration033ColumnExists($pdo, $table, $column)) {

        echo "SKIP: {$table}.{$column} already exists\n";

        return;

    }

    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");

    echo "OK: added {$table}.{$column}\n";

}



try {

    $pdo->exec(

        'CREATE TABLE IF NOT EXISTS password_reset_tokens (

            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            user_id INT UNSIGNED NOT NULL,

            token_hash VARCHAR(64) NOT NULL,

            expires_at DATETIME NOT NULL,

            used_at DATETIME NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_password_reset_user (user_id),

            INDEX idx_password_reset_token (token_hash),

            INDEX idx_password_reset_expires (expires_at),

            CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'

    );

    echo "OK: password_reset_tokens\n";

} catch (\Throwable $e) {

    echo 'WARN: password_reset_tokens - ' . $e->getMessage() . "\n";

}



migration033AddColumn($pdo, 'class_sessions', 'teacher_join_delay_minutes', 'INT NULL AFTER teacher_joined_at');

migration033AddColumn($pdo, 'class_sessions', 'recurrence_parent_id', 'INT UNSIGNED NULL AFTER description');

migration033AddColumn($pdo, 'class_sessions', 'recurrence_rule', 'VARCHAR(32) NULL AFTER recurrence_parent_id');

migration033AddColumn($pdo, 'class_sessions', 'recurrence_end_date', 'DATE NULL AFTER recurrence_rule');



try {

    $pdo->exec(

        "INSERT IGNORE INTO system_config (`key`, `value`, updated_at) VALUES

            ('notify_admin_class_scheduled', '1', NOW()),

            ('notify_admin_reschedule', '1', NOW()),

            ('notify_teacher_student_assigned', '1', NOW()),

            ('admin_notification_email', '', NOW())"

    );

    echo "OK: system_config notification keys\n";

} catch (\Throwable $e) {

    echo 'WARN: system_config - ' . $e->getMessage() . "\n";

}



echo "Migration 033 complete.\n";


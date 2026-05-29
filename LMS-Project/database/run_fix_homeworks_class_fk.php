<?php

/**
 * One-time fix: remove legacy class_id + FKs to class_sessions on homeworks / homework_submissions.
 * The app does not send class_id (class-independent homework).
 * Run: php database/run_fix_homeworks_class_fk.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/lib/Database.php';

$pdo = Database::connection();
$db = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

$dropFkIfExists = static function (\PDO $pdo, string $dbName, string $table, string $constraintName): void {
    $stmt = $pdo->prepare(
        'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl AND CONSTRAINT_TYPE = :ctype
           AND CONSTRAINT_NAME = :cname'
    );
    $stmt->execute([
        'db' => $dbName,
        'tbl' => $table,
        'ctype' => 'FOREIGN KEY',
        'cname' => $constraintName,
    ]);
    if ($stmt->fetchColumn()) {
        $pdo->exec('ALTER TABLE `' . str_replace('`', '``', $table) . '` DROP FOREIGN KEY `' . str_replace('`', '``', $constraintName) . '`');
        echo "Dropped FK {$table}.{$constraintName}\n";
    } else {
        echo "FK {$constraintName} not on {$table} (skip).\n";
    }
};

$dropColumnIfExists = static function (\PDO $pdo, string $dbName, string $table, string $column): void {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl AND COLUMN_NAME = :col'
    );
    $stmt->execute(['db' => $dbName, 'tbl' => $table, 'col' => $column]);
    if ((int) $stmt->fetchColumn() > 0) {
        $pdo->exec('ALTER TABLE `' . str_replace('`', '``', $table) . '` DROP COLUMN `' . str_replace('`', '``', $column) . '`');
        echo "Dropped column {$table}.{$column}\n";
    } else {
        echo "Column {$table}.{$column} not present (skip).\n";
    }
};

// --- homeworks ---
$dropFkIfExists($pdo, $db, 'homeworks', 'fk_homeworks_class');
$dropColumnIfExists($pdo, $db, 'homeworks', 'class_id');

// --- homework_submissions (student upload INSERT omits class_id) ---
$dropFkIfExists($pdo, $db, 'homework_submissions', 'fk_hs_class');
$dropColumnIfExists($pdo, $db, 'homework_submissions', 'class_id');

echo "Done.\n";

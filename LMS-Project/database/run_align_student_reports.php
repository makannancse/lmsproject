<?php

/**
 * One-time align: add missing student_reports / students columns for the report card module.
 * Run from project root: php database/run_align_student_reports.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/lib/Database.php';

$pdo = Database::connection();

$columnExists = static function (\PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
    );
    $stmt->execute(['t' => $table, 'c' => $column]);

    return (int) $stmt->fetchColumn() > 0;
};

$tableExists = static function (\PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
    );
    $stmt->execute(['t' => $table]);

    return (int) $stmt->fetchColumn() > 0;
};

$addColumn = static function (\PDO $pdo, string $table, string $column, string $definition) use ($columnExists): void {
    if ($columnExists($pdo, $table, $column)) {
        echo "Skip {$table}.{$column} (exists)\n";

        return;
    }
    $sql = 'ALTER TABLE `' . str_replace('`', '``', $table) . '` ADD COLUMN `' . str_replace('`', '``', $column) . '` ' . $definition;
    $pdo->exec($sql);
    echo "Added {$table}.{$column}\n";
};

if ($tableExists($pdo, 'students')) {
    $addColumn($pdo, 'students', 'parent_email', 'VARCHAR(255) NULL');
}

if (!$tableExists($pdo, 'student_reports')) {
    echo "Table student_reports does not exist — run database/migrations/015_student_reports_module.sql first.\n";
    exit(1);
}

$reportCols = [
    'email' => "VARCHAR(255) NOT NULL DEFAULT ''",
    'student_name' => "VARCHAR(255) NOT NULL DEFAULT ''",
    'teacher_name' => "VARCHAR(255) NOT NULL DEFAULT ''",
    'subject' => "VARCHAR(255) NOT NULL DEFAULT ''",
    'overall_performance' => "VARCHAR(100) NOT NULL DEFAULT ''",
    'concept_understanding' => "VARCHAR(100) NOT NULL DEFAULT ''",
    'application_ability' => "VARCHAR(100) NOT NULL DEFAULT ''",
    'homework_completion' => "VARCHAR(100) NOT NULL DEFAULT ''",
    'attention_level' => "VARCHAR(100) NOT NULL DEFAULT ''",
    'participation_level' => "VARCHAR(100) NOT NULL DEFAULT ''",
    'behaviour' => "VARCHAR(100) NOT NULL DEFAULT ''",
    'subjects_addressed' => 'TEXT NULL',
    'future_focus' => 'TEXT NULL',
    'recommended_focus' => 'TEXT NULL',
    'study_strategies' => 'TEXT NULL',
    'additional_support' => 'TEXT NULL',
    'overall_feedback' => 'TEXT NULL',
    'report_date' => 'DATE NULL',
    'pdf_path' => 'VARCHAR(512) NULL',
    'created_at' => 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP',
];

foreach ($reportCols as $col => $def) {
    $addColumn($pdo, 'student_reports', $col, $def);
}

echo "Done.\n";

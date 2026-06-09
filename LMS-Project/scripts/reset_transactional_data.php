<?php

declare(strict_types=1);

/**
 * CLI: backup full DB, then truncate transactional tables.
 *
 * Usage (WAMP PHP 8.3):
 *   c:\wamp64\bin\php\php8.3.28\php.exe scripts\reset_transactional_data.php
 *   c:\wamp64\bin\php\php8.3.28\php.exe scripts\reset_transactional_data.php --dry-run
 */

$root = dirname(__DIR__);
require_once $root . '/app/config/config.php';
require_once $root . '/app/lib/DatabaseResetService.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);

echo "LearnWise — transactional data reset\n";
echo $dryRun ? "Mode: DRY RUN (no writes)\n\n" : "Mode: LIVE\n\n";

try {
    $result = DatabaseResetService::run($dryRun);
} catch (\Throwable $e) {
    fwrite(STDERR, "Reset failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "Backup location:\n  " . $result['backup_path'] . "\n\n";

echo "Tables cleaned (" . count($result['tables_cleaned']) . "):\n";
foreach ($result['tables_cleaned'] as $table) {
    echo "  - {$table}\n";
}

if ($result['log_files_cleared'] !== []) {
    echo "\nLog files cleared:\n";
    foreach ($result['log_files_cleared'] as $log) {
        echo "  - {$log}\n";
    }
}

echo "\nSQL executed:\n";
foreach ($result['sql_executed'] as $line) {
    echo '  ' . $line . "\n";
}

echo "\nKept: users, teachers, students, teacher_students, class_master, system_config, teacher_google_accounts, teacher_availability.\n";
echo $dryRun ? "\nRe-run without --dry-run to apply.\n" : "\nDone. Verify the app loads at your BASE_PATH URL.\n";

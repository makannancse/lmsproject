<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';

/**
 * Full DB backup + wipe transactional tables for fresh testing.
 */
class DatabaseResetService
{
    /** Child tables first (FK-safe truncate order). */
    private const TRANSACTIONAL_TABLES = [
        'homework_submissions',
        'homework_attachments',
        'homework_assigned_students',
        'homeworks',
        'meeting_activity_logs',
        'class_attendance',
        'class_recordings',
        'enrollments',
        'student_payments',
        'teacher_payment_logs',
        'teacher_payouts',
        'teacher_payments',
        'reschedule_requests',
        'feedback',
        'student_reports',
        'class_sessions',
    ];

    /**
     * @return array{
     *   backup_path: string,
     *   tables_cleaned: list<string>,
     *   sql_executed: list<string>,
     *   log_files_cleared: list<string>
     * }
     */
    public static function run(bool $dryRun = false): array
    {
        $pdo = Database::connection();
        $root = dirname(__DIR__, 2);
        $backupDir = $root . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($backupDir) && !$dryRun) {
            mkdir($backupDir, 0755, true);
        }

        $date = date('Ymd');
        $backupPath = $backupDir . DIRECTORY_SEPARATOR . 'pre_reset_' . $date . '.sql';
        if (is_file($backupPath) && !$dryRun) {
            $backupPath = $backupDir . DIRECTORY_SEPARATOR . 'pre_reset_' . $date . '_' . date('His') . '.sql';
        }

        $sqlExecuted = [];
        $tablesCleaned = [];

        if (!$dryRun) {
            self::backupDatabase($pdo, $backupPath);
            $sqlExecuted[] = '-- Full database backup written to: ' . $backupPath;
        } else {
            $sqlExecuted[] = '-- DRY RUN: backup would be written to: ' . $backupPath;
        }

        $existing = self::existingTables($pdo, self::TRANSACTIONAL_TABLES);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $sqlExecuted[] = 'SET FOREIGN_KEY_CHECKS = 0;';

        foreach ($existing as $table) {
            $stmt = 'TRUNCATE TABLE `' . $table . '`;';
            $sqlExecuted[] = $stmt;
            if (!$dryRun) {
                $pdo->exec($stmt);
            }
            $tablesCleaned[] = $table;
        }

        foreach ($existing as $table) {
            $stmt = 'ALTER TABLE `' . $table . '` AUTO_INCREMENT = 1;';
            $sqlExecuted[] = $stmt;
            if (!$dryRun) {
                try {
                    $pdo->exec($stmt);
                } catch (\Throwable $e) {
                    // Some tables may not use AUTO_INCREMENT.
                }
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $sqlExecuted[] = 'SET FOREIGN_KEY_CHECKS = 1;';

        $logFilesCleared = self::clearTransactionalLogFiles($root, $dryRun);

        return [
            'backup_path' => $backupPath,
            'tables_cleaned' => $tablesCleaned,
            'sql_executed' => $sqlExecuted,
            'log_files_cleared' => $logFilesCleared,
        ];
    }

    /**
     * @param list<string> $tables
     * @return list<string>
     */
    private static function existingTables(\PDO $pdo, array $tables): array
    {
        $found = [];
        foreach ($tables as $table) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = :name'
            );
            $stmt->execute(['name' => $table]);
            if ((int) $stmt->fetchColumn() > 0) {
                $found[] = $table;
            }
        }

        return $found;
    }

    private static function backupDatabase(\PDO $pdo, string $path): void
    {
        $fh = fopen($path, 'wb');
        if ($fh === false) {
            throw new \RuntimeException('Cannot write backup file: ' . $path);
        }

        fwrite($fh, "-- LearnWise LMS full backup\n");
        fwrite($fh, '-- Generated: ' . gmdate('Y-m-d H:i:s') . " UTC\n\n");
        fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

        $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        foreach ($tables as $table) {
            $createRow = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->fetch(\PDO::FETCH_NUM);
            if ($createRow) {
                fwrite($fh, 'DROP TABLE IF EXISTS `' . $table . "`;\n");
                fwrite($fh, $createRow[1] . ";\n\n");
            }

            $rows = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`');
            while ($row = $rows->fetch(\PDO::FETCH_ASSOC)) {
                $columns = array_map(static fn (string $c): string => '`' . $c . '`', array_keys($row));
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_int($value) || is_float($value)) {
                        $values[] = (string) $value;
                    } else {
                        $values[] = $pdo->quote((string) $value);
                    }
                }
                fwrite(
                    $fh,
                    'INSERT INTO `' . $table . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n"
                );
            }
            fwrite($fh, "\n");
        }

        fwrite($fh, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($fh);
    }

    /**
     * @return list<string>
     */
    private static function clearTransactionalLogFiles(string $root, bool $dryRun): array
    {
        $patterns = [
            $root . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . '*.log',
            $root . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'email' . DIRECTORY_SEPARATOR . '*',
        ];
        $cleared = [];
        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                if (!is_file($file)) {
                    continue;
                }
                $basename = basename($file);
                if (in_array($basename, ['.gitkeep', 'README.md'], true)) {
                    continue;
                }
                if (!$dryRun) {
                    file_put_contents($file, '');
                }
                $cleared[] = str_replace($root . DIRECTORY_SEPARATOR, '', $file);
            }
        }

        return $cleared;
    }
}

<?php

declare(strict_types=1);

/**
 * Append-only logging for report PDF generation and parent email workflow (logs/report.log).
 */
class ReportLog
{
    public static function line(string $message): void
    {
        $root = dirname(__DIR__, 2);
        $dir = $root . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . DIRECTORY_SEPARATOR . 'report.log';
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($file, $line, FILE_APPEND);
    }
}

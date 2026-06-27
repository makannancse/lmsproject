<?php

declare(strict_types=1);

/**
 * Read application log files with pagination (newest entries first).
 */
class LogViewer
{
    /** @var array<string, string> */
    private const LOG_FILES = [
        'audit' => 'user_management.log',
        'email' => 'mail.log',
    ];

    /**
     * @return list<string>
     */
    public static function availableTypes(): array
    {
        return array_keys(self::LOG_FILES);
    }

    public static function logPath(string $type): ?string
    {
        $file = self::LOG_FILES[$type] ?? null;
        if ($file === null) {
            return null;
        }

        $path = dirname(__DIR__, 2) . '/logs/' . $file;
        if (!is_file($path)) {
            return null;
        }

        return $path;
    }

    /**
     * @return array{lines: list<string>, total: int}
     */
    public static function readPage(string $type, int $offset, int $limit): array
    {
        $path = self::logPath($type);
        if ($path === null) {
            return ['lines' => [], 'total' => 0];
        }

        $allLines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $allLines = array_values(array_filter($allLines, static fn(string $line): bool => trim($line) !== ''));
        $allLines = array_reverse($allLines);
        $total = count($allLines);

        return [
            'lines' => array_slice($allLines, $offset, $limit),
            'total' => $total,
        ];
    }

    /**
     * @return list<string>
     */
    public static function emailLogFiles(): array
    {
        $dir = dirname(__DIR__, 2) . '/logs';
        $files = ['mail.log', 'mail_error.log', 'email_credentials.log'];
        $existing = [];
        foreach ($files as $file) {
            if (is_file($dir . '/' . $file)) {
                $existing[] = $file;
            }
        }

        return $existing;
    }

    /**
     * @return array{lines: list<string>, total: int}
     */
    public static function readEmailPage(string $file, int $offset, int $limit): array
    {
        $safe = basename($file);
        $path = dirname(__DIR__, 2) . '/logs/' . $safe;
        if (!is_file($path)) {
            return ['lines' => [], 'total' => 0];
        }

        $allLines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $allLines = array_values(array_filter($allLines, static fn(string $line): bool => trim($line) !== ''));
        $allLines = array_reverse($allLines);
        $total = count($allLines);

        return [
            'lines' => array_slice($allLines, $offset, $limit),
            'total' => $total,
        ];
    }
}

<?php

declare(strict_types=1);

class SyncLog
{
    /**
     * @param array<string, mixed> $context
     */
    public static function write(string $fileName, array $context): void
    {
        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $context['timestamp'] = gmdate('Y-m-d H:i:s');
        @file_put_contents(
            $dir . DIRECTORY_SEPARATOR . $fileName,
            json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND
        );
    }
}

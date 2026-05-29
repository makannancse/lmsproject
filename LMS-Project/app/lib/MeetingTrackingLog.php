<?php

declare(strict_types=1);

class MeetingTrackingLog
{
    /**
     * @param array<string, mixed> $context
     */
    public static function write(string $event, array $context = []): void
    {
        $root = dirname(__DIR__, 2);
        $dir = $root . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $context['event'] = $event;
        $context['timestamp'] = gmdate('Y-m-d H:i:s');
        @file_put_contents(
            $dir . DIRECTORY_SEPARATOR . 'meeting_tracking.log',
            json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND
        );
    }
}

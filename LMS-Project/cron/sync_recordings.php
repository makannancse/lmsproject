<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/lib/MeetingTrackingService.php';
require_once dirname(__DIR__) . '/app/lib/SyncLog.php';

$limit = max(1, (int) env('RECORDING_SYNC_BATCH_LIMIT', 25));
$service = new MeetingTrackingService();

try {
    $result = $service->syncPendingRecordings($limit);
    SyncLog::write('recording_sync.log', [
        'message' => 'Recording sync completed',
        'limit' => $limit,
        'checked' => $result['checked'],
        'synced' => $result['synced'],
        'processing' => $result['processing'],
        'failed' => $result['failed'],
        'disabled' => $result['disabled'],
    ]);
    echo 'Recording sync completed: ' . json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (\Throwable $e) {
    SyncLog::write('recording_sync.log', [
        'message' => 'Recording sync crashed',
        'limit' => $limit,
        'error' => $e->getMessage(),
    ]);
    fwrite(STDERR, 'Recording sync failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/lib/GoogleMeetLiveTrackingService.php';
require_once dirname(__DIR__) . '/app/lib/MeetingTrackingService.php';
require_once dirname(__DIR__) . '/app/lib/SyncLog.php';
require_once dirname(__DIR__) . '/app/lib/MeetingSyncDebugService.php';

$lookbackHours = max(1, (int) env('GOOGLE_MEET_SYNC_LOOKBACK_HOURS', 12));
$lookaheadHours = max(1, (int) env('GOOGLE_MEET_SYNC_LOOKAHEAD_HOURS', 6));
$service = new GoogleMeetLiveTrackingService();

try {
    $tracking = new MeetingTrackingService();
    $ongoingAuto = $tracking->autoSyncOngoingClasses();
    $result = $service->syncClassesForLiveWindow($lookbackHours, $lookaheadHours);
    SyncLog::write('google_meet_status.log', [
        'event' => 'cron_meet_poll_completed',
        'message' => 'Google Meet live status sync completed',
        'lookback_hours' => $lookbackHours,
        'lookahead_hours' => $lookaheadHours,
        'checked' => $result['checked'],
        'started' => $result['started'],
        'completed' => $result['completed'],
        'unchanged' => $result['unchanged'],
        'failed' => $result['failed'],
        'skipped' => $result['skipped'],
        'skip_reasons' => $result['skip_reasons'] ?? [],
        'auto_poll_completed' => $ongoingAuto['completed'] ?? [],
    ]);
    echo 'Meeting sync completed: ' . json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (\Throwable $e) {
    SyncLog::write('google_meet_live_tracking.log', [
        'message' => 'Google Meet live status sync crashed',
        'lookback_hours' => $lookbackHours,
        'lookahead_hours' => $lookaheadHours,
        'error' => $e->getMessage(),
    ]);
    fwrite(STDERR, 'Meeting sync failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

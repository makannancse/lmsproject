<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/GoogleMeetLiveTrackingService.php';
require_once dirname(__DIR__) . '/lib/MeetingSyncDebugService.php';
require_once dirname(__DIR__) . '/lib/MeetingTrackingService.php';

class MeetingSyncDebugController
{
    public static function index(): void
    {
        Auth::requireRole(['admin']);

        $pdo = Database::connection();
        $classId = (int) ($_GET['class_id'] ?? 0);
        $runSync = isset($_GET['run']) && (string) $_GET['run'] === '1';

        $listStmt = $pdo->query(
            'SELECT cs.id, cs.title, cs.status, cs.meeting_live_status,
                    cs.scheduled_timezone, cs.start_time_utc, cs.end_time_utc,
                    cs.actual_start_time, cs.actual_end_time, cs.teacher_joined_at,
                    u.name AS teacher_name
             FROM class_sessions cs
             INNER JOIN users u ON u.id = cs.teacher_id
             WHERE cs.meeting_link IS NOT NULL AND TRIM(cs.meeting_link) <> ""
             ORDER BY cs.id DESC
             LIMIT 40'
        );
        $classes = $listStmt->fetchAll() ?: [];

        $debug = null;
        $syncResult = null;
        $timezoneCheck = null;
        $classRow = null;

        if ($classId > 0) {
            $stmt = $pdo->prepare('SELECT cs.*, u.email AS teacher_account_email FROM class_sessions cs INNER JOIN users u ON u.id = cs.teacher_id WHERE cs.id = :id LIMIT 1');
            $stmt->execute(['id' => $classId]);
            $classRow = $stmt->fetch() ?: null;

            if ($classRow !== false && $classRow !== null) {
                $debugService = new MeetingSyncDebugService();
                $timezoneCheck = $debugService->verifyTimezoneStorage($classRow);
                $classContext = $debugService->buildClassContext($classRow);

                if ($runSync) {
                    $live = new GoogleMeetLiveTrackingService();
                    $syncResult = $live->syncClass($classId, 'admin_debug');
                    $stmt->execute(['id' => $classId]);
                    $classRow = $stmt->fetch() ?: $classRow;
                    $classContext = $debugService->buildClassContext($classRow);
                }

                $debug = $syncResult['debug'] ?? [
                    'conditions' => [],
                    'failed_conditions' => ['run_sync_required'],
                    'completion_decision' => 'unknown',
                    'reason' => 'Click "Run live sync" to fetch Google Meet API data and evaluate completion conditions.',
                    'api_source' => 'Google Meet API v2 (not Calendar API)',
                ];
            }
        }

        View::render('admin/meeting_sync_debug', [
            'pageTitle' => 'Meeting Sync Debug',
            'classes' => $classes,
            'classId' => $classId,
            'classRow' => $classRow,
            'timezoneCheck' => $timezoneCheck,
            'debug' => $debug,
            'syncResult' => $syncResult,
            'runSync' => $runSync,
        ]);
    }
}

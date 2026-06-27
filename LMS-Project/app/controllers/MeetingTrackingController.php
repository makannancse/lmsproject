<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/GoogleMeetLiveTrackingService.php';
require_once dirname(__DIR__) . '/lib/MeetingTrackingService.php';
require_once dirname(__DIR__) . '/models/ClassSession.php';
require_once dirname(__DIR__) . '/models/SystemConfig.php';
require_once dirname(__DIR__) . '/models/TeacherGoogleAccount.php';

class MeetingTrackingController
{
    /**
     * Background poll: sync ongoing classes from Google Meet (no "End Class" click required).
     */
    public static function syncOngoing(): void
    {
        Auth::requireRole(['admin', 'teacher', 'student']);
        $user = Auth::user() ?: [];
        $userId = (int) ($user['id'] ?? 0);
        $role = (string) ($user['role'] ?? '');

        $service = new MeetingTrackingService();
        try {
            $result = $service->autoSyncOngoingClasses($userId, $role);
            self::json([
                'ok' => true,
                'checked' => $result['checked'],
                'completed' => $result['completed'],
                'still_ongoing' => $result['still_ongoing'],
                'reload' => $result['completed'] !== [],
            ]);
        } catch (\Throwable $e) {
            self::json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public static function track(): void
    {
        Auth::requireRole(['admin', 'teacher', 'student']);
        $user = Auth::user() ?: [];
        $role = (string) ($user['role'] ?? '');
        $userId = (int) ($user['id'] ?? 0);
        $classId = (int) ($_POST['class_id'] ?? 0);
        $event = trim((string) ($_POST['event'] ?? ''));
        $base = appWebPath();

        if ($classId <= 0 || $event === '') {
            $_SESSION['flash_warning'] = 'Invalid meeting tracking request.';
            redirectTo('/dashboard');
        }

        if ($role !== 'admin' && !ClassSession::findByIdForUser($classId, $userId, $role)) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        $service = new MeetingTrackingService();
        $liveService = new GoogleMeetLiveTrackingService();
        try {
            if ($event === 'teacher-start') {
                if ($role !== 'teacher') {
                    throw new RuntimeException('Only the assigned teacher can start this class.');
                }
                $classRow = $service->getClassById($classId);
                if ($classRow === null) {
                    throw new RuntimeException('Class not found.');
                }
                if ((int) ($classRow['teacher_id'] ?? 0) !== $userId) {
                    throw new RuntimeException('Only the assigned teacher can start this class.');
                }
                $acct = TeacherGoogleAccount::findByTeacherId($userId);
                $requiresRecordingAck = TeacherGoogleAccount::recordingSupportedFromAccountRow($acct);
                if ($requiresRecordingAck && (int) ($_POST['recording_acknowledged'] ?? 0) !== 1) {
                    throw new RuntimeException('Please acknowledge the recording reminder before starting the class.');
                }

                $class = $service->startTeacherSession($classId, $userId);
                try {
                    $liveService->syncClass($classId, 'teacher_launch');
                    $synced = $service->getClassById($classId);
                    if (is_array($synced)) {
                        $class = $synced;
                    }
                } catch (\Throwable $ignored) {
                    // Meet API sync is best-effort before redirect; cron/webhooks continue tracking.
                }
                $meetingLink = trim((string) ($class['meeting_link'] ?? ''));
                if ($meetingLink === '') {
                    $meetingLink = trim((string) SystemConfig::get('static_meeting_link', env('STATIC_MEETING_LINK', '')));
                }
                if ($meetingLink === '') {
                    throw new RuntimeException('No meeting link configured for this class.');
                }
                $teacherGoogleEmail = trim((string) ($class['teacher_google_email'] ?? ($user['email'] ?? '')));
                $meetingLink = appendMeetAuthUser($meetingLink, $teacherGoogleEmail);

                header('Location: ' . $meetingLink);
                exit;
            } elseif ($event === 'leave') {
                if ($role === 'teacher' || $role === 'admin') {
                    $result = $service->finalizeTeacherHostLeave($classId, 'teacher_leave_request');
                    if (($result['status'] ?? '') === 'completed') {
                        $_SESSION['flash_success'] = 'Class completed using the host\'s actual Google Meet end time.';
                    } else {
                        $_SESSION['flash_warning'] = 'Google Meet has not confirmed the host left yet. Leave the Meet call, wait a few seconds, then try again.';
                    }
                } else {
                    $service->markLeave($classId, $userId, $role);
                    $_SESSION['flash_info'] = 'Your attendance has been updated.';
                }
            } elseif ($event === 'end') {
                try {
                    $result = $service->finalizeTeacherHostLeave($classId, 'manual_end_request');
                    if (($result['status'] ?? '') === 'completed') {
                        $_SESSION['flash_success'] = 'Class completed using real Google Meet timings.';
                    } else {
                        $_SESSION['flash_warning'] = 'Google Meet has not reported the host session as ended yet.';
                    }
                } catch (\Throwable $endError) {
                    $_SESSION['flash_warning'] = $endError->getMessage();
                }
            } elseif ($event === 'sync-recording') {
                $result = $service->syncRecordingForClass($classId, true);
                $_SESSION['flash_success'] = ($result['message'] ?? 'Recording sync processed.');
            }
        } catch (\Throwable $e) {
            $_SESSION['flash_warning'] = $e->getMessage();
            if ($event === 'teacher-start' && $classId > 0) {
                redirectTo('/join-class?class_id=' . $classId);
                exit;
            }
        }

        redirect($_SERVER['HTTP_REFERER'] ?? url('dashboard'));
        exit;
    }

    public static function webhook(): void
    {
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            self::json(['ok' => false, 'error' => 'Invalid JSON payload'], 400);
            return;
        }

        $workspaceEvent = self::decodeWorkspaceEventPayload($payload);
        if ($workspaceEvent !== null) {
            $liveService = new GoogleMeetLiveTrackingService();
            $trackingService = new MeetingTrackingService();
            try {
                $conferenceRecordName = self::conferenceRecordFromWorkspaceEvent($workspaceEvent);
                $spaceName = self::spaceNameFromWorkspaceEvent($workspaceEvent);
                $synced = null;
                if ($conferenceRecordName !== null) {
                    $synced = $liveService->syncClassByConferenceRecord($conferenceRecordName, 'workspace_event');
                }
                if ($synced === null && $spaceName !== null) {
                    $synced = $liveService->syncClassBySpaceName($spaceName, 'workspace_event');
                }
                $completed = false;
                $classId = (int) ($synced['class_id'] ?? 0);
                if (
                    $classId > 0
                    && (
                        ($synced['status'] ?? '') === 'completed'
                        || ($synced['meeting_live_status'] ?? '') === 'ended'
                    )
                ) {
                    try {
                        $trackingService->finalizeTeacherHostLeave($classId, 'workspace_event');
                        $completed = true;
                    } catch (\Throwable $ignored) {
                        $completed = ($synced['status'] ?? '') === 'completed';
                    }
                }
                self::json([
                    'ok' => true,
                    'workspace_event_synced' => $synced !== null,
                    'class_completed' => $completed,
                ]);
            } catch (\Throwable $e) {
                self::json(['ok' => false, 'error' => $e->getMessage()], 500);
            }
            return;
        }

        $expected = trim((string) env('GOOGLE_MEET_WEBHOOK_TOKEN', ''));
        if ($expected !== '') {
            $provided = (string) ($_SERVER['HTTP_X_MEET_WEBHOOK_TOKEN'] ?? ($payload['token'] ?? ''));
            if (!hash_equals($expected, $provided)) {
                self::json(['ok' => false, 'error' => 'Invalid webhook token'], 403);
                return;
            }
        }

        $service = new MeetingTrackingService();
        $liveService = new GoogleMeetLiveTrackingService();
        $event = trim((string) ($payload['event'] ?? ''));
        $class = null;
        if (!empty($payload['class_id'])) {
            $class = $service->getClassById((int) $payload['class_id']);
        } elseif (!empty($payload['google_event_id'])) {
            $class = $service->getClassByGoogleEventId((string) $payload['google_event_id']);
        }
        if ($class === null) {
            self::json(['ok' => false, 'error' => 'Class not found for webhook event'], 404);
            return;
        }

        try {
            switch ($event) {
                case 'meeting_started':
                case 'participant_joined':
                case 'participant_left':
                    $liveService->syncClass((int) $class['id'], 'google_webhook');
                    break;
                case 'meeting_ended':
                    $service->finalizeTeacherHostLeave((int) $class['id'], 'google_webhook');
                    break;
                case 'recording_ready':
                    $service->syncRecordingForClass((int) $class['id'], true);
                    break;
                default:
                    self::json(['ok' => false, 'error' => 'Unsupported event'], 422);
                    return;
            }
        } catch (\Throwable $e) {
            self::json(['ok' => false, 'error' => $e->getMessage()], 500);
            return;
        }

        self::json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeWorkspaceEventPayload(array $payload): ?array
    {
        $message = $payload['message'] ?? null;
        if (!is_array($message) || empty($message['data']) || !is_string($message['data'])) {
            return null;
        }

        $decoded = base64_decode($message['data'], true);
        if (!is_string($decoded) || trim($decoded) === '') {
            return null;
        }

        $event = json_decode($decoded, true);
        return is_array($event) ? $event : null;
    }

    /**
     * @param array<string, mixed> $workspaceEvent
     */
    private static function conferenceRecordFromWorkspaceEvent(array $workspaceEvent): ?string
    {
        foreach ([
            ['conferenceRecord', 'name'],
            ['participantSession', 'name'],
            ['recording', 'name'],
        ] as $path) {
            $cursor = $workspaceEvent;
            foreach ($path as $segment) {
                if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                    $cursor = null;
                    break;
                }
                $cursor = $cursor[$segment];
            }
            $value = is_string($cursor) ? trim($cursor) : '';
            if ($value === '') {
                continue;
            }
            if (preg_match('~^(conferenceRecords/[^/]+)~', $value, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $workspaceEvent
     */
    private static function spaceNameFromWorkspaceEvent(array $workspaceEvent): ?string
    {
        $source = trim((string) ($workspaceEvent['source'] ?? ''));
        if ($source === '') {
            return null;
        }

        if (preg_match('~//meet\.googleapis\.com/(spaces/[^/]+)~', $source, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private static function json(array $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($body, JSON_UNESCAPED_SLASHES);
    }
}

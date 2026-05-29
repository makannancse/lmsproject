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
    public static function track(): void
    {
        Auth::requireRole(['admin', 'teacher', 'student']);
        $user = Auth::user() ?: [];
        $role = (string) ($user['role'] ?? '');
        $userId = (int) ($user['id'] ?? 0);
        $classId = (int) ($_POST['class_id'] ?? 0);
        $event = trim((string) ($_POST['event'] ?? ''));
        $base = defined('BASE_PATH') ? BASE_PATH : '';

        if ($classId <= 0 || $event === '') {
            $_SESSION['flash_warning'] = 'Invalid meeting tracking request.';
            header('Location: ' . $base . '/dashboard');
            exit;
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
                $acct = TeacherGoogleAccount::findByTeacherId($userId);
                $requiresRecordingAck = TeacherGoogleAccount::recordingSupportedFromAccountRow($acct);
                if ($requiresRecordingAck && (int) ($_POST['recording_acknowledged'] ?? 0) !== 1) {
                    throw new RuntimeException('Please acknowledge the recording reminder before starting the class.');
                }

                $class = $service->startTeacherSession($classId, $userId);
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
                $service->markLeave($classId, $userId, $role === 'admin' ? 'teacher' : $role);
                $_SESSION['flash_info'] = 'Refreshing the class status from Google Meet activity.';
            } elseif ($event === 'end') {
                $sync = $liveService->syncClass($classId, 'manual_end_request');
                if (($sync['status'] ?? '') === 'completed') {
                    $_SESSION['flash_success'] = 'Class completed using real Google Meet timings.';
                } else {
                    $_SESSION['flash_warning'] = 'Google Meet has not reported the host session as ended yet.';
                }
            } elseif ($event === 'sync-recording') {
                $result = $service->syncRecordingForClass($classId, true);
                $_SESSION['flash_success'] = ($result['message'] ?? 'Recording sync processed.');
            }
        } catch (\Throwable $e) {
            $_SESSION['flash_warning'] = $e->getMessage();
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? ($base . '/dashboard')));
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
                self::json(['ok' => true, 'workspace_event_synced' => $synced !== null]);
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
                case 'meeting_ended':
                    $liveService->syncClass((int) $class['id'], 'google_webhook');
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

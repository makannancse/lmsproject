<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/GoogleOAuthService.php';
require_once dirname(__DIR__) . '/lib/GoogleCalendarMeetingService.php';
require_once dirname(__DIR__) . '/lib/GoogleMeetLiveTrackingService.php';
require_once dirname(__DIR__) . '/lib/GoogleAccountType.php';
require_once dirname(__DIR__) . '/models/StudentPayment.php';
require_once dirname(__DIR__) . '/models/TeacherGoogleAccount.php';
require_once dirname(__DIR__) . '/controllers/ClassController.php';

class GoogleIntegrationController
{
    public static function connectGoogle(): void
    {
        Auth::requireRole(['admin', 'teacher']);
        Auth::startSession();

        $actor = Auth::user() ?: [];
        $actorRole = (string) ($actor['role'] ?? '');
        $actorId = (int) ($actor['id'] ?? 0);
        $teacherId = (int) ($_POST['teacher_id'] ?? $_GET['teacher_id'] ?? $actorId);
        if ($teacherId <= 0) {
            self::json(['error' => 'teacher_id is required'], 422);
            return;
        }
        if ($actorRole === 'teacher' && $actorId !== $teacherId) {
            self::json(['error' => 'Teacher can connect only own account'], 403);
            return;
        }

        try {
            $oauth = new GoogleOAuthService();
            $url = $oauth->buildAuthUrl($teacherId);
            if (function_exists('logGoogleAuth')) {
                logGoogleAuth([
                    'event' => 'oauth_connect_redirect',
                    'teacher_id' => $teacherId,
                    'actor_role' => $actorRole,
                    'redirect_uri' => $oauth->configuredRedirectUri(),
                    'google_auth_url' => $url,
                ]);
            }
            header('Location: ' . $url, true, 302);
            exit;
        } catch (\Throwable $e) {
            if (function_exists('logGoogleAuth')) {
                logGoogleAuth([
                    'event' => 'oauth_connect_failed',
                    'teacher_id' => $teacherId,
                    'error' => $e->getMessage(),
                ]);
            }
            self::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function authGoogle(): void
    {
        Auth::requireRole(['admin', 'teacher']);
        Auth::startSession();

        $actor = Auth::user() ?: [];
        $actorRole = (string) ($actor['role'] ?? '');
        $actorId = (int) ($actor['id'] ?? 0);
        $teacherId = (int) ($_GET['teacher_id'] ?? $_POST['teacher_id'] ?? $actorId);
        if ($teacherId <= 0) {
            $_SESSION['flash_warning'] = 'Teacher id is required to connect Google.';
            redirectTo($actorRole === 'admin' ? '/admin' : '/teacher');
        }
        if ($actorRole === 'teacher' && $actorId !== $teacherId) {
            $_SESSION['flash_warning'] = 'You can connect only your own Google account.';
            redirectTo('/teacher');
        }

        try {
            $oauth = new GoogleOAuthService();
            $url = $oauth->buildAuthUrl($teacherId);
            if (function_exists('logGoogleAuth')) {
                logGoogleAuth([
                    'event' => 'oauth_redirect_to_google',
                    'teacher_id' => $teacherId,
                    'actor_role' => $actorRole,
                    'redirect_uri' => $oauth->configuredRedirectUri(),
                    'google_auth_url' => $url,
                ]);
            }
            header('Location: ' . $url, true, 302);
            exit;
        } catch (\Throwable $e) {
            if (function_exists('logGoogleAuth')) {
                logGoogleAuth([
                    'event' => 'oauth_auth_url_failed',
                    'teacher_id' => $teacherId,
                    'error' => $e->getMessage(),
                ]);
            }
            self::redirectAfterOAuth(0, 'Google connection failed: ' . $e->getMessage(), true);
        }
    }

    public static function callback(): void
    {
        Auth::startSession();
        $code = (string) ($_GET['code'] ?? '');
        $state = (string) ($_GET['state'] ?? '');
        if (function_exists('logGoogleAuth')) {
            logGoogleAuth([
                'event' => 'oauth_callback_received',
                'has_code' => $code !== '',
                'has_state' => $state !== '',
                'callback_url' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
                'user_role' => (string) ($_SESSION['role'] ?? ''),
            ]);
        }
        if ($code === '' || $state === '') {
            self::redirectAfterOAuth(0, 'Google connection failed: missing authorization code.', true);
            return;
        }

        try {
            $result = (new GoogleOAuthService())->handleCallback($code, $state);
            $message = 'Google account connected successfully.';
            $profile = GoogleAccountType::profileFromEmail($result['email'] ?? null);
            if (!$profile['recording_supported']) {
                $message .= ' Meet scheduling works with your Gmail account. Cloud recording and Drive sync require Google Workspace.';
            }
            self::redirectAfterOAuth((int) ($result['teacher_id'] ?? 0), $message);
        } catch (\Throwable $e) {
            if (function_exists('logGoogleAuth')) {
                logGoogleAuth([
                    'event' => 'oauth_callback_failed',
                    'error' => $e->getMessage(),
                ]);
            }
            self::redirectAfterOAuth(0, 'Google connection failed: ' . $e->getMessage(), true);
        }
    }

    public static function authGoogleCallback(): void
    {
        self::callback();
    }

    public static function disconnectGoogle(): void
    {
        Auth::requireRole(['admin', 'teacher']);
        Auth::startSession();

        $actor = Auth::user() ?: [];
        $actorRole = (string) ($actor['role'] ?? '');
        $actorId = (int) ($actor['id'] ?? 0);
        $teacherId = (int) ($_POST['teacher_id'] ?? $_GET['teacher_id'] ?? $actorId);
        if ($teacherId <= 0) {
            self::json(['error' => 'teacher_id is required'], 422);
            return;
        }
        if ($actorRole === 'teacher' && $actorId !== $teacherId) {
            self::json(['error' => 'Teacher can disconnect only own account'], 403);
            return;
        }

        (new GoogleOAuthService())->prepareReconnect($teacherId);
        $_SESSION['flash_success'] = 'Google account disconnected.';
        redirectTo($actorRole === 'admin' ? '/admin' : '/teacher');
    }

    public static function createClass(): void
    {
        Auth::requireRole(['admin']);

        $payload = self::jsonBody();
        $actor = Auth::user() ?: [];
        $actorRole = (string) ($actor['role'] ?? '');

        $teacherId = (int) ($payload['teacher_id'] ?? 0);
        $startTime = trim((string) ($payload['start_time'] ?? ''));
        $endTime = trim((string) ($payload['end_time'] ?? ''));
        $timezone = normalizeTimezone((string) ($payload['timezone'] ?? 'Asia/Kolkata'), APP_TIMEZONE);
        $summary = trim((string) ($payload['summary'] ?? 'LMS Class'));
        $title = trim((string) ($payload['title'] ?? $summary));
        $studentIds = array_values(array_unique(array_filter(array_map('intval', (array) ($payload['student_ids'] ?? [])))));
        if ($teacherId <= 0 || $startTime === '' || $endTime === '') {
            self::json(['error' => 'teacher_id, start_time and end_time are required'], 422);
            return;
        }
        if ($actorRole !== 'admin') {
            self::json(['error' => 'Only admin can schedule classes'], 403);
            return;
        }

        try {
            $startUtc = localToUtcString($startTime, $timezone);
            $endUtc = localToUtcString($endTime, $timezone);
            $service = new GoogleCalendarMeetingService();
            $meetTrackingService = new GoogleMeetLiveTrackingService();
            $meeting = $service->createMeeting(
                $teacherId,
                utcToTimezoneIso8601($startUtc, 'UTC'),
                utcToTimezoneIso8601($endUtc, 'UTC'),
                'UTC',
                $summary,
                self::studentEmailsForIds($studentIds)
            );
            $account = (new GoogleOAuthService())->getTeacherAccount($teacherId);
            $spaceMeta = null;
            try {
                $spaceMeta = $meetTrackingService->describeSpaceForMeetingLink($teacherId, (string) ($meeting['meet_link'] ?? ''));
            } catch (\Throwable $ignored) {
                $spaceMeta = null;
            }
            $classId = self::insertClassSession(
                $teacherId,
                $title,
                $startTime,
                $endTime,
                $timezone,
                $meeting['meet_link'],
                $meeting['event_id'],
                (string) ($meeting['organizer_email'] ?? ($account['google_email'] ?? '')),
                is_array($spaceMeta) ? (string) ($spaceMeta['name'] ?? '') : '',
                $studentIds
            );
            $mail = ClassController::sendClassNotification($classId);

            self::json([
                'ok' => true,
                'class_id' => $classId,
                'teacher_id' => $teacherId,
                'meeting_link' => $meeting['meet_link'],
                'event_id' => $meeting['event_id'],
                'mail_status' => $mail['status'] ?? 'failed',
            ]);
        } catch (\Throwable $e) {
            $code = str_contains(strtolower($e->getMessage()), 'already has a class') ? 409 : 500;
            self::json(['error' => $e->getMessage()], $code);
        }
    }

    private static function insertClassSession(
        int $teacherId,
        string $title,
        string $startTime,
        string $endTime,
        string $timezone,
        string $meetingLink,
        string $eventId,
        string $teacherGoogleEmail,
        string $googleMeetSpaceName = '',
        array $studentIds = []
    ): int {
        $start = new DateTimeImmutable($startTime, new DateTimeZone($timezone));
        $end = new DateTimeImmutable($endTime, new DateTimeZone($timezone));
        $startUtc = $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $endUtc = $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $tgaRowRec = TeacherGoogleAccount::findByTeacherId($teacherId);
            $recordingEnabledInsert = TeacherGoogleAccount::recordingSupportedFromAccountRow($tgaRowRec) ? 1 : 0;
            $googleMeetingCode = self::extractGoogleMeetCode($meetingLink);
            $stmt = $pdo->prepare(
                'INSERT INTO class_sessions
                    (teacher_id, title, start_datetime, scheduled_time_utc, start_time_utc, end_datetime, end_time_utc, timezone, scheduled_timezone, meeting_link, google_event_id, teacher_google_email, google_meet_space_name, google_meeting_code, meeting_live_status, status, recording_enabled, payout_amount, student_fee)
                 VALUES
                    (:teacher_id, :title, :start_datetime, :scheduled_time_utc, :start_time_utc, :end_datetime, :end_time_utc, :timezone, :scheduled_timezone, :meeting_link, :google_event_id, :teacher_google_email, :google_meet_space_name, :google_meeting_code, "pending", "scheduled", :recording_enabled, 0.00, 0.00)'
            );
            $stmt->execute([
                'teacher_id' => $teacherId,
                'title' => $title === '' ? 'LMS Class' : $title,
                'start_datetime' => $startUtc,
                'scheduled_time_utc' => $startUtc,
                'start_time_utc' => $startUtc,
                'end_datetime' => $endUtc,
                'end_time_utc' => $endUtc,
                'timezone' => $timezone,
                'scheduled_timezone' => $timezone,
                'meeting_link' => $meetingLink,
                'google_event_id' => $eventId,
                'teacher_google_email' => $teacherGoogleEmail !== '' ? $teacherGoogleEmail : null,
                'google_meet_space_name' => $googleMeetSpaceName !== '' ? $googleMeetSpaceName : null,
                'google_meeting_code' => $googleMeetingCode,
                'recording_enabled' => $recordingEnabledInsert,
            ]);
            $classId = (int) $pdo->lastInsertId();

            if (!empty($studentIds)) {
                $insertEnroll = $pdo->prepare(
                    'INSERT INTO enrollments (class_id, student_id, status)
                     VALUES (:class_id, :student_id, "active")'
                );
                foreach ($studentIds as $studentId) {
                    $insertEnroll->execute([
                        'class_id' => $classId,
                        'student_id' => $studentId,
                    ]);
                    StudentPayment::createPendingForEnrollment($classId, $studentId, 0.00);
                }
            }

            $pdo->commit();
            return $classId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return $_POST ?: [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : ($_POST ?: []);
    }

    private static function json(array $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($body, JSON_UNESCAPED_SLASHES);
    }

    private static function redirectAfterOAuth(int $teacherId, string $message, bool $isError = false): void
    {
        $actor = Auth::user() ?: [];
        $role = (string) ($actor['role'] ?? 'teacher');
        $_SESSION[$isError ? 'flash_warning' : 'flash_success'] = $message;

        $path = $role === 'admin' ? '/admin' : '/teacher';
        if ($role === 'admin' && $teacherId > 0) {
            $path .= '#teacher-google-connections';
        }

        if (function_exists('logGoogleAuth')) {
            logGoogleAuth([
                'event' => 'oauth_final_redirect',
                'teacher_id' => $teacherId,
                'user_role' => $role,
                'is_error' => $isError,
                'final_destination' => appUrl($path),
                'relative_destination' => appRelativeUrl($path),
            ]);
        }

        redirectTo($path);
    }

    private static function extractGoogleMeetCode(?string $meetingLink): ?string
    {
        $meetingLink = trim((string) $meetingLink);
        if ($meetingLink === '') {
            return null;
        }

        if (preg_match('~meet\.google\.com/([a-z]{3}-[a-z]{4}-[a-z]{3})~i', $meetingLink, $matches) === 1) {
            return strtolower($matches[1]);
        }

        return null;
    }

    /**
     * @param list<int> $studentIds
     * @return list<string>
     */
    private static function studentEmailsForIds(array $studentIds): array
    {
        $studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds), static fn (int $id): bool => $id > 0)));
        if ($studentIds === []) {
            return [];
        }

        $pdo = Database::connection();
        $placeholders = [];
        $params = [];
        foreach ($studentIds as $index => $studentId) {
            $key = 'sid' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $studentId;
        }

        $stmt = $pdo->prepare(
            'SELECT email
             FROM users
             WHERE role = "student"
               AND id IN (' . implode(', ', $placeholders) . ')
               AND email IS NOT NULL
               AND TRIM(email) <> ""'
        );
        $stmt->execute($params);

        $emails = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if ($email !== '') {
                $emails[$email] = $email;
            }
        }

        return array_values($emails);
    }
}

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/models/User.php';
require_once dirname(__DIR__) . '/models/ClassSession.php';
require_once dirname(__DIR__) . '/lib/GoogleCalendarMeetingService.php';
require_once dirname(__DIR__) . '/lib/Mailer.php';
require_once dirname(__DIR__) . '/models/SystemConfig.php';
require_once dirname(__DIR__) . '/models/ClassMaster.php';
require_once dirname(__DIR__) . '/models/TeacherGoogleAccount.php';
require_once dirname(__DIR__) . '/lib/PayoutService.php';
require_once dirname(__DIR__) . '/models/TeacherPayout.php';
require_once dirname(__DIR__) . '/models/StudentPayment.php';
require_once dirname(__DIR__) . '/models/TeacherStudent.php';
require_once dirname(__DIR__) . '/lib/MeetingTrackingService.php';
require_once dirname(__DIR__) . '/lib/GoogleMeetLiveTrackingService.php';

class ClassController
{
    public static function joinTrack(): void
    {
        Auth::requireRole(['teacher', 'student']);
        $user = Auth::user();
        $classId = (int) ($_GET['class_id'] ?? 0);
        if ($classId <= 0) {
            http_response_code(400);
            echo 'Invalid class.';
            return;
        }

        $role = (string) ($user['role'] ?? '');
        $uid = (int) ($user['id'] ?? 0);
        $class = ClassSession::findByIdForUser($classId, $uid, $role);
        if (!$class) {
            http_response_code(403);
            echo 'Not allowed for this class.';
            return;
        }

        $target = self::resolveMeetingLink($class);
        if ($target === '') {
            $target = SystemConfig::get('static_meeting_link', env('STATIC_MEETING_LINK', ''));
        }
        if ($target === '') {
            http_response_code(500);
            echo 'No meeting link configured.';
            return;
        }

        $displayTimezone = resolveUserTimezone($user, classScheduledTimezone($class, APP_TIMEZONE));
        if ($role === 'teacher') {
            View::render('classes/teacher_launch', [
                'pageTitle' => 'Launch Class',
                'class' => $class,
                'displayTimezone' => $displayTimezone,
                'teacherGoogleEmail' => (string) ($class['teacher_google_email'] ?? ($user['email'] ?? '')),
                'recordingWorkflowSupported' => TeacherGoogleAccount::recordingSupportedFromAccountRow(
                    TeacherGoogleAccount::findByTeacherId((int) ($user['id'] ?? 0))
                ),
            ]);
            return;
        }

        try {
            $liveService = new GoogleMeetLiveTrackingService();
            $liveService->syncClass($classId, 'student_join_check');
            $refreshed = ClassSession::findByIdForUser($classId, $uid, $role);
            if (is_array($refreshed)) {
                $class = $refreshed;
            }
        } catch (\Throwable $ignored) {
            // Keep the waiting-room experience if the Meet API check is temporarily unavailable.
        }

        if (!isTeacherHostActiveForClass($class)) {
            View::render('classes/student_waiting', [
                'pageTitle' => 'Waiting For Teacher',
                'class' => $class,
                'displayTimezone' => $displayTimezone,
            ]);
            return;
        }

        $tracking = new MeetingTrackingService();
        $tracking->markJoin($classId, $uid, $role);

        header('Location: ' . studentMeetJoinUrl($target, $user));
        exit;
    }

    /**
     * Notify the assigned teacher and enrolled students by email.
     *
     * @return array{sent:int,failed:int,total:int,status:string,error?:string}
     *         status: success | partial | failed
     */
    public static function sendClassNotification(int $classId): array
    {
        $pdo = Database::connection();

        $classStmt = $pdo->prepare(
            'SELECT cs.id, cs.title, cs.start_datetime, cs.start_time_utc, cs.end_datetime, cs.end_time_utc,
                    cs.timezone, cs.scheduled_timezone, cs.meeting_link,
                    u.name AS teacher_name, u.email AS teacher_email, u.timezone AS teacher_timezone
             FROM class_sessions cs
             INNER JOIN users u ON u.id = cs.teacher_id
             WHERE cs.id = :id
             LIMIT 1'
        );
        $classStmt->execute(['id' => $classId]);
        $class = $classStmt->fetch();

        if (!$class) {
            return ['sent' => 0, 'failed' => 0, 'total' => 0, 'status' => 'failed'];
        }

        $studentStmt = $pdo->prepare(
            'SELECT u.name, u.email, u.timezone
             FROM enrollments e
             INNER JOIN users u ON u.id = e.student_id
             WHERE e.class_id = :class_id
               AND e.status = "active"'
        );
        $studentStmt->execute(['class_id' => $classId]);
        $students = $studentStmt->fetchAll() ?: [];

        $recipients = [];
        if (!empty($class['teacher_email'])) {
            $recipients[] = [
                'name' => (string) ($class['teacher_name'] ?? 'Teacher'),
                'email' => (string) $class['teacher_email'],
                'timezone' => (string) ($class['teacher_timezone'] ?? APP_TIMEZONE),
                'role' => 'teacher',
            ];
        }
        foreach ($students as $student) {
            if (empty($student['email'])) {
                continue;
            }
            $recipients[] = [
                'name' => (string) ($student['name'] ?? 'Student'),
                'email' => (string) $student['email'],
                'timezone' => (string) ($student['timezone'] ?? APP_TIMEZONE),
                'role' => 'student',
            ];
        }

        $eligible = 0;
        foreach ($recipients as $row) {
            if (!empty($row['email'])) {
                $eligible++;
            }
        }

        $smtpErr = Mailer::getSmtpEnvError();
        if ($eligible > 0 && $smtpErr !== null) {
            Mailer::logSmtpIssue($smtpErr);

            return [
                'sent' => 0,
                'failed' => $eligible,
                'total' => count($recipients),
                'status' => 'failed',
                'error' => $smtpErr,
            ];
        }

        $result = ['sent' => 0, 'failed' => 0, 'total' => count($recipients)];
        foreach ($recipients as $recipient) {
            try {
                $studentTimezone = $recipient['timezone'] ?? APP_TIMEZONE;
                $startLocal = formatUtcForTimezone(classStartUtcValue($class), $studentTimezone, 'Y-m-d h:i A T');
                $endLocal = formatUtcForTimezone(classEndUtcValue($class), $studentTimezone, 'Y-m-d h:i A T');
                $scheduledStart = formatClassScheduledAt($class, 'Y-m-d h:i A T');
                $scheduledTimezoneLabel = formatClassScheduledTimezoneLabel($class);

                $meetingLink = self::resolveMeetingLink($class);
                $subject = 'Class Scheduled: ' . $class['title'];
                $body = self::buildClassEmailTemplate(
                    (string) ($recipient['name'] ?? 'User'),
                    (string) $class['title'],
                    $startLocal,
                    $endLocal,
                    $scheduledStart,
                    $scheduledTimezoneLabel,
                    (string) $class['teacher_name'],
                    $meetingLink,
                    (string) ($recipient['role'] ?? 'student')
                );

                $mailResponse = Mailer::send((string) $recipient['email'], $subject, $body, true);
                if (!empty($mailResponse['success'])) {
                    $result['sent']++;
                } else {
                    $result['failed']++;
                    error_log(
                        'Class notification failed for class #' . $classId .
                        ' to ' . $recipient['email'] .
                        ': ' . ($mailResponse['error'] ?? 'Unknown error')
                    );
                }
            } catch (\Throwable $e) {
                $result['failed']++;
                error_log('Class notification failed for class #' . $classId . ': ' . $e->getMessage());
            }
        }

        if ($result['total'] === 0) {
            $result['status'] = 'success';
        } elseif ($result['failed'] === 0) {
            $result['status'] = 'success';
        } elseif ($result['sent'] > 0) {
            $result['status'] = 'partial';
        } else {
            $result['status'] = 'failed';
        }

        return $result;
    }

    private static function buildClassEmailTemplate(
        string $studentName,
        string $classTitle,
        string $startLocal,
        string $endLocal,
        string $scheduledStart,
        string $scheduledTimezoneLabel,
        string $teacherName,
        string $meetingLink,
        string $recipientRole = 'student'
    ): string {
        $safeStudentName = htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8');
        $safeClassTitle = htmlspecialchars($classTitle, ENT_QUOTES, 'UTF-8');
        $safeStart = htmlspecialchars($startLocal, ENT_QUOTES, 'UTF-8');
        $safeEnd = htmlspecialchars($endLocal, ENT_QUOTES, 'UTF-8');
        $safeScheduledStart = htmlspecialchars($scheduledStart, ENT_QUOTES, 'UTF-8');
        $safeScheduledTimezoneLabel = htmlspecialchars($scheduledTimezoneLabel, ENT_QUOTES, 'UTF-8');
        $safeTeacherName = htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8');
        $safeMeetingLink = htmlspecialchars($meetingLink, ENT_QUOTES, 'UTF-8');
        $intro = $recipientRole === 'teacher'
            ? 'Your class has been scheduled successfully.'
            : 'Your class has been scheduled successfully.';
        $actionLabel = $recipientRole === 'teacher' ? 'Open Class' : 'Join Class';

        return '
            <div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
                <h2 style="margin-bottom: 8px;">Class Scheduled</h2>
                <p>Hi ' . $safeStudentName . ',</p>
                <p>' . htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') . '</p>
                <table cellpadding="6" cellspacing="0" border="0" style="border-collapse: collapse;">
                    <tr><td><strong>Class</strong></td><td>' . $safeClassTitle . '</td></tr>
                    <tr><td><strong>Your Time</strong></td><td>' . $safeStart . ' to ' . $safeEnd . '</td></tr>
                    <tr><td><strong>Scheduled Timezone</strong></td><td>' . $safeScheduledStart . '<br>' . $safeScheduledTimezoneLabel . '</td></tr>
                    <tr><td><strong>Instructor</strong></td><td>' . $safeTeacherName . '</td></tr>
                    <tr><td><strong>Google Meet link</strong></td><td><a href="' . $safeMeetingLink . '">' . $safeMeetingLink . '</a></td></tr>
                </table>
                <p style="margin-top: 16px;">
                    <a href="' . $safeMeetingLink . '" style="display: inline-block; background: #0d6efd; color: #ffffff; text-decoration: none; padding: 10px 16px; border-radius: 6px;">
                        ' . htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') . '
                    </a>
                </p>
                <p style="margin-top: 16px;">Regards,<br>' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . ' Team</p>
            </div>
        ';
    }

    private static function resolveMeetingLink(array $classRow): string
    {
        if (!empty($classRow['meeting_link'])) {
            return (string) $classRow['meeting_link'];
        }
        return trim((string) SystemConfig::get('static_meeting_link', env('STATIC_MEETING_LINK', '')));
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

    private static function teacherGoogleSchedulingError(int $teacherId): ?string
    {
        $account = TeacherGoogleAccount::getCredentialsForTeacher($teacherId);
        if ($account === null) {
            return 'Assigned teacher must connect a Google account (Workspace or Gmail) before scheduling.';
        }
        $status = (string) ($account['status'] ?? '');
        if ($status === 'error') {
            return 'Assigned teacher Google connection is invalid. Please reconnect the account.';
        }
        if ($status !== 'active') {
            return 'Assigned teacher Google account is disconnected. Please reconnect it first.';
        }
        if (trim((string) ($account['refresh_token'] ?? '')) === '') {
            return 'Assigned teacher Google token is incomplete. Please reconnect with offline consent.';
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

    public static function index(): void
    {
        Auth::requireRole(['admin', 'teacher']);
        $user = Auth::user();
        $role = (string) ($user['role'] ?? '');
        $userId = (int) ($user['id'] ?? 0);
        $pdo = Database::connection();
        $statusFilter = trim((string) ($_GET['status'] ?? ''));
        $allowed = ['scheduled', 'ongoing', 'completed', 'cancelled', 'rescheduled'];
        $sql = 'SELECT cs.*, u.name AS teacher_name
             FROM class_sessions cs
             INNER JOIN users u ON u.id = cs.teacher_id';
        $params = [];
        $where = [];
        if ($role === 'teacher') {
            $where[] = 'cs.teacher_id = :uid';
            $params['uid'] = $userId;
        }
        if (in_array($statusFilter, $allowed, true)) {
            $where[] = 'cs.status = :st';
            $params['st'] = $statusFilter;
        }
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY cs.start_datetime DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $classes = $stmt->fetchAll() ?: [];

        View::render('classes/index', [
            'pageTitle' => 'Classes',
            'classes' => $classes,
            'statusFilter' => $statusFilter,
        ]);
    }

    public static function updateStatus(): void
    {
        Auth::requireRole(['admin']);
        $pdo = Database::connection();
        $base = defined('BASE_PATH') ? BASE_PATH : '';

        $classId = (int) ($_POST['class_id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if ($classId <= 0 || !in_array($status, ['scheduled', 'ongoing', 'completed', 'cancelled', 'rescheduled'], true)) {
            $_SESSION['flash_warning'] = 'Invalid class status update request.';
            header('Location: ' . $base . '/classes');
            return;
        }

        if ($status === 'completed') {
            $liveService = new GoogleMeetLiveTrackingService();
            $sync = $liveService->syncClass($classId, 'admin_status_update');
            if (($sync['status'] ?? '') === 'completed') {
                $_SESSION['flash_success'] = 'Class completed using actual Google Meet timings.';
            } else {
                $_SESSION['flash_warning'] = 'Google Meet has not reported the teacher session as ended yet.';
            }
            header('Location: ' . $base . '/classes');
            return;
        }

        $stmt = $pdo->prepare('UPDATE class_sessions SET status = :status WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'id' => $classId,
        ]);
        $_SESSION['flash_success'] = 'Class status updated.';
        header('Location: ' . $base . '/classes');
    }

    public static function completed(): void
    {
        Auth::requireRole(['admin']);
        $pdo = Database::connection();
        $stmt = $pdo->query(
            'SELECT cs.*, u.name AS teacher_name
             FROM class_sessions cs
             INNER JOIN users u ON u.id = cs.teacher_id
             WHERE cs.status = "completed"
             ORDER BY cs.completed_at DESC, cs.start_datetime DESC'
        );
        $classes = $stmt->fetchAll() ?: [];

        View::render('classes/completed', [
            'pageTitle' => 'Completed Classes',
            'classes' => $classes,
        ]);
    }

    public static function toggleRecording(): void
    {
        Auth::requireRole(['admin']);
        $classId = (int) ($_POST['class_id'] ?? 0);
        $enabled = (int) ($_POST['recording_enabled'] ?? 0) === 1 ? 1 : 0;
        if ($classId > 0) {
            $pdo = Database::connection();
            $teacherStmt = $pdo->prepare('SELECT teacher_id FROM class_sessions WHERE id = :id LIMIT 1');
            $teacherStmt->execute(['id' => $classId]);
            $row = $teacherStmt->fetch();
            if ($enabled === 1 && $row) {
                $tgaRow = TeacherGoogleAccount::findByTeacherId((int) ($row['teacher_id'] ?? 0));
                if (!TeacherGoogleAccount::recordingSupportedFromAccountRow($tgaRow)) {
                    $_SESSION['flash_warning'] = 'Recording reminders and Drive sync need a Google Workspace–style account (not personal Gmail). You can continue hosting Meet with Gmail.';
                    $enabled = 0;
                }
            }
            $stmt = $pdo->prepare('UPDATE class_sessions SET recording_enabled = :enabled WHERE id = :id');
            $stmt->execute(['enabled' => $enabled, 'id' => $classId]);
        }
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        header('Location: ' . $base . '/classes');
    }

    /**
     * @param array<string, mixed> $old
     * @return list<array<string, mixed>>
     */
    private static function studentsForScheduleForm(array $old = []): array
    {
        $teacherId = (int) ($old['teacher_id'] ?? 0);
        if ($teacherId <= 0) {
            $teachers = User::allTeachers();
            if ($teachers !== []) {
                $teacherId = (int) ($teachers[0]['id'] ?? 0);
            }
        }

        return $teacherId > 0 ? TeacherStudent::studentsForTeacher($teacherId) : [];
    }

    public static function createForm(): void
    {
        Auth::requireRole(['admin']);
        $teachers = User::allTeachers();
        $old = [];
        $students = self::studentsForScheduleForm($old);
        $classTypes = [];
        try {
            $classTypes = ClassMaster::allActive();
        } catch (\Throwable $e) {
            $classTypes = [];
        }
        View::render('classes/create', [
            'pageTitle' => 'Schedule Class',
            'teachers' => $teachers,
            'students' => $students,
            'classTypes' => $classTypes,
        ]);
    }

    public static function editForm(): void
    {
        Auth::requireRole(['admin', 'teacher']);
        $user = Auth::user();
        $role = (string) ($user['role'] ?? '');
        $classId = (int) ($_GET['id'] ?? 0);
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        if ($classId <= 0) {
            header('Location: ' . $base . '/classes');
            return;
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT cs.*, u.name AS teacher_name FROM class_sessions cs INNER JOIN users u ON u.id = cs.teacher_id WHERE cs.id = :id LIMIT 1');
        $stmt->execute(['id' => $classId]);
        $class = $stmt->fetch();
        if (!$class) {
            header('Location: ' . $base . '/classes');
            return;
        }
        if ($role === 'teacher' && (int) $class['teacher_id'] !== (int) ($user['id'] ?? 0)) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        View::render('classes/edit', [
            'pageTitle' => 'Edit Class',
            'class' => $class,
            'errors' => [],
        ]);
    }

    public static function update(): void
    {
        Auth::requireRole(['admin', 'teacher']);
        $user = Auth::user();
        $role = (string) ($user['role'] ?? '');
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        $classId = (int) ($_POST['class_id'] ?? 0);
        if ($classId <= 0) {
            header('Location: ' . $base . '/classes');
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM class_sessions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $classId]);
        $class = $stmt->fetch();
        if (!$class) {
            header('Location: ' . $base . '/classes');
            return;
        }
        if ($role === 'teacher' && (int) $class['teacher_id'] !== (int) ($user['id'] ?? 0)) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        $startRaw = trim((string) ($_POST['start_datetime'] ?? ''));
        $durationMin = (int) ($_POST['duration_minutes'] ?? 0);
        $payoutAmount = parseInrAmount($_POST['payout_amount'] ?? $class['payout_amount']);
        $studentFee = parseInrAmount($_POST['student_fee'] ?? ($class['student_fee'] ?? 0));
        $meetingLink = trim((string) ($_POST['meeting_link'] ?? ''));
        $timezone = classScheduledTimezone($class, APP_TIMEZONE);
        $errors = [];
        if ($startRaw === '') {
            $errors[] = 'Start date/time is required.';
        }
        if ($durationMin <= 0) {
            $errors[] = 'Duration must be greater than zero.';
        }
        if ($payoutAmount < 0) {
            $errors[] = 'Payout amount cannot be negative.';
        }
        if ($studentFee < 0) {
            $errors[] = 'Student fee cannot be negative.';
        }

        try {
            $startLocal = new DateTimeImmutable($startRaw, new DateTimeZone($timezone));
        } catch (\Throwable $e) {
            $errors[] = 'Invalid start date/time.';
            $startLocal = null;
        }

        if (!empty($errors)) {
            View::render('classes/edit', [
                'pageTitle' => 'Edit Class',
                'class' => $class,
                'errors' => $errors,
            ]);
            return;
        }

        $startUtc = $startLocal->setTimezone(new DateTimeZone('UTC'));
        $endUtc = $startUtc->modify('+' . $durationMin . ' minutes');
        $startUtcValue = $startUtc->format('Y-m-d H:i:s');
        $endUtcValue = $endUtc->format('Y-m-d H:i:s');
        $nextMeetingLink = $meetingLink !== '' ? $meetingLink : (string) ($class['meeting_link'] ?? '');
        $nextMeetingCode = self::extractGoogleMeetCode($nextMeetingLink);
        $existingMeetingCode = self::extractGoogleMeetCode((string) ($class['meeting_link'] ?? ''));
        $meetingCodeChanged = $nextMeetingCode !== $existingMeetingCode;

        if (!empty($class['google_event_id']) && !empty($class['teacher_id'])) {
            $meetingService = new GoogleCalendarMeetingService();
            $meetingService->updateMeeting(
                (int) $class['teacher_id'],
                (string) $class['google_event_id'],
                utcToTimezoneIso8601($startUtcValue, 'UTC'),
                utcToTimezoneIso8601($endUtcValue, 'UTC'),
                'UTC',
                (string) ($class['title'] ?? '')
            );
        }

        $upd = $pdo->prepare(
            'UPDATE class_sessions
             SET start_datetime = :start_dt,
                 scheduled_time_utc = :scheduled_time_utc,
                 start_time_utc = :start_time_utc,
                 end_datetime = :end_dt,
                 end_time_utc = :end_time_utc,
                 timezone = :timezone,
                 scheduled_timezone = :scheduled_timezone,
                 payout_amount = :payout,
                 student_fee = :student_fee,
                 meeting_link = :meeting_link,
                 google_meeting_code = :google_meeting_code,
                 google_meet_space_name = :google_meet_space_name,
                 google_conference_id = :google_conference_id,
                 meeting_live_status = :meeting_live_status,
                 meeting_participant_count = CASE
                     WHEN status = "completed" THEN meeting_participant_count
                     ELSE NULL
                 END,
                 teacher_joined_at = CASE
                     WHEN status = "completed" THEN teacher_joined_at
                     ELSE NULL
                 END,
                 student_joined_at = CASE
                     WHEN status = "completed" THEN student_joined_at
                     ELSE NULL
                 END,
                 actual_start_time = CASE
                     WHEN status = "completed" THEN actual_start_time
                     ELSE NULL
                 END,
                 actual_end_time = CASE
                     WHEN status = "completed" THEN actual_end_time
                     ELSE NULL
                 END,
                 actual_duration = CASE
                     WHEN status = "completed" THEN actual_duration
                     ELSE NULL
                 END,
                 actual_duration_minutes = CASE
                     WHEN status = "completed" THEN actual_duration_minutes
                     ELSE NULL
                 END,
                 completed_at = CASE
                     WHEN status = "completed" THEN completed_at
                     ELSE NULL
                 END,
                 status = IF(status = "completed", "completed", "rescheduled")
             WHERE id = :id'
        );
        $upd->execute([
            'start_dt' => $startUtcValue,
            'scheduled_time_utc' => $startUtcValue,
            'start_time_utc' => $startUtcValue,
            'end_dt' => $endUtcValue,
            'end_time_utc' => $endUtcValue,
            'timezone' => $timezone,
            'scheduled_timezone' => $timezone,
            'payout' => $payoutAmount,
            'student_fee' => $studentFee,
            'meeting_link' => $meetingLink !== '' ? $meetingLink : null,
            'google_meeting_code' => $nextMeetingCode,
            'google_meet_space_name' => $meetingCodeChanged ? null : ($class['google_meet_space_name'] ?? null),
            'google_conference_id' => (string) ($class['status'] ?? '') === 'completed' ? ($class['google_conference_id'] ?? null) : null,
            'meeting_live_status' => (string) ($class['status'] ?? '') === 'completed'
                ? (string) ($class['meeting_live_status'] ?? 'ended')
                : 'pending',
            'id' => $classId,
        ]);
        logTimezoneFix([
            'event' => 'class_rescheduled_to_utc',
            'class_id' => $classId,
            'timezone' => $timezone,
            'input_start' => $startRaw,
            'duration_minutes' => $durationMin,
            'start_time_utc' => $startUtcValue,
            'end_time_utc' => $endUtcValue,
        ]);
        logTimezoneConversion([
            'event' => 'class_rescheduled_to_utc',
            'class_id' => $classId,
            'timezone' => $timezone,
            'input_start' => $startRaw,
            'duration_minutes' => $durationMin,
            'start_time_utc' => $startUtcValue,
            'end_time_utc' => $endUtcValue,
        ]);

        $_SESSION['flash_success'] = 'Class updated successfully.';
        header('Location: ' . $base . '/classes');
    }

    public static function store(): void
    {
        Auth::requireRole(['admin']);
        $pdo = Database::connection();
        $calendarAjax = !empty($_POST['calendar_ajax']);

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        $classMasterId = (int) ($_POST['class_master_id'] ?? 0);
        $payoutAmount = parseInrAmount($_POST['payout_amount'] ?? 0);
        $studentFee = parseInrAmount($_POST['student_fee'] ?? 0);
        $start = $_POST['start_datetime'] ?? '';
        $end = $_POST['end_datetime'] ?? '';
        $timezone = normalizeTimezone((string) ($_POST['timezone'] ?? APP_TIMEZONE), APP_TIMEZONE);
        $_POST['timezone'] = $timezone;
        $studentIds = array_filter(array_map('intval', $_POST['student_ids'] ?? []));

        if ($classMasterId > 0) {
            try {
                $cm = ClassMaster::find($classMasterId);
                if ($cm && $title === '') {
                    $title = (string) $cm['class_name'];
                }
                if ($cm && $description === '' && !empty($cm['description'])) {
                    $description = (string) $cm['description'];
                }
            } catch (\Throwable $e) {
                // ignore if class_master table missing
            }
        }

        $errors = [];
        if ($title === '') {
            $errors[] = 'Title is required.';
        }
        if ($payoutAmount < 0) {
            $errors[] = 'Payout amount cannot be negative.';
        }
        if ($studentFee < 0) {
            $errors[] = 'Student fee cannot be negative.';
        }
        if ($teacherId <= 0) {
            $errors[] = 'Teacher is required.';
        }
        if ($teacherId > 0 && $studentIds !== []) {
            $unmapped = TeacherStudent::filterUnmappedStudentIds($teacherId, $studentIds);
            if ($unmapped !== []) {
                $errors[] = 'One or more selected students are not mapped to this teacher. Link them under Admin → Teacher-Students, then refresh the student list.';
            }
        }
        if ($start === '' || $end === '') {
            $errors[] = 'Start and end date/time are required.';
        }

        try {
            $startDt = new DateTimeImmutable($start, new DateTimeZone($timezone));
            $endDt = new DateTimeImmutable($end, new DateTimeZone($timezone));
            if ($endDt <= $startDt) {
                $errors[] = 'End time must be after start time.';
            }
        } catch (\Throwable $e) {
            $errors[] = 'Invalid date/time format.';
        }

        if (!empty($errors)) {
            if ($calendarAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'errors' => $errors]);
                return;
            }
            $teachers = User::allTeachers();
            $classTypes = [];
            try {
                $classTypes = ClassMaster::allActive();
            } catch (\Throwable $e) {
            }
            View::render('classes/create', [
                'pageTitle' => 'Schedule Class',
                'teachers' => $teachers,
                'students' => self::studentsForScheduleForm($_POST),
                'classTypes' => $classTypes,
                'errors' => $errors,
                'old' => $_POST,
            ]);
            return;
        }

        $teacherGoogleError = self::teacherGoogleSchedulingError($teacherId);
        if ($teacherGoogleError !== null) {
            if ($calendarAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'errors' => [$teacherGoogleError]]);
                return;
            }

            $teachers = User::allTeachers();
            $classTypes = [];
            try {
                $classTypes = ClassMaster::allActive();
            } catch (\Throwable $ignored) {
                $classTypes = [];
            }
            View::render('classes/create', [
                'pageTitle' => 'Schedule Class',
                'teachers' => $teachers,
                'students' => self::studentsForScheduleForm($_POST),
                'classTypes' => $classTypes,
                'errors' => [$teacherGoogleError],
                'old' => $_POST,
            ]);
            return;
        }

        $startUtc = $startDt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $endUtc = $endDt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $startUtcIso = utcToTimezoneIso8601($startUtc, 'UTC');
        $endUtcIso = utcToTimezoneIso8601($endUtc, 'UTC');

        // Create the Google Meet event first so the availability check does not
        // see this new class row during the same transaction.
        $meetingService = new GoogleCalendarMeetingService();
        $meetTrackingService = new GoogleMeetLiveTrackingService();
        $googleEventId = null;
        $meetLink = null;
        $googleMeetSpaceName = null;
        $googleMeetingCode = null;
        $teacherGoogleEmail = '';
        try {
            $attendeeEmails = self::studentEmailsForIds($studentIds);
            $meeting = $meetingService->createMeeting(
                $teacherId,
                $startUtcIso,
                $endUtcIso,
                'UTC',
                $title,
                $attendeeEmails
            );
            $googleEventId = $meeting['event_id'] ?? null;
            $meetLink = $meeting['meet_link'] ?? null;
            $googleMeetingCode = self::extractGoogleMeetCode($meetLink);
            $teacherGoogleAccount = TeacherGoogleAccount::getCredentialsForTeacher($teacherId);
            $teacherGoogleEmail = (string) ($meeting['organizer_email'] ?? ($teacherGoogleAccount['google_email'] ?? ''));
            try {
                $spaceMeta = $meetTrackingService->describeSpaceForMeetingLink($teacherId, (string) $meetLink);
                $googleMeetSpaceName = is_array($spaceMeta) ? ($spaceMeta['name'] ?? null) : null;
            } catch (\Throwable $ignored) {
                $googleMeetSpaceName = null;
            }
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            if ($calendarAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'errors' => [$errorMessage]]);
                return;
            }

            $teachers = User::allTeachers();
            $classTypes = [];
            try {
                $classTypes = ClassMaster::allActive();
            } catch (\Throwable $ignored) {
                $classTypes = [];
            }
            View::render('classes/create', [
                'pageTitle' => 'Schedule Class',
                'teachers' => $teachers,
                'students' => self::studentsForScheduleForm($_POST),
                'classTypes' => $classTypes,
                'errors' => [$errorMessage],
                'old' => $_POST,
            ]);
            return;
        }

        $pdo->beginTransaction();
        try {
            $teacherGoogleRowForRec = TeacherGoogleAccount::findByTeacherId($teacherId);
            $recordingEnabledInsert = TeacherGoogleAccount::recordingSupportedFromAccountRow($teacherGoogleRowForRec) ? 1 : 0;
            $insertClass = $pdo->prepare(
                'INSERT INTO class_sessions
                    (teacher_id, class_master_id, title, description, payout_amount, student_fee, start_datetime, scheduled_time_utc, start_time_utc, end_datetime, end_time_utc, timezone, scheduled_timezone, meeting_link, teacher_google_email, google_meet_space_name, google_meeting_code, meeting_live_status, status, recording_enabled)
                 VALUES
                    (:teacher_id, :class_master_id, :title, :description, :payout_amount, :student_fee, :start_datetime, :scheduled_time_utc, :start_time_utc, :end_datetime, :end_time_utc, :timezone, :scheduled_timezone, :meeting_link, :teacher_google_email, :google_meet_space_name, :google_meeting_code, "pending", "scheduled", :recording_enabled)'
            );
            $insertClass->execute([
                'teacher_id' => $teacherId,
                'class_master_id' => $classMasterId > 0 ? $classMasterId : null,
                'title' => $title,
                'description' => $description,
                'payout_amount' => $payoutAmount,
                'student_fee' => $studentFee,
                'start_datetime' => $startUtc,
                'scheduled_time_utc' => $startUtc,
                'start_time_utc' => $startUtc,
                'end_datetime' => $endUtc,
                'end_time_utc' => $endUtc,
                'timezone' => $timezone,
                'scheduled_timezone' => $timezone,
                'meeting_link' => $meetLink,
                'teacher_google_email' => $teacherGoogleEmail !== '' ? $teacherGoogleEmail : null,
                'google_meet_space_name' => $googleMeetSpaceName,
                'google_meeting_code' => $googleMeetingCode,
                'recording_enabled' => $recordingEnabledInsert,
            ]);
            $classId = (int) $pdo->lastInsertId();

            $updateMeet = $pdo->prepare(
                'UPDATE class_sessions 
                 SET google_event_id = :event_id,
                     meeting_link = :meeting_link,
                     teacher_google_email = :teacher_google_email,
                     google_meet_space_name = :google_meet_space_name,
                     google_meeting_code = :google_meeting_code,
                     meeting_live_status = "pending"
                 WHERE id = :id'
            );
            $updateMeet->execute([
                'event_id' => $googleEventId,
                'meeting_link' => $meetLink,
                'teacher_google_email' => $teacherGoogleEmail !== '' ? $teacherGoogleEmail : null,
                'google_meet_space_name' => $googleMeetSpaceName,
                'google_meeting_code' => $googleMeetingCode,
                'id' => $classId,
            ]);

            if (!empty($studentIds)) {
                $insertEnroll = $pdo->prepare(
                    'INSERT INTO enrollments (class_id, student_id, status)
                     VALUES (:class_id, :student_id, "active")'
                );
                foreach ($studentIds as $sid) {
                    $insertEnroll->execute([
                        'class_id' => $classId,
                        'student_id' => $sid,
                    ]);
                    StudentPayment::createPendingForEnrollment($classId, $sid, $studentFee);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            if ($googleEventId !== null) {
                $meetingService->deleteMeeting($teacherId, (string) $googleEventId);
            }
            throw $e;
        }

        logTimezoneFix([
            'event' => 'class_scheduled_to_utc',
            'class_id' => $classId,
            'teacher_id' => $teacherId,
            'timezone' => $timezone,
            'input_start' => $start,
            'input_end' => $end,
            'start_time_utc' => $startUtc,
            'end_time_utc' => $endUtc,
        ]);
        logTimezoneConversion([
            'event' => 'class_scheduled_to_utc',
            'class_id' => $classId,
            'teacher_id' => $teacherId,
            'timezone' => $timezone,
            'input_start' => $start,
            'input_end' => $end,
            'start_time_utc' => $startUtc,
            'end_time_utc' => $endUtc,
        ]);
        logMeetingHost([
            'event' => 'class_session_saved',
            'class_id' => $classId,
            'teacher_id' => $teacherId,
            'teacher_google_email' => $teacherGoogleEmail !== '' ? $teacherGoogleEmail : null,
            'google_event_id' => $googleEventId,
            'meeting_link' => $meetLink,
        ]);

        $mailResult = self::sendClassNotification($classId);
        $mailStatus = $mailResult['status'] ?? 'failed';
        $notices = [];
        if ($mailStatus === 'partial') {
            $notices[] = 'Some notification emails failed. Check logs.';
        } elseif ($mailStatus === 'failed') {
            $notices[] = 'Notification emails failed completely. Check logs.';
        }

        if ($calendarAjax) {
            header('Content-Type: application/json; charset=UTF-8');
            $messages = [];
            if ($mailStatus === 'success') {
                $messages[] = 'Class scheduled successfully. Notifications sent.';
            } else {
                $messages[] = 'Class scheduled.';
            }
            echo json_encode([
                'ok' => true,
                'class_id' => $classId,
                'warnings' => $notices,
                'messages' => $messages,
            ]);
            return;
        }

        if ($mailStatus === 'success') {
            if (!empty($notices)) {
                $_SESSION['flash_warning'] = implode(' ', $notices);
            } else {
                $_SESSION['flash_success'] = 'Class scheduled successfully. Notifications sent.';
            }
        } elseif ($mailStatus === 'partial') {
            $_SESSION['flash_warning'] = implode(' ', $notices);
        } else {
            $_SESSION['flash_warning'] = implode(' ', $notices);
        }

        $base = defined('BASE_PATH') ? BASE_PATH : '';
        header('Location: ' . $base . '/admin');
    }
}

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
require_once dirname(__DIR__) . '/lib/ClassRecurrenceHelper.php';
require_once dirname(__DIR__) . '/lib/RecurringSeriesService.php';
require_once dirname(__DIR__) . '/lib/EmailTemplate.php';
require_once dirname(__DIR__) . '/lib/Pagination.php';
require_once dirname(__DIR__) . '/models/RecurringOccurrence.php';

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
            $teacherGoogleEmail = (string) ($class['teacher_google_email'] ?? ($user['email'] ?? ''));
            $openMeetUrl = appendMeetAuthUser($target, $teacherGoogleEmail);
            View::render('classes/teacher_launch', [
                'pageTitle' => 'Launch Class',
                'class' => $class,
                'displayTimezone' => $displayTimezone,
                'teacherGoogleEmail' => $teacherGoogleEmail,
                'openMeetUrl' => $openMeetUrl,
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
               AND e.status = "active"
               AND u.status = "active"'
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
        $studentNames = array_values(array_filter(array_map(
            static fn(array $s): string => (string) ($s['name'] ?? ''),
            $students
        )));
        foreach ($recipients as $recipient) {
            try {
                $classTimezone = classScheduledTimezone($class, APP_TIMEZONE);
                $classTimezoneAbbr = schedulingTimezoneAbbreviation($classTimezone);
                $scheduledRange = formatClassTimeRange($class);
                $scheduledStart = formatClassScheduledAt($class, 'l M j, Y g:i A');
                $scheduledEnd = formatClassScheduledEndAt($class, 'g:i A');
                $recipientTimezone = normalizeTimezone((string) ($recipient['timezone'] ?? ''), $classTimezone);
                $startLocal = formatUtcForTimezone(classStartUtcValue($class), $recipientTimezone, 'l M j, Y g:i A');
                $endLocal = formatUtcForTimezone(classEndUtcValue($class), $recipientTimezone, 'g:i A');
                $recipientAbbr = schedulingTimezoneAbbreviation($recipientTimezone);
                $showRecipientLocal = $recipientTimezone !== $classTimezone;

                $meetingLink = self::resolveMeetingLink($class);
                $subject = EmailTemplate::subject('class_scheduled');
                $body = self::buildClassEmailTemplate(
                    (string) ($recipient['name'] ?? 'User'),
                    (string) $class['title'],
                    $scheduledRange,
                    $scheduledStart,
                    $scheduledEnd,
                    $classTimezoneAbbr,
                    $showRecipientLocal ? ($startLocal . ' – ' . $endLocal . ' ' . $recipientAbbr) : '',
                    (string) $class['teacher_name'],
                    $meetingLink,
                    (string) ($recipient['role'] ?? 'student'),
                    $studentNames,
                    (string) $class['title']
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

        require_once dirname(__DIR__) . '/lib/NotificationMailer.php';
        NotificationMailer::notifyAdminClassScheduled($class, $students);

        return $result;
    }

    /**
     * @param list<int> $studentIds
     * @param list<array{start: DateTimeImmutable, end: DateTimeImmutable}> $occurrenceSlots
     */
    private static function storeRecurringSeries(
        \PDO $pdo,
        int $teacherId,
        int $classMasterId,
        string $title,
        string $description,
        float $teacherRate,
        float $studentRate,
        string $timezone,
        array $studentIds,
        array $occurrenceSlots,
        string $frequency,
        ?string $recurrenceEndDate,
        ?int $occurrenceCount,
        bool $calendarAjax,
        string $startInput,
        string $endInput,
        array $recurrenceConfig = []
    ): void {
        $googleEventId = null;
        try {
            $pdo->beginTransaction();
            $result = RecurringSeriesService::createFromSchedule(
                $pdo,
                $teacherId,
                $classMasterId,
                $title,
                $description,
                $teacherRate,
                $studentRate,
                $timezone,
                $studentIds,
                $occurrenceSlots,
                $frequency,
                $recurrenceEndDate,
                $occurrenceCount
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            RecurringSeriesService::logRecurringSchedule([
                'event' => 'series_failed',
                'error' => $e->getMessage(),
                'teacher_id' => $teacherId,
                'frequency' => $frequency,
                'occurrence_count' => count($occurrenceSlots),
                'trace' => $e->getTraceAsString(),
            ]);
            if ($googleEventId !== null && $googleEventId !== '') {
                try {
                    (new GoogleCalendarMeetingService())->deleteMeeting($teacherId, $googleEventId);
                } catch (\Throwable $ignored) {
                }
            }
            if ($calendarAjax) {
                self::respondScheduleJson([
                    'success' => false,
                    'message' => 'Could not save recurring series: ' . $e->getMessage(),
                    'errors' => [$e->getMessage()],
                ], 500);
                return;
            }
            throw $e;
        }

        $seriesId = (int) $result['series_id'];
        $classId = (int) $result['class_session_id'];
        $occurrenceCountTotal = (int) $result['occurrence_count'];
        $meetLink = $result['meet_link'] ?? null;
        $googleEventId = $result['google_event_id'] ?? null;

        logTimezoneFix([
            'event' => 'recurring_series_scheduled',
            'series_id' => $seriesId,
            'class_id' => $classId,
            'teacher_id' => $teacherId,
            'timezone' => $timezone,
            'occurrence_count' => $occurrenceCountTotal,
        ]);

        $mailResult = self::sendRecurringSeriesNotification($seriesId);
        $mailStatus = $mailResult['status'] ?? 'failed';
        $notices = [];
        if ($mailStatus === 'failed') {
            $notices[] = 'Notification email failed. Check logs.';
        }

        if ($calendarAjax) {
            $message = $mailStatus === 'success'
                ? ($occurrenceCountTotal . ' class occurrences scheduled. One meeting invitation has been sent.')
                : ($occurrenceCountTotal . ' class occurrences scheduled in one recurring series.');
            if ($notices !== []) {
                $message .= ' ' . implode(' ', $notices);
            }
            self::respondScheduleJson([
                'success' => true,
                'message' => $message,
                'redirect_url' => self::defaultScheduleRedirectPath() . '?series=' . $seriesId,
                'class_id' => $classId,
                'series_id' => $seriesId,
                'occurrence_count' => $occurrenceCountTotal,
                'warnings' => $notices,
                'google_meet_created' => $meetLink !== null && $meetLink !== '',
                'email_sent' => $mailStatus === 'success',
            ]);
            return;
        }

        if ($mailStatus === 'success') {
            $_SESSION['flash_success'] = $occurrenceCountTotal . ' occurrences scheduled. One meeting invitation sent.';
        } else {
            $_SESSION['flash_warning'] = implode(' ', $notices);
        }
        redirectTo(self::defaultScheduleRedirectPath());
    }

    /**
     * @return array{sent:int,failed:int,total:int,status:string}
     */
    public static function sendRecurringSeriesNotification(int $seriesId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT rs.*, u.name AS teacher_name, u.email AS teacher_email, u.timezone AS teacher_timezone
             FROM recurring_series rs
             INNER JOIN users u ON u.id = rs.teacher_id
             WHERE rs.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $seriesId]);
        $series = $stmt->fetch();
        if (!$series) {
            return ['sent' => 0, 'failed' => 0, 'total' => 0, 'status' => 'failed'];
        }

        $studentsStmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email, u.timezone
             FROM recurring_series_students rss
             INNER JOIN users u ON u.id = rss.student_id
             WHERE rss.series_id = :sid AND u.status = "active"'
        );
        $studentsStmt->execute(['sid' => $seriesId]);
        $students = $studentsStmt->fetchAll() ?: [];

        $occCountStmt = $pdo->prepare('SELECT COUNT(*) FROM recurring_occurrences WHERE series_id = :sid');
        $occCountStmt->execute(['sid' => $seriesId]);
        $totalClasses = (int) ($occCountStmt->fetchColumn() ?: 0);

        $tz = (string) ($series['scheduled_timezone'] ?? $series['timezone'] ?? APP_TIMEZONE);
        $tzAbbr = schedulingTimezoneAbbreviation($tz);
        try {
            $seriesTz = new DateTimeZone($tz);
            $startDate = (new DateTimeImmutable((string) $series['start_date'], $seriesTz))->format('d-M-Y');
            $endDate = !empty($series['end_date'])
                ? (new DateTimeImmutable((string) $series['end_date'], $seriesTz))->format('d-M-Y')
                : $startDate;
            $startTime = (new DateTimeImmutable((string) $series['start_date'] . ' ' . (string) $series['start_time'], $seriesTz))->format('g:i A');
            $endTime = (new DateTimeImmutable((string) $series['start_date'] . ' ' . (string) $series['end_time'], $seriesTz))->format('g:i A');
        } catch (\Throwable $e) {
            $startDate = (string) ($series['start_date'] ?? '');
            $endDate = (string) ($series['end_date'] ?? $startDate);
            $startTime = (string) ($series['start_time'] ?? '');
            $endTime = (string) ($series['end_time'] ?? '');
        }
        $frequencyLabel = ucfirst((string) ($series['frequency'] ?? 'daily'));
        $meetingLink = (string) ($series['meeting_link'] ?? '');

        $recipients = [];
        if (!empty($series['teacher_email'])) {
            $recipients[] = [
                'name' => (string) ($series['teacher_name'] ?? 'Teacher'),
                'email' => (string) $series['teacher_email'],
                'role' => 'teacher',
            ];
        }
        foreach ($students as $student) {
            if (!empty($student['email'])) {
                $recipients[] = [
                    'name' => (string) ($student['name'] ?? 'Student'),
                    'email' => (string) $student['email'],
                    'role' => 'student',
                ];
            }
        }

        $studentNames = array_values(array_filter(array_map(
            static fn(array $s): string => (string) ($s['name'] ?? ''),
            $students
        )));

        $result = ['sent' => 0, 'failed' => 0, 'total' => count($recipients), 'status' => 'success'];
        $subject = EmailTemplate::subject('recurring_scheduled');
        $recurrenceLabel = ucfirst((string) ($series['frequency'] ?? 'daily'));

        foreach ($recipients as $recipient) {
            $studentList = $studentNames !== []
                ? implode(', ', $studentNames)
                : (string) ($recipient['name'] ?? '');
            $studentDisplay = ($recipient['role'] ?? '') === 'student'
                ? (string) ($recipient['name'] ?? '')
                : $studentList;
            $intro = '<p>Hi ' . htmlspecialchars((string) ($recipient['name'] ?? ''), ENT_QUOTES, 'UTF-8') . ',</p>'
                . '<p>Your recurring class series has been scheduled successfully.</p>';
            $rows = [
                'Student Name' => htmlspecialchars($studentDisplay, ENT_QUOTES, 'UTF-8'),
                'Teacher Name' => htmlspecialchars((string) ($series['teacher_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'Class Title' => htmlspecialchars((string) ($series['title'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'Recurring Type' => htmlspecialchars($recurrenceLabel, ENT_QUOTES, 'UTF-8'),
                'Start Date' => htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'),
                'End Date' => htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'),
                'Time' => htmlspecialchars($startTime . ' – ' . $endTime, ENT_QUOTES, 'UTF-8'),
                'Timezone' => htmlspecialchars($tzAbbr, ENT_QUOTES, 'UTF-8'),
                'Total Sessions' => (string) (int) $totalClasses,
                'Meeting Link' => $meetingLink !== ''
                    ? '<a href="' . htmlspecialchars($meetingLink, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($meetingLink, ENT_QUOTES, 'UTF-8') . '</a>'
                    : '—',
            ];
            $body = EmailTemplate::wrap(
                'Recurring Classes Scheduled Successfully',
                $intro,
                $rows,
                $meetingLink !== '' ? 'Join Class' : null,
                $meetingLink !== '' ? $meetingLink : null
            );
            try {
                $mailResponse = Mailer::send((string) $recipient['email'], $subject, $body, true);
                if (!empty($mailResponse['success'])) {
                    $result['sent']++;
                } else {
                    $result['failed']++;
                }
            } catch (\Throwable $e) {
                $result['failed']++;
            }
        }

        if ($result['failed'] > 0 && $result['sent'] > 0) {
            $result['status'] = 'partial';
        } elseif ($result['failed'] > 0) {
            $result['status'] = 'failed';
        }

        require_once dirname(__DIR__) . '/lib/NotificationMailer.php';
        NotificationMailer::notifyAdminClassScheduled([
            'title' => (string) ($series['title'] ?? ''),
            'teacher_name' => (string) ($series['teacher_name'] ?? ''),
            'meeting_link' => $meetingLink,
            'start_datetime' => $startDate,
            'scheduled_timezone' => $tz,
            'start_time_utc' => (string) ($series['start_date'] ?? ''),
        ], $students);

        return $result;
    }

    /**
     * @param list<string> $studentNames
     */
    private static function buildClassEmailTemplate(
        string $recipientName,
        string $classTitle,
        string $scheduledRange,
        string $scheduledStart,
        string $scheduledEnd,
        string $scheduledTimezoneAbbr,
        string $recipientLocalRange,
        string $teacherName,
        string $meetingLink,
        string $recipientRole = 'student',
        array $studentNames = [],
        string $subjectLabel = ''
    ): string {
        $safeRecipientName = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
        $studentList = $studentNames !== []
            ? htmlspecialchars(implode(', ', $studentNames), ENT_QUOTES, 'UTF-8')
            : ($recipientRole === 'student' ? $safeRecipientName : '—');
        $safeSubject = htmlspecialchars($subjectLabel !== '' ? $subjectLabel : $classTitle, ENT_QUOTES, 'UTF-8');
        $actionLabel = $recipientRole === 'teacher' ? 'Open Class' : 'Join Class';
        $classDate = htmlspecialchars($scheduledStart, ENT_QUOTES, 'UTF-8');
        $timeRange = htmlspecialchars($scheduledStart . ' – ' . $scheduledEnd, ENT_QUOTES, 'UTF-8');

        $intro = '<p>Hi ' . $safeRecipientName . ',</p><p>Your class has been scheduled successfully.</p>';
        $rows = [
            'Student Name' => $studentList,
            'Teacher Name' => htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8'),
            'Class Title' => $safeSubject,
            'Date' => $classDate,
            'Time' => $timeRange,
            'Timezone' => htmlspecialchars($scheduledTimezoneAbbr, ENT_QUOTES, 'UTF-8'),
        ];
        if ($recipientLocalRange !== '') {
            $rows['Your Local Time'] = htmlspecialchars($recipientLocalRange, ENT_QUOTES, 'UTF-8');
        }
        if ($meetingLink !== '') {
            $rows['Meeting Link'] = '<a href="' . htmlspecialchars($meetingLink, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($meetingLink, ENT_QUOTES, 'UTF-8') . '</a>';
        }

        return EmailTemplate::wrap(
            'Class Scheduled Successfully',
            $intro,
            $rows,
            $meetingLink !== '' ? $actionLabel : null,
            $meetingLink !== '' ? $meetingLink : null
        );
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
        $countSql = 'SELECT COUNT(*) FROM class_sessions cs INNER JOIN users u ON u.id = cs.teacher_id';
        if (!empty($where)) {
            $countSql .= ' WHERE ' . implode(' AND ', $where);
        }
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetchColumn() ?: 0);

        $req = Pagination::fromRequest();
        $sql .= ' ORDER BY cs.start_datetime DESC LIMIT :limit OFFSET :offset';
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $req['per_page'], \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $req['offset'], \PDO::PARAM_INT);
        $stmt->execute();
        $classes = $stmt->fetchAll() ?: [];
        $pagination = Pagination::meta($total, $req['page'], $req['per_page']);

        View::render('classes/index', [
            'pageTitle' => 'Classes',
            'classes' => $classes,
            'statusFilter' => $statusFilter,
            'pagination' => $pagination,
            'queryParams' => array_filter(['status' => $statusFilter !== '' ? $statusFilter : null]),
        ]);
    }

    public static function delete(): void
    {
        Auth::requireRole(['admin', 'teacher']);
        $user = Auth::user();
        $role = (string) ($user['role'] ?? '');
        $classId = (int) ($_POST['class_id'] ?? 0);

        if ($classId <= 0) {
            $_SESSION['flash_error'] = 'Invalid class ID.';
            redirectTo($_SERVER['HTTP_REFERER'] ?? '/classes');
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM class_sessions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $classId]);
        $class = $stmt->fetch();

        if (!$class) {
            $_SESSION['flash_error'] = 'Class not found.';
            redirectTo($_SERVER['HTTP_REFERER'] ?? '/classes');
            return;
        }

        if ($role === 'teacher' && (int) $class['teacher_id'] !== (int) ($user['id'] ?? 0)) {
            $_SESSION['flash_error'] = 'You do not have permission to delete this class.';
            redirectTo($_SERVER['HTTP_REFERER'] ?? '/classes');
            return;
        }

        $deleteScope = (string) ($_POST['delete_scope'] ?? 'current');
        $targetIds = [$classId];
        $targetOccurrenceIds = [];
        $seriesId = 0;
        $occurrenceId = (int) ($class['recurring_occurrence_id'] ?? 0);

        if ($occurrenceId > 0 && self::classIsInRecurrenceSeries($class, $pdo)) {
            $targetOccurrenceIds = RecurringOccurrence::idsFromScope($occurrenceId, $deleteScope, $pdo);
            $mapped = [];
            $mapStmt = $pdo->prepare('SELECT class_session_id, series_id FROM recurring_occurrences WHERE id = :id LIMIT 1');
            foreach ($targetOccurrenceIds as $oid) {
                $mapStmt->execute(['id' => $oid]);
                $occRow = $mapStmt->fetch();
                if ($occRow) {
                    $seriesId = (int) $occRow['series_id'];
                    $mappedId = (int) ($occRow['class_session_id'] ?? 0);
                    if ($mappedId > 0) {
                        $mapped[] = $mappedId;
                    }
                }
            }
            if ($mapped !== []) {
                $targetIds = $mapped;
            }
        } elseif ($deleteScope === 'all_future' && self::classIsInRecurrenceSeries($class, $pdo)) {
            $targetIds = ClassRecurrenceHelper::futureSeriesClassIds($classId, $pdo);
            if ($targetIds === []) {
                $targetIds = [$classId];
            }
        } elseif ($deleteScope === 'entire_series' && self::classIsInRecurrenceSeries($class, $pdo)) {
            $targetIds = ClassRecurrenceHelper::allSeriesClassIds($classId, $pdo);
            if ($targetIds === []) {
                $targetIds = [$classId];
            }
        }

        try {
            $pdo->beginTransaction();

            $delStmt = $pdo->prepare('DELETE FROM class_sessions WHERE id = :id');
            $meetingService = new GoogleCalendarMeetingService();
            
            $firstTargetClass = null;
            $studentsForEmail = [];
            
            foreach ($targetIds as $targetId) {
                $tStmt = $pdo->prepare('SELECT cs.*, u.name AS teacher_name, u.email AS teacher_email FROM class_sessions cs INNER JOIN users u ON u.id = cs.teacher_id WHERE cs.id = :id LIMIT 1');
                $tStmt->execute(['id' => $targetId]);
                $targetClass = $tStmt->fetch();
                if (!$targetClass) {
                    continue;
                }

                if ($firstTargetClass === null) {
                    $firstTargetClass = $targetClass;
                    $studentStmt = $pdo->prepare('SELECT u.name, u.email FROM enrollments e INNER JOIN users u ON u.id = e.student_id WHERE e.class_id = :class_id AND e.status = "active"');
                    $studentStmt->execute(['class_id' => $targetId]);
                    $studentsForEmail = $studentStmt->fetchAll() ?: [];
                }

                $delStmt->execute(['id' => $targetId]);

                if (!empty($targetClass['google_event_id'])) {
                    try {
                        $meetingService->deleteMeeting((int)$targetClass['teacher_id'], (string)$targetClass['google_event_id']);
                    } catch (\Throwable $e) {
                        error_log('Failed to delete Google Calendar event: ' . $e->getMessage());
                    }
                }
            }
            
            if ($targetOccurrenceIds !== []) {
                $delOccStmt = $pdo->prepare('DELETE FROM recurring_occurrences WHERE id = :id');
                foreach ($targetOccurrenceIds as $oid) {
                    $delOccStmt->execute(['id' => $oid]);
                }
                
                if ($deleteScope === 'entire_series' && $seriesId > 0) {
                    $cancelSeries = $pdo->prepare('UPDATE recurring_series SET status = "cancelled" WHERE id = :sid');
                    $cancelSeries->execute(['sid' => $seriesId]);
                }
            }

            $pdo->commit();

            if ($firstTargetClass) {
                require_once dirname(__DIR__) . '/lib/NotificationMailer.php';
                NotificationMailer::notifyClassAction('Cancelled', $firstTargetClass, $studentsForEmail);
            }

            $_SESSION['flash_success'] = count($targetIds) > 1 
                ? count($targetIds) . ' classes deleted successfully.' 
                : 'Class deleted successfully.';
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['flash_error'] = 'Failed to delete class: ' . $e->getMessage();
        }

        redirectTo($_SERVER['HTTP_REFERER'] ?? '/classes');
    }

    public static function updateStatus(): void
    {
        Auth::requireRole(['admin']);
        $pdo = Database::connection();
        $base = appWebPath();

        $classId = (int) ($_POST['class_id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if ($classId <= 0 || !in_array($status, ['scheduled', 'ongoing', 'completed', 'cancelled', 'rescheduled'], true)) {
            $_SESSION['flash_warning'] = 'Invalid class status update request.';
            redirectTo('/classes');
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
            redirectTo('/classes');
            return;
        }

        $stmt = $pdo->prepare('UPDATE class_sessions SET status = :status WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'id' => $classId,
        ]);
        $_SESSION['flash_success'] = 'Class status updated.';
        redirectTo('/classes');
    }

    public static function completed(): void
    {
        Auth::requireRole(['admin']);
        $pdo = Database::connection();
        $total = (int) ($pdo->query(
            'SELECT COUNT(*) FROM class_sessions cs WHERE cs.status = "completed"'
        )->fetchColumn() ?: 0);

        $req = Pagination::fromRequest();
        $stmt = $pdo->prepare(
            'SELECT cs.*, u.name AS teacher_name
             FROM class_sessions cs
             INNER JOIN users u ON u.id = cs.teacher_id
             WHERE cs.status = "completed"
             ORDER BY cs.completed_at DESC, cs.start_datetime DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $req['per_page'], \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $req['offset'], \PDO::PARAM_INT);
        $stmt->execute();
        $classes = $stmt->fetchAll() ?: [];
        $pagination = Pagination::meta($total, $req['page'], $req['per_page']);

        View::render('classes/completed', [
            'pageTitle' => 'Completed Classes',
            'classes' => $classes,
            'pagination' => $pagination,
            'queryParams' => [],
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
        $base = appWebPath();
        redirectTo('/classes');
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
        $base = appWebPath();
        if ($classId <= 0) {
            redirectTo('/classes');
            return;
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT cs.*, u.name AS teacher_name FROM class_sessions cs INNER JOIN users u ON u.id = cs.teacher_id WHERE cs.id = :id LIMIT 1');
        $stmt->execute(['id' => $classId]);
        $class = $stmt->fetch();
        if (!$class) {
            redirectTo('/classes');
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
            'isRecurringSeries' => self::classIsInRecurrenceSeries($class, $pdo),
        ]);
    }

    public static function update(): void
    {
        Auth::requireRole(['admin', 'teacher']);
        $user = Auth::user();
        $role = (string) ($user['role'] ?? '');
        $base = appWebPath();
        $classId = (int) ($_POST['class_id'] ?? 0);
        if ($classId <= 0) {
            redirectTo('/classes');
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM class_sessions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $classId]);
        $class = $stmt->fetch();
        if (!$class) {
            redirectTo('/classes');
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
                'isRecurringSeries' => self::classIsInRecurrenceSeries($class, $pdo),
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

        $editScope = (string) ($_POST['edit_scope'] ?? 'current');
        $targetIds = [$classId];
        $occurrenceId = (int) ($class['recurring_occurrence_id'] ?? 0);
        if ($occurrenceId > 0 && self::classIsInRecurrenceSeries($class, $pdo)) {
            $occurrenceScopeIds = RecurringOccurrence::idsFromScope($occurrenceId, $editScope, $pdo);
            $mapped = [];
            $mapStmt = $pdo->prepare('SELECT class_session_id FROM recurring_occurrences WHERE id = :id LIMIT 1');
            foreach ($occurrenceScopeIds as $oid) {
                $mapStmt->execute(['id' => $oid]);
                $mappedId = (int) ($mapStmt->fetchColumn() ?: 0);
                if ($mappedId > 0) {
                    $mapped[] = $mappedId;
                }
            }
            if ($mapped !== []) {
                $targetIds = $mapped;
            }
        } elseif ($editScope === 'all_future' && self::classIsInRecurrenceSeries($class, $pdo)) {
            $targetIds = ClassRecurrenceHelper::futureSeriesClassIds($classId, $pdo);
            if ($targetIds === []) {
                $targetIds = [$classId];
            }
        } elseif ($editScope === 'entire_series' && self::classIsInRecurrenceSeries($class, $pdo)) {
            $targetIds = ClassRecurrenceHelper::allSeriesClassIds($classId, $pdo);
            if ($targetIds === []) {
                $targetIds = [$classId];
            }
        }

        $oldStartTs = strtotime((string) (classStartUtcValue($class) ?? $class['start_datetime']) . ' UTC');
        $deltaSec = $startUtc->getTimestamp() - ($oldStartTs ?: $startUtc->getTimestamp());
        $durationSec = $durationMin * 60;
        $meetingService = new GoogleCalendarMeetingService();

        $firstTargetClass = null;

        foreach ($targetIds as $targetId) {
            $tStmt = $pdo->prepare('SELECT cs.*, u.name AS teacher_name, u.email AS teacher_email FROM class_sessions cs INNER JOIN users u ON u.id = cs.teacher_id WHERE cs.id = :id LIMIT 1');
            $tStmt->execute(['id' => $targetId]);
            $targetClass = $tStmt->fetch();
            if (!$targetClass) {
                continue;
            }

            if ($firstTargetClass === null) {
                $firstTargetClass = $targetClass;
            }

            if ((int) $targetId === $classId) {
                $targetStartUtcValue = $startUtcValue;
                $targetEndUtcValue = $endUtcValue;
                $targetMeetingLink = $nextMeetingLink;
                $targetMeetingCode = $nextMeetingCode;
            } else {
                $targetOldStartTs = strtotime((string) (classStartUtcValue($targetClass) ?? $targetClass['start_datetime']) . ' UTC');
                $targetNewStartTs = ($targetOldStartTs ?: $startUtc->getTimestamp()) + $deltaSec;
                $targetStartUtcValue = gmdate('Y-m-d H:i:s', $targetNewStartTs);
                $targetEndUtcValue = gmdate('Y-m-d H:i:s', $targetNewStartTs + $durationSec);
                $targetMeetingLink = (string) ($targetClass['meeting_link'] ?? '');
                $targetMeetingCode = self::extractGoogleMeetCode($targetMeetingLink);
            }

            if (!empty($targetClass['google_event_id']) && !empty($targetClass['teacher_id'])) {
                $meetingService->updateMeeting(
                    (int) $targetClass['teacher_id'],
                    (string) $targetClass['google_event_id'],
                    utcToTimezoneIso8601($targetStartUtcValue, 'UTC'),
                    utcToTimezoneIso8601($targetEndUtcValue, 'UTC'),
                    'UTC',
                    (string) ($targetClass['title'] ?? '')
                );
            }

            $targetMeetingCodeChanged = $targetMeetingCode !== self::extractGoogleMeetCode((string) ($targetClass['meeting_link'] ?? ''));
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
                     meeting_participant_count = CASE WHEN status = "completed" THEN meeting_participant_count ELSE NULL END,
                     teacher_joined_at = CASE WHEN status = "completed" THEN teacher_joined_at ELSE NULL END,
                     teacher_join_delay_minutes = CASE WHEN status = "completed" THEN teacher_join_delay_minutes ELSE NULL END,
                     student_joined_at = CASE WHEN status = "completed" THEN student_joined_at ELSE NULL END,
                     actual_start_time = CASE WHEN status = "completed" THEN actual_start_time ELSE NULL END,
                     actual_end_time = CASE WHEN status = "completed" THEN actual_end_time ELSE NULL END,
                     actual_duration = CASE WHEN status = "completed" THEN actual_duration ELSE NULL END,
                     actual_duration_minutes = CASE WHEN status = "completed" THEN actual_duration_minutes ELSE NULL END,
                     completed_at = CASE WHEN status = "completed" THEN completed_at ELSE NULL END,
                     status = IF(status = "completed", "completed", "rescheduled")
                 WHERE id = :id'
            );
            $upd->execute([
                'start_dt' => $targetStartUtcValue,
                'scheduled_time_utc' => $targetStartUtcValue,
                'start_time_utc' => $targetStartUtcValue,
                'end_dt' => $targetEndUtcValue,
                'end_time_utc' => $targetEndUtcValue,
                'timezone' => $timezone,
                'scheduled_timezone' => $timezone,
                'payout' => $payoutAmount,
                'student_fee' => $studentFee,
                'meeting_link' => $targetMeetingLink !== '' ? $targetMeetingLink : null,
                'google_meeting_code' => $targetMeetingCode,
                'google_meet_space_name' => $targetMeetingCodeChanged ? null : ($targetClass['google_meet_space_name'] ?? null),
                'google_conference_id' => (string) ($targetClass['status'] ?? '') === 'completed' ? ($targetClass['google_conference_id'] ?? null) : null,
                'meeting_live_status' => (string) ($targetClass['status'] ?? '') === 'completed'
                    ? (string) ($targetClass['meeting_live_status'] ?? 'ended')
                    : 'pending',
                'id' => $targetId,
            ]);

            if (!empty($targetClass['recurring_occurrence_id'])) {
                $roUpd = $pdo->prepare(
                    'UPDATE recurring_occurrences 
                     SET scheduled_start_utc = :start_utc,
                         scheduled_end_utc = :end_utc,
                         status = IF(status = "completed", "completed", "rescheduled")
                     WHERE id = :oid'
                );
                $roUpd->execute([
                    'start_utc' => $targetStartUtcValue,
                    'end_utc' => $targetEndUtcValue,
                    'oid' => $targetClass['recurring_occurrence_id'],
                ]);
            }
        }

        logTimezoneFix([
            'event' => 'class_rescheduled_to_utc',
            'class_id' => $classId,
            'timezone' => $timezone,
            'input_start' => $startRaw,
            'duration_minutes' => $durationMin,
            'start_time_utc' => $startUtcValue,
            'end_time_utc' => $endUtcValue,
            'edit_scope' => $editScope,
            'updated_count' => count($targetIds),
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

        if ($firstTargetClass) {
            $studentStmt = $pdo->prepare('SELECT u.name, u.email FROM enrollments e INNER JOIN users u ON u.id = e.student_id WHERE e.class_id = :class_id AND e.status = "active"');
            $studentStmt->execute(['class_id' => $firstTargetClass['id']]);
            $students = $studentStmt->fetchAll() ?: [];
            
            require_once dirname(__DIR__) . '/lib/NotificationMailer.php';
            NotificationMailer::notifyClassAction('Updated', $firstTargetClass, $students);
        }

        $_SESSION['flash_success'] = count($targetIds) > 1
            ? count($targetIds) . ' classes in the series were updated.'
            : 'Class updated successfully.';
        redirectTo('/classes');
    }

    public static function store(): void
    {
        Auth::requireRole(['admin']);
        $pdo = Database::connection();
        $calendarAjax = !empty($_POST['calendar_ajax']);

        if (function_exists('logClassScheduleLive')) {
            logClassScheduleLive([
                'event' => 'schedule_request',
                'admin_id' => Auth::userId(),
                'calendar_ajax' => $calendarAjax,
                'teacher_id' => (int) ($_POST['teacher_id'] ?? 0),
                'student_ids' => $_POST['student_ids'] ?? [],
                'payload' => [
                    'title' => $_POST['title'] ?? '',
                    'start_datetime' => $_POST['start_datetime'] ?? '',
                    'end_datetime' => $_POST['end_datetime'] ?? '',
                    'timezone' => $_POST['timezone'] ?? '',
                    'recurrence_rule' => $_POST['recurrence_rule'] ?? 'none',
                    'recurrence_end_mode' => $_POST['recurrence_end_mode'] ?? '',
                    'recurrence_until' => $_POST['recurrence_until'] ?? '',
                    'recurrence_count' => $_POST['recurrence_count'] ?? '',
                ],
                'request_uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
                'base_path' => appWebPath(),
            ]);
        }

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
        } elseif (!User::isActive(User::findById($teacherId))) {
            $errors[] = 'Selected teacher is inactive. Activate the account or choose another teacher.';
        }
        if ($teacherId > 0 && $studentIds !== []) {
            $unmapped = TeacherStudent::filterUnmappedStudentIds($teacherId, $studentIds);
            if ($unmapped !== []) {
                $errors[] = 'One or more selected students are not mapped to this teacher. Link them under Admin → Teacher-Students, then refresh the student list.';
            }
            foreach ($studentIds as $studentId) {
                if (!User::isActive(User::findById($studentId))) {
                    $errors[] = 'One or more selected students are inactive.';
                    break;
                }
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
                self::respondScheduleJson([
                    'success' => false,
                    'message' => implode(' ', $errors),
                    'errors' => $errors,
                ], 422);
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
                self::respondScheduleJson([
                    'success' => false,
                    'message' => $teacherGoogleError,
                    'errors' => [$teacherGoogleError],
                ], 422);
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

        $recurrence = ClassRecurrenceHelper::parseFromPost($_POST, $startDt, $endDt, $timezone);
        $normalizedSlot = ClassRecurrenceHelper::normalizeSlotForRecurrence($startDt, $endDt, $recurrence['rule']);
        $startDt = $normalizedSlot['start'];
        $endDt = $normalizedSlot['end'];
        if ($recurrence['end_date'] === null && $normalizedSlot['inferred_until'] !== null) {
            $recurrence['end_date'] = $normalizedSlot['inferred_until'];
        }

        $occurrenceSlots = ClassRecurrenceHelper::buildOccurrencesFromConfig($startDt, $endDt, $recurrence);

        if ($recurrence['rule'] !== 'none') {
            self::storeRecurringSeries(
                $pdo,
                $teacherId,
                $classMasterId,
                $title,
                $description,
                $payoutAmount,
                $studentFee,
                $timezone,
                $studentIds,
                $occurrenceSlots,
                (string) ($recurrence['frequency_db'] ?? 'daily'),
                $recurrence['end_date'],
                $recurrence['count'],
                $calendarAjax,
                $start,
                $end,
                $recurrence
            );
            return;
        }

        $meetingService = new GoogleCalendarMeetingService();
        $meetTrackingService = new GoogleMeetLiveTrackingService();
        $attendeeEmails = self::studentEmailsForIds($studentIds);
        $teacherGoogleRowForRec = TeacherGoogleAccount::findByTeacherId($teacherId);
        $recordingEnabledInsert = TeacherGoogleAccount::recordingSupportedFromAccountRow($teacherGoogleRowForRec) ? 1 : 0;
        $teacherGoogleAccount = TeacherGoogleAccount::getCredentialsForTeacher($teacherId);
        $teacherGoogleEmailDefault = (string) ($teacherGoogleAccount['google_email'] ?? '');

        $plannedMeets = [];
        try {
            $slot = $occurrenceSlots[0];
            $slotStartUtc = $slot['start']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $slotEndUtc = $slot['end']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $meeting = $meetingService->createMeeting(
                $teacherId,
                utcToTimezoneIso8601($slotStartUtc, $timezone),
                utcToTimezoneIso8601($slotEndUtc, $timezone),
                $timezone,
                $title,
                $attendeeEmails
            );
            $meetLink = (string) ($meeting['meet_link'] ?? '');
            $googleMeetSpaceName = null;
            try {
                $spaceMeta = $meetTrackingService->describeSpaceForMeetingLink($teacherId, $meetLink);
                $googleMeetSpaceName = is_array($spaceMeta) ? ($spaceMeta['name'] ?? null) : null;
            } catch (\Throwable $ignored) {
                $googleMeetSpaceName = null;
            }
            $plannedMeets[] = [
                'start_utc' => $slotStartUtc,
                'end_utc' => $slotEndUtc,
                'google_event_id' => $meeting['event_id'] ?? null,
                'meet_link' => $meetLink !== '' ? $meetLink : null,
                'google_meeting_code' => self::extractGoogleMeetCode($meetLink),
                'google_meet_space_name' => $googleMeetSpaceName,
                'teacher_google_email' => (string) ($meeting['organizer_email'] ?? $teacherGoogleEmailDefault),
            ];
        } catch (\Throwable $e) {
            foreach ($plannedMeets as $planned) {
                if (!empty($planned['google_event_id'])) {
                    try {
                        $meetingService->deleteMeeting($teacherId, (string) $planned['google_event_id']);
                    } catch (\Throwable $ignored) {
                    }
                }
            }
            $errorMessage = $e->getMessage();
            if (function_exists('logClassScheduleLive')) {
                logClassScheduleLive([
                    'event' => 'google_meet_failed',
                    'teacher_id' => $teacherId,
                    'error' => $errorMessage,
                ]);
            }
            if ($calendarAjax) {
                self::respondScheduleJson([
                    'success' => false,
                    'message' => $errorMessage,
                    'errors' => [$errorMessage],
                ], 422);
                return;
            }
            View::render('classes/create', [
                'pageTitle' => 'Schedule Class',
                'teachers' => User::allTeachers(),
                'students' => self::studentsForScheduleForm($_POST),
                'classTypes' => ClassMaster::allActive(),
                'errors' => [$errorMessage],
                'old' => $_POST,
            ]);
            return;
        }

        $classId = 0;
        $meetLink = null;
        $googleEventId = null;
        $teacherGoogleEmail = $teacherGoogleEmailDefault;
        $createdEventIds = [];
        $pdo->beginTransaction();
        try {
            $recurrenceRule = null;
            $recurrenceEndDate = null;
            $createdClassIds = [];
            foreach ($plannedMeets as $index => $planned) {
                $saved = self::persistClassOccurrence(
                    $pdo,
                    $meetingService,
                    $meetTrackingService,
                    $teacherId,
                    $classMasterId,
                    $title,
                    $description,
                    $payoutAmount,
                    $studentFee,
                    $planned['start_utc'],
                    $planned['end_utc'],
                    $timezone,
                    $studentIds,
                    $planned['google_event_id'],
                    $planned['meet_link'],
                    $planned['google_meet_space_name'],
                    $planned['google_meeting_code'],
                    $planned['teacher_google_email'],
                    $recordingEnabledInsert,
                    null,
                    null,
                    null
                );
                $classId = $saved['class_id'];
                $meetLink = $saved['meet_link'];
                $googleEventId = $saved['google_event_id'];
                $teacherGoogleEmail = $planned['teacher_google_email'];
                if (!empty($saved['google_event_id'])) {
                    $createdEventIds[] = (string) $saved['google_event_id'];
                }
                $createdClassIds[] = (int) $saved['class_id'];
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            foreach ($createdEventIds as $eventId) {
                try {
                    $meetingService->deleteMeeting($teacherId, $eventId);
                } catch (\Throwable $ignored) {
                }
            }
            foreach ($plannedMeets as $planned) {
                $eventId = (string) ($planned['google_event_id'] ?? '');
                if ($eventId !== '' && !in_array($eventId, $createdEventIds, true)) {
                    try {
                        $meetingService->deleteMeeting($teacherId, $eventId);
                    } catch (\Throwable $ignored) {
                    }
                }
            }
            if ($calendarAjax) {
                self::logClassSchedule([
                    'event' => 'class_schedule_failed',
                    'teacher_id' => $teacherId,
                    'error' => $e->getMessage(),
                ]);
                self::respondScheduleJson([
                    'success' => false,
                    'message' => 'Could not save class: ' . $e->getMessage(),
                    'errors' => [$e->getMessage()],
                ], 500);
                return;
            }
            throw $e;
        }

        $startUtc = (string) ($plannedMeets[0]['start_utc'] ?? '');
        $endUtc = (string) ($plannedMeets[0]['end_utc'] ?? '');
        $occurrenceCount = count($plannedMeets);

        logTimezoneFix([
            'event' => 'class_scheduled_to_utc',
            'class_id' => $classId,
            'teacher_id' => $teacherId,
            'timezone' => $timezone,
            'input_start' => $start,
            'input_end' => $end,
            'start_time_utc' => $startUtc,
            'end_time_utc' => $endUtc,
            'occurrence_count' => $occurrenceCount,
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

        $mailResult = ['status' => 'success', 'sent' => 0, 'failed' => 0];
        foreach ($createdClassIds ?? [$classId] as $notifyClassId) {
            $singleMail = self::sendClassNotification((int) $notifyClassId);
            if (($singleMail['status'] ?? '') !== 'success') {
                $mailResult['status'] = $singleMail['status'] ?? 'failed';
            }
            $mailResult['sent'] += (int) ($singleMail['sent'] ?? 0);
            $mailResult['failed'] += (int) ($singleMail['failed'] ?? 0);
        }
        $mailStatus = $mailResult['status'] ?? 'failed';
        $notices = [];
        if ($mailStatus === 'partial') {
            $notices[] = 'Some notification emails failed. Check logs.';
        } elseif ($mailStatus === 'failed') {
            $notices[] = 'Notification emails failed completely. Check logs.';
        }

        if ($calendarAjax) {
            if ($mailStatus === 'success') {
                $message = $occurrenceCount > 1
                    ? ($occurrenceCount . ' recurring classes scheduled. Meeting invitations have been sent.')
                    : 'Meeting invitation has been sent.';
            } else {
                $message = $occurrenceCount > 1
                    ? ($occurrenceCount . ' recurring classes scheduled.')
                    : 'Class scheduled successfully.';
            }
            if ($notices !== []) {
                $message .= ' ' . implode(' ', $notices);
            }

            $redirectUrl = self::defaultScheduleRedirectPath() . '?scheduled=' . $classId;
            $liveLog = [
                'event' => 'class_scheduled',
                'class_id' => $classId,
                'teacher_id' => $teacherId,
                'student_ids' => $studentIds,
                'google_meet_created' => $meetLink !== null && $meetLink !== '',
                'google_event_id' => $googleEventId,
                'meeting_link' => $meetLink,
                'email_sent' => $mailStatus === 'success',
                'email_status' => $mailStatus,
                'email_result' => $mailResult,
                'redirect_url' => $redirectUrl,
                'controller_response' => 'success',
            ];
            self::logClassSchedule($liveLog);
            if (function_exists('logClassScheduleLive')) {
                logClassScheduleLive($liveLog);
            }
            self::respondScheduleJson([
                'success' => true,
                'message' => $message,
                'redirect_url' => $redirectUrl,
                'class_id' => $classId,
                'warnings' => $notices,
                'google_meet_created' => $meetLink !== null && $meetLink !== '',
                'email_sent' => $mailStatus === 'success',
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

        redirectTo(self::defaultScheduleRedirectPath());
    }

    private static function defaultScheduleRedirectPath(): string
    {
        $target = strtolower(trim((string) ($_POST['redirect_to'] ?? 'calendar')));

        if ($target === 'classes') {
            return '/classes';
        }

        return '/admin/calendar';
    }

    /** @deprecated Use defaultScheduleRedirectPath() */
    private static function defaultScheduleRedirectUrl(): string
    {
        return path(self::defaultScheduleRedirectPath());
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function logClassSchedule(array $context): void
    {
        if (!function_exists('writeStructuredLog')) {
            return;
        }

        writeStructuredLog('class_schedule.log', $context);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function respondScheduleJson(array $payload, int $httpStatus = 200): void
    {
        http_response_code($httpStatus);
        header('Content-Type: application/json; charset=UTF-8');

        if (!isset($payload['success'])) {
            $payload['success'] = !empty($payload['ok']);
        }
        if (!isset($payload['ok'])) {
            $payload['ok'] = !empty($payload['success']);
        }

        $logPayload = array_merge(['event' => 'schedule_json_response', 'http_status' => $httpStatus], $payload);
        self::logClassSchedule($logPayload);
        if (function_exists('logClassScheduleLive')) {
            logClassScheduleLive($logPayload);
        }

        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string, mixed> $class
     */
    private static function classIsInRecurrenceSeries(array $class, \PDO $pdo): bool
    {
        if ((int) ($class['recurring_series_id'] ?? 0) > 0) {
            return true;
        }
        if ((string) ($class['recurrence_rule'] ?? '') !== '' && (string) ($class['recurrence_rule'] ?? '') !== 'none') {
            return true;
        }
        if ((int) ($class['recurrence_parent_id'] ?? 0) > 0) {
            return true;
        }
        $chk = $pdo->prepare('SELECT 1 FROM class_sessions WHERE recurrence_parent_id = :id LIMIT 1');
        $chk->execute(['id' => (int) ($class['id'] ?? 0)]);

        return (bool) $chk->fetchColumn();
    }

    /**
     * @param list<int> $studentIds
     * @return array{class_id: int, google_event_id: ?string, meet_link: ?string}
     */
    private static function persistClassOccurrence(
        \PDO $pdo,
        GoogleCalendarMeetingService $meetingService,
        GoogleMeetLiveTrackingService $meetTrackingService,
        int $teacherId,
        int $classMasterId,
        string $title,
        string $description,
        float $payoutAmount,
        float $studentFee,
        string $startUtc,
        string $endUtc,
        string $timezone,
        array $studentIds,
        ?string $googleEventId,
        ?string $meetLink,
        ?string $googleMeetSpaceName,
        ?string $googleMeetingCode,
        string $teacherGoogleEmail,
        int $recordingEnabledInsert,
        ?int $recurrenceParentId,
        ?string $recurrenceRule,
        ?string $recurrenceEndDate
    ): array {
        $insertClass = $pdo->prepare(
            'INSERT INTO class_sessions
                (teacher_id, class_master_id, title, description, payout_amount, student_fee,
                 start_datetime, scheduled_time_utc, start_time_utc, end_datetime, end_time_utc,
                 timezone, scheduled_timezone, meeting_link, teacher_google_email, google_meet_space_name,
                 google_meeting_code, meeting_live_status, status, recording_enabled,
                 recurrence_parent_id, recurrence_rule, recurrence_end_date)
             VALUES
                (:teacher_id, :class_master_id, :title, :description, :payout_amount, :student_fee,
                 :start_datetime, :scheduled_time_utc, :start_time_utc, :end_datetime, :end_time_utc,
                 :timezone, :scheduled_timezone, :meeting_link, :teacher_google_email, :google_meet_space_name,
                 :google_meeting_code, "pending", "scheduled", :recording_enabled,
                 :recurrence_parent_id, :recurrence_rule, :recurrence_end_date)'
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
            'recurrence_parent_id' => $recurrenceParentId,
            'recurrence_rule' => $recurrenceRule,
            'recurrence_end_date' => $recurrenceEndDate,
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

        if ($studentIds !== []) {
            $insertEnroll = $pdo->prepare(
                'INSERT INTO enrollments (class_id, student_id, status) VALUES (:class_id, :student_id, "active")'
            );
            foreach ($studentIds as $sid) {
                $insertEnroll->execute(['class_id' => $classId, 'student_id' => $sid]);
                StudentPayment::createPendingForEnrollment($classId, $sid, $studentFee);
            }
        }

        return [
            'class_id' => $classId,
            'google_event_id' => $googleEventId,
            'meet_link' => $meetLink,
        ];
    }
}

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/models/ClassSession.php';
require_once dirname(__DIR__) . '/models/RecurringOccurrence.php';
require_once dirname(__DIR__) . '/models/User.php';
require_once dirname(__DIR__) . '/models/ClassMaster.php';
require_once dirname(__DIR__) . '/models/TeacherStudent.php';

/**
 * FullCalendar UI + JSON feed for class_sessions.
 */
class CalendarController
{
    public static function adminPage(): void
    {
        Auth::requireRole(['admin']);
        $teachers = User::allTeachers();
        $initialTeacherId = $teachers !== [] ? (int) ($teachers[0]['id'] ?? 0) : 0;
        $students = $initialTeacherId > 0 ? TeacherStudent::studentsForTeacher($initialTeacherId) : [];
        $classTypes = [];
        try {
            $classTypes = ClassMaster::allActive();
        } catch (\Throwable $e) {
            $classTypes = [];
        }

        View::render('calendar/index', [
            'pageTitle' => 'Class Calendar (Admin)',
            'calendarRole' => 'admin',
            'canSchedule' => true,
            'teachers' => $teachers,
            'students' => $students,
            'classTypes' => $classTypes,
        ]);
    }

    public static function teacherPage(): void
    {
        Auth::requireRole(['teacher']);
        View::render('calendar/index', [
            'pageTitle' => 'Class Calendar',
            'calendarRole' => 'teacher',
            'canSchedule' => false,
            'teachers' => [],
            'students' => [],
            'classTypes' => [],
        ]);
    }

    public static function studentPage(): void
    {
        Auth::requireRole(['student']);
        View::render('calendar/index', [
            'pageTitle' => 'Class Calendar',
            'calendarRole' => 'student',
            'canSchedule' => false,
            'teachers' => [],
            'students' => [],
            'classTypes' => [],
        ]);
    }

    /** GET /calendar/events or get_classes.php — JSON for FullCalendar. */
    public static function serveEventsJson(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        Auth::startSession();

        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $user = Auth::user();
        $role = (string) ($user['role'] ?? '');
        if (!in_array($role, ['admin', 'teacher', 'student'], true)) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            return;
        }

        $startRaw = $_GET['start'] ?? '';
        $endRaw = $_GET['end'] ?? '';
        if ($startRaw === '' || $endRaw === '') {
            http_response_code(400);
            echo json_encode(['error' => 'start and end query parameters are required']);
            return;
        }

        try {
            $startDt = new DateTimeImmutable($startRaw);
            $endDt = new DateTimeImmutable($endRaw);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid start/end datetime']);
            return;
        }

        $rangeStartUtc = $startDt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $rangeEndUtc = $endDt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $selectedTimezone = normalizeTimezone(
            (string) ($_GET['timezone'] ?? resolveUserTimezone($user, APP_TIMEZONE)),
            resolveUserTimezone($user, APP_TIMEZONE)
        );

        $ft = isset($_GET['teacher_id']) ? (int) $_GET['teacher_id'] : 0;
        $fs = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;
        $filterTeacher = $role === 'admin' && $ft > 0 ? $ft : null;
        $filterStudent = $role === 'admin' && $fs > 0 ? $fs : null;

        $rows = ClassSession::findCalendarEvents(
            $rangeStartUtc,
            $rangeEndUtc,
            $role,
            (int) ($user['id'] ?? 0),
            $filterTeacher,
            $filterStudent
        );

        $recurringRows = RecurringOccurrence::findCalendarEvents(
            $rangeStartUtc,
            $rangeEndUtc,
            $role,
            (int) ($user['id'] ?? 0),
            $filterTeacher,
            $filterStudent
        );

        $rows = array_merge($rows, $recurringRows);

        $events = [];
        foreach ($rows as $row) {
            $ev = self::rowToFullCalendarEvent($row, $role, $selectedTimezone);
            if ($ev !== null) {
                $events[] = $ev;
            }
        }

        logTimezoneFix([
            'event' => 'calendar_events_rendered',
            'viewer_role' => $role,
            'viewer_id' => (int) ($user['id'] ?? 0),
            'selected_timezone' => $selectedTimezone,
            'range_start_utc' => $rangeStartUtc,
            'range_end_utc' => $rangeEndUtc,
            'event_count' => count($events),
        ]);
        logTimezoneConversion([
            'event' => 'calendar_events_rendered',
            'viewer_role' => $role,
            'viewer_id' => (int) ($user['id'] ?? 0),
            'selected_timezone' => $selectedTimezone,
            'range_start_utc' => $rangeStartUtc,
            'range_end_utc' => $rangeEndUtc,
            'event_count' => count($events),
        ]);

        echo json_encode($events, JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>|null
     */
    private static function rowToFullCalendarEvent(array $row, string $viewerRole, string $selectedTimezone): ?array
    {
        $status = (string) ($row['status'] ?? 'scheduled');
        $colors = self::colorsForStatus($status);

        $startUtc = classStartUtcValue($row);
        $endUtc = classEndUtcValue($row);
        $startDt = utcDateTimeImmutable($startUtc);
        $endDt = utcDateTimeImmutable($endUtc);
        if ($startDt === null || $endDt === null || $endDt <= $startDt) {
            return null;
        }

        $durMin = (int) round(($endDt->getTimestamp() - $startDt->getTimestamp()) / 60);

        $title = (string) ($row['title'] ?? 'Class');
        if ($durMin > 0) {
            $title .= ' (' . $durMin . 'm)';
        }

        $startLocal = formatUtcForTimezone($startUtc, $selectedTimezone, 'd M Y g:i A');
        $endLocal = formatUtcForTimezone($endUtc, $selectedTimezone, 'd M Y g:i A');
        $scheduledTimezone = classScheduledTimezone($row, APP_TIMEZONE);
        $scheduledTimezoneLabel = formatClassScheduledTimezoneLabel($row);
        $scheduledStartLocal = formatUtcForTimezone($startUtc, $scheduledTimezone, 'd M Y g:i A');
        $scheduledEndLocal = formatUtcForTimezone($endUtc, $scheduledTimezone, 'g:i A');
        $actualStartLocal = formatClassActualAt($row, 'start', $scheduledTimezone, 'd M Y h:i A T');
        $actualEndLocal = formatClassActualAt($row, 'end', $scheduledTimezone, 'd M Y h:i A T');
        $actualDuration = formatDurationMinutes(classActualDurationMinutes($row), '');
        $teacher = (string) ($row['teacher_name'] ?? '');
        $students = (string) ($row['student_names'] ?? '');
        $tooltip = $title . "\n"
            . 'Status: ' . $status . "\n"
            . 'Teacher: ' . $teacher . "\n"
            . ($students !== '' ? ('Students: ' . $students . "\n") : '')
            . 'Class time: ' . $scheduledStartLocal . ' – ' . $scheduledEndLocal . ' ' . $scheduledTimezoneLabel . "\n"
            . 'Calendar view (' . $selectedTimezone . '): ' . $startLocal . ' – ' . $endLocal;

        $classId = (int) ($row['id'] ?? 0);
        $base = appWebPath();
        $trackJoin = $base . '/join-class?class_id=' . $classId;
        $directMeetLink = !empty($row['meeting_link']) ? (string) $row['meeting_link'] : '';

        $recordingUrl = (string) ($row['recording_url'] ?? '');
        if ($viewerRole === 'student' && (string) ($row['visible_to_student'] ?? 'no') !== 'yes') {
            $recordingUrl = '';
        }

        return [
            'id' => (string) $classId,
            'title' => $title,
            'start' => utcToTimezoneIso8601($startUtc, $selectedTimezone),
            'end' => utcToTimezoneIso8601($endUtc, $selectedTimezone),
            'backgroundColor' => $colors['bg'],
            'borderColor' => $colors['border'],
            'textColor' => '#ffffff',
            'extendedProps' => [
                'status' => $status,
                'teacher_name' => $teacher,
                'student_names' => $students,
                'description' => (string) ($row['description'] ?? ''),
                'tooltip' => $tooltip,
                'join_student' => $trackJoin,
                'join_teacher' => $trackJoin,
                'join_track' => $trackJoin,
                'direct_meet_link' => $directMeetLink,
                'recording_url' => $recordingUrl,
                'recording_enabled' => (int) ($row['recording_enabled'] ?? 0),
                'recording_visible_to_student' => (string) ($row['visible_to_student'] ?? 'no'),
                'class_id' => $classId,
                'series_id' => (int) ($row['recurring_series_id'] ?? 0),
                'occurrence_id' => (int) ($row['recurring_occurrence_id'] ?? 0),
                'is_recurring_occurrence' => !empty($row['is_recurring_occurrence']),
                'viewer_role' => $viewerRole,
                'selected_timezone' => $selectedTimezone,
                'scheduled_timezone' => $scheduledTimezone,
                'scheduled_timezone_label' => $scheduledTimezoneLabel,
                'teacher_google_email' => (string) ($row['teacher_google_email'] ?? ''),
                'teacher_joined' => !empty($row['teacher_joined_at']),
                'teacher_join_delay_minutes' => teacherJoinDelayMinutes($row),
                'teacher_late_join' => teacherLateStatusText($row, $viewerRole === 'teacher' ? 'teacher' : 'admin'),
                'start_local' => $startLocal,
                'end_local' => $endLocal,
                'actual_start_local' => $actualStartLocal,
                'actual_end_local' => $actualEndLocal,
                'actual_duration' => $actualDuration,
                'start_utc' => (string) ($startUtc ?? ''),
                'end_utc' => (string) ($endUtc ?? ''),
            ],
        ];
    }

    /**
     * @return array{bg: string, border: string}
     */
    private static function colorsForStatus(string $status): array
    {
        switch ($status) {
            case 'ongoing':
                return ['bg' => '#fd7e14', 'border' => '#e8590c'];
            case 'completed':
                return ['bg' => '#198754', 'border' => '#146c43'];
            case 'cancelled':
                return ['bg' => '#6c757d', 'border' => '#5c636a'];
            default:
                return ['bg' => '#0d6efd', 'border' => '#0a58ca'];
        }
    }
}

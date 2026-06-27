<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/ClassRecurrenceHelper.php';
require_once dirname(__DIR__) . '/lib/GoogleCalendarMeetingService.php';
require_once dirname(__DIR__) . '/lib/GoogleMeetLiveTrackingService.php';
require_once dirname(__DIR__) . '/models/StudentPayment.php';
require_once dirname(__DIR__) . '/models/TeacherGoogleAccount.php';

/**
 * Creates Google Calendar-style recurring series: one series, one meet link, one email, many occurrences.
 */
class RecurringSeriesService
{
    /**
     * @param list<int> $studentIds
     * @param list<array{start: DateTimeImmutable, end: DateTimeImmutable}> $occurrenceSlots
     * @return array{series_id: int, occurrence_count: int, class_session_id: int, meet_link: ?string, google_event_id: ?string}
     */
    public static function createFromSchedule(
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
        ?int $occurrenceCount
    ): array {
        if ($occurrenceSlots === []) {
            throw new RuntimeException('No occurrences to schedule.');
        }

        $first = $occurrenceSlots[0];
        $last = $occurrenceSlots[count($occurrenceSlots) - 1];
        $firstStartUtc = $first['start']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $firstEndUtc = $first['end']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $startDate = $first['start']->format('Y-m-d');
        $endDate = $last['start']->format('Y-m-d');
        $startTime = $first['start']->format('H:i:s');
        $endTime = $first['end']->format('H:i:s');

        $meetingService = new GoogleCalendarMeetingService();
        $meetTrackingService = new GoogleMeetLiveTrackingService();
        $attendeeEmails = self::studentEmailsForIds($studentIds);
        $teacherGoogleRow = TeacherGoogleAccount::findByTeacherId($teacherId);
        $recordingEnabled = TeacherGoogleAccount::recordingSupportedFromAccountRow($teacherGoogleRow) ? 1 : 0;

        $meeting = $meetingService->createMeeting(
            $teacherId,
            utcToTimezoneIso8601($firstStartUtc, $timezone),
            utcToTimezoneIso8601($firstEndUtc, $timezone),
            $timezone,
            $title,
            $attendeeEmails
        );

        $meetLink = (string) ($meeting['meet_link'] ?? '');
        $googleEventId = (string) ($meeting['event_id'] ?? '');
        if ($googleEventId === '') {
            self::logRecurringSchedule([
                'event' => 'google_event_missing',
                'teacher_id' => $teacherId,
                'meet_link' => $meetLink !== '' ? $meetLink : null,
                'message' => 'Google Calendar createMeeting returned no event_id; series will use empty google_event_id on class_sessions until synced.',
            ]);
        }
        $googleMeetingCode = self::extractMeetCode($meetLink);
        $googleMeetSpaceName = null;
        try {
            $spaceMeta = $meetTrackingService->describeSpaceForMeetingLink($teacherId, $meetLink);
            $googleMeetSpaceName = is_array($spaceMeta) ? ($spaceMeta['name'] ?? null) : null;
        } catch (\Throwable $ignored) {
        }
        $teacherGoogleEmail = (string) ($meeting['organizer_email'] ?? '');

        $seriesStmt = $pdo->prepare(
            'INSERT INTO recurring_series
                (teacher_id, class_master_id, title, description, subject, meeting_link, google_event_id,
                 google_meet_space_name, google_meeting_code, teacher_google_email,
                 start_date, end_date, start_time, end_time, timezone, scheduled_timezone,
                 frequency, recurrence_end_date, occurrence_count, teacher_rate, student_rate,
                 recording_enabled, status)
             VALUES
                (:teacher_id, :class_master_id, :title, :description, :subject, :meeting_link, :google_event_id,
                 :google_meet_space_name, :google_meeting_code, :teacher_google_email,
                 :start_date, :end_date, :start_time, :end_time, :timezone, :scheduled_timezone,
                 :frequency, :recurrence_end_date, :occurrence_count, :teacher_rate, :student_rate,
                 :recording_enabled, "active")'
        );
        $seriesStmt->execute([
            'teacher_id' => $teacherId,
            'class_master_id' => $classMasterId > 0 ? $classMasterId : null,
            'title' => $title,
            'description' => $description,
            'subject' => $title,
            'meeting_link' => $meetLink !== '' ? $meetLink : null,
            'google_event_id' => $googleEventId !== '' ? $googleEventId : null,
            'google_meet_space_name' => $googleMeetSpaceName,
            'google_meeting_code' => $googleMeetingCode,
            'teacher_google_email' => $teacherGoogleEmail !== '' ? $teacherGoogleEmail : null,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'timezone' => $timezone,
            'scheduled_timezone' => $timezone,
            'frequency' => $frequency,
            'recurrence_end_date' => $recurrenceEndDate,
            'occurrence_count' => count($occurrenceSlots),
            'teacher_rate' => $teacherRate,
            'student_rate' => $studentRate,
            'recording_enabled' => $recordingEnabled,
        ]);
        $seriesId = (int) $pdo->lastInsertId();

        if ($studentIds !== []) {
            $studentStmt = $pdo->prepare(
                'INSERT INTO recurring_series_students (series_id, student_id) VALUES (:sid, :uid)'
            );
            foreach ($studentIds as $studentId) {
                $studentStmt->execute(['sid' => $seriesId, 'uid' => $studentId]);
            }
        }

        $firstClassSessionId = 0;
        $occStmt = $pdo->prepare(
            'INSERT INTO recurring_occurrences
                (series_id, occurrence_date, scheduled_start_utc, scheduled_end_utc, status, teacher_payment, meeting_live_status)
             VALUES
                (:series_id, :occurrence_date, :start_utc, :end_utc, "scheduled", 0, "pending")'
        );
        // google_event_id is omitted from INSERT: class_sessions.google_event_id is NOT NULL
        // (defaults to ''). Only the first occurrence is updated after insert (see persistClassOccurrence).
        $classStmt = $pdo->prepare(
            'INSERT INTO class_sessions
                (teacher_id, class_master_id, title, description, payout_amount, student_fee,
                 start_datetime, scheduled_time_utc, start_time_utc, end_datetime, end_time_utc,
                 timezone, scheduled_timezone, meeting_link, teacher_google_email,
                 google_meet_space_name, google_meeting_code, meeting_live_status, status, recording_enabled,
                 recurring_series_id, recurrence_rule)
             VALUES
                (:teacher_id, :class_master_id, :title, :description, :payout, :student_fee,
                 :start_dt, :scheduled_time_utc, :start_time_utc, :end_dt, :end_time_utc,
                 :timezone, :scheduled_timezone, :meeting_link, :teacher_google_email,
                 :google_meet_space_name, :google_meeting_code, "pending", "scheduled", :recording_enabled,
                 :recurring_series_id, :recurrence_rule)'
        );
        $updateGoogleEventStmt = $pdo->prepare(
            'UPDATE class_sessions SET google_event_id = :google_event_id WHERE id = :id'
        );

        self::logRecurringSchedule([
            'event' => 'series_created',
            'series_id' => $seriesId,
            'occurrence_count' => count($occurrenceSlots),
            'frequency' => $frequency,
            'teacher_id' => $teacherId,
        ]);

        foreach ($occurrenceSlots as $index => $slot) {
            $slotStartUtc = $slot['start']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $slotEndUtc = $slot['end']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $occurrenceDate = $slot['start']->format('Y-m-d');

            $occStmt->execute([
                'series_id' => $seriesId,
                'occurrence_date' => $occurrenceDate,
                'start_utc' => $slotStartUtc,
                'end_utc' => $slotEndUtc,
            ]);
            $occurrenceId = (int) $pdo->lastInsertId();

            try {
                $classStmt->execute([
                    'teacher_id' => $teacherId,
                    'class_master_id' => $classMasterId > 0 ? $classMasterId : null,
                    'title' => $title,
                    'description' => $description,
                    'payout' => $teacherRate,
                    'student_fee' => 0,
                    'start_dt' => $slotStartUtc,
                    'scheduled_time_utc' => $slotStartUtc,
                    'start_time_utc' => $slotStartUtc,
                    'end_dt' => $slotEndUtc,
                    'end_time_utc' => $slotEndUtc,
                    'timezone' => $timezone,
                    'scheduled_timezone' => $timezone,
                    'meeting_link' => $meetLink !== '' ? $meetLink : null,
                    'teacher_google_email' => $teacherGoogleEmail !== '' ? $teacherGoogleEmail : null,
                    'google_meet_space_name' => $googleMeetSpaceName,
                    'google_meeting_code' => $googleMeetingCode,
                    'recording_enabled' => $recordingEnabled,
                    'recurring_series_id' => $seriesId,
                    'recurrence_rule' => $frequency,
                ]);
            } catch (\PDOException $e) {
                self::logRecurringSchedule([
                    'event' => 'class_session_insert_failed',
                    'series_id' => $seriesId,
                    'occurrence_index' => $index,
                    'occurrence_date' => $occurrenceDate,
                    'google_event_id' => $index === 0 ? ($googleEventId !== '' ? $googleEventId : '(empty)') : '(series occurrence — omitted from insert)',
                    'error' => $e->getMessage(),
                    'root_cause' => 'INSERT INTO class_sessions failed; google_event_id column is NOT NULL and cannot receive explicit NULL.',
                ]);
                throw $e;
            }
            $classSessionId = (int) $pdo->lastInsertId();

            if ($index === 0 && $googleEventId !== '') {
                $updateGoogleEventStmt->execute([
                    'google_event_id' => $googleEventId,
                    'id' => $classSessionId,
                ]);
            }

            if (migration034HasOccurrenceColumn($pdo)) {
                $pdo->prepare(
                    'UPDATE class_sessions SET recurring_occurrence_id = :oid WHERE id = :id'
                )->execute(['oid' => $occurrenceId, 'id' => $classSessionId]);
            }

            $pdo->prepare(
                'UPDATE recurring_occurrences SET class_session_id = :cid WHERE id = :oid'
            )->execute(['cid' => $classSessionId, 'oid' => $occurrenceId]);

            if ($studentIds !== []) {
                $enrollStmt = $pdo->prepare(
                    'INSERT INTO enrollments (class_id, student_id, status) VALUES (:class_id, :student_id, "active")'
                );
                foreach ($studentIds as $studentId) {
                    $enrollStmt->execute(['class_id' => $classSessionId, 'student_id' => $studentId]);
                }
            }

            if ($firstClassSessionId === 0) {
                $firstClassSessionId = $classSessionId;
            }
        }

        self::createSeriesStudentPayments($pdo, $seriesId, $studentIds, $studentRate);

        self::logRecurringSchedule([
            'event' => 'series_complete',
            'series_id' => $seriesId,
            'occurrence_count' => count($occurrenceSlots),
            'class_session_id' => $firstClassSessionId,
            'meet_link' => $meetLink !== '' ? $meetLink : null,
        ]);

        return [
            'series_id' => $seriesId,
            'occurrence_count' => count($occurrenceSlots),
            'class_session_id' => $firstClassSessionId,
            'meet_link' => $meetLink !== '' ? $meetLink : null,
            'google_event_id' => $googleEventId !== '' ? $googleEventId : null,
        ];
    }

    /**
     * @param list<int> $studentIds
     */
    private static function createSeriesStudentPayments(\PDO $pdo, int $seriesId, array $studentIds, float $studentRate): void
    {
        if ($studentIds === [] || $studentRate <= 0) {
            return;
        }

        if (migration034HasSeriesPaymentColumn($pdo)) {
            $firstOcc = $pdo->prepare(
                'SELECT class_session_id FROM recurring_occurrences WHERE series_id = :sid ORDER BY scheduled_start_utc ASC LIMIT 1'
            );
            $firstOcc->execute(['sid' => $seriesId]);
            $classId = (int) ($firstOcc->fetchColumn() ?: 0);
            if ($classId <= 0) {
                return;
            }
            $stmt = $pdo->prepare(
                'INSERT INTO student_payments (student_id, class_id, recurring_series_id, amount, currency, status, payment_date, created_at)
                 VALUES (:student_id, :class_id, :series_id, :amount, "INR", "pending", NULL, NOW())'
            );
            foreach ($studentIds as $studentId) {
                $stmt->execute([
                    'student_id' => $studentId,
                    'class_id' => $classId,
                    'series_id' => $seriesId,
                    'amount' => parseInrAmount($studentRate),
                ]);
            }

            return;
        }

        $firstOcc = $pdo->prepare(
            'SELECT class_session_id FROM recurring_occurrences WHERE series_id = :sid ORDER BY scheduled_start_utc ASC LIMIT 1'
        );
        $firstOcc->execute(['sid' => $seriesId]);
        $classId = (int) ($firstOcc->fetchColumn() ?: 0);
        if ($classId <= 0) {
            return;
        }
        foreach ($studentIds as $studentId) {
            StudentPayment::createPendingForEnrollment($classId, $studentId, $studentRate);
        }
    }

    /**
     * @param list<int> $studentIds
     * @return list<string>
     */
    private static function studentEmailsForIds(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }
        $pdo = Database::connection();
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT email FROM users WHERE id IN ($placeholders) AND status = 'active' AND email IS NOT NULL AND email != ''"
        );
        $stmt->execute($studentIds);

        return array_values(array_filter(array_map(
            static fn(array $r): string => (string) ($r['email'] ?? ''),
            $stmt->fetchAll() ?: []
        )));
    }

    private static function extractMeetCode(string $meetLink): ?string
    {
        if (preg_match('#meet\.google\.com/([a-z0-9-]+)#i', $meetLink, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    public static function syncOccurrenceFromClassSession(int $classSessionId, array $classRow): void
    {
        $occurrenceId = (int) ($classRow['recurring_occurrence_id'] ?? 0);
        $seriesId = (int) ($classRow['recurring_series_id'] ?? 0);
        if ($occurrenceId <= 0 || $seriesId <= 0) {
            return;
        }

        $pdo = Database::connection();
        $series = $pdo->prepare('SELECT teacher_rate FROM recurring_series WHERE id = :id LIMIT 1');
        $series->execute(['id' => $seriesId]);
        $teacherRate = (float) ($series->fetchColumn() ?: 0);

        $status = (string) ($classRow['status'] ?? 'scheduled');
        $teacherPayment = 0.0;
        if ($status === 'completed') {
            $teacherPayment = $teacherRate;
        }

        $delayMinutes = function_exists('teacherJoinDelayMinutes') ? teacherJoinDelayMinutes($classRow) : null;

        $pdo->prepare(
            'UPDATE recurring_occurrences
             SET actual_start_utc = :actual_start,
                 actual_end_utc = :actual_end,
                 duration_minutes = :duration,
                 status = :status,
                 teacher_payment = :teacher_payment,
                 teacher_joined_at = :teacher_joined_at,
                 teacher_join_delay_minutes = :teacher_join_delay_minutes,
                 student_joined_at = :student_joined_at,
                 meeting_live_status = :meeting_live_status,
                 google_conference_id = :google_conference_id
             WHERE id = :id'
        )->execute([
            'actual_start' => $classRow['actual_start_time'] ?? null,
            'actual_end' => $classRow['actual_end_time'] ?? null,
            'duration' => $classRow['actual_duration_minutes'] ?? $classRow['actual_duration'] ?? null,
            'status' => in_array($status, ['scheduled', 'ongoing', 'completed', 'cancelled', 'missed', 'rescheduled'], true)
                ? $status : 'scheduled',
            'teacher_payment' => $teacherPayment,
            'teacher_joined_at' => $classRow['teacher_joined_at'] ?? null,
            'teacher_join_delay_minutes' => $delayMinutes,
            'student_joined_at' => $classRow['student_joined_at'] ?? null,
            'meeting_live_status' => $classRow['meeting_live_status'] ?? 'pending',
            'google_conference_id' => $classRow['google_conference_id'] ?? null,
            'id' => $occurrenceId,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function logRecurringSchedule(array $context): void
    {
        if (!function_exists('writeStructuredLog')) {
            return;
        }
        writeStructuredLog('recurring_schedule.log', $context);
    }
}

function migration034HasOccurrenceColumn(\PDO $pdo): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "class_sessions" AND COLUMN_NAME = "recurring_occurrence_id" LIMIT 1'
        );
        $stmt->execute();
        $cached = (bool) $stmt->fetchColumn();
    } catch (\Throwable $e) {
        $cached = false;
    }

    return $cached;
}

function migration034HasSeriesPaymentColumn(\PDO $pdo): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "student_payments" AND COLUMN_NAME = "recurring_series_id" LIMIT 1'
        );
        $stmt->execute();
        $cached = (bool) $stmt->fetchColumn();
    } catch (\Throwable $e) {
        $cached = false;
    }

    return $cached;
}

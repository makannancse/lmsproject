<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';

class RecurringOccurrence
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function findCalendarEvents(
        string $rangeStartUtc,
        string $rangeEndUtc,
        string $viewerRole,
        int $viewerUserId,
        ?int $filterTeacherId,
        ?int $filterStudentId
    ): array {
        $pdo = Database::connection();

        $sql = 'SELECT ro.id AS occurrence_id,
                    ro.series_id AS recurring_series_id,
                    ro.id AS recurring_occurrence_id,
                    ro.class_session_id,
                    ro.occurrence_date,
                    ro.scheduled_start_utc,
                    ro.scheduled_end_utc,
                    ro.actual_start_utc,
                    ro.actual_end_utc,
                    ro.duration_minutes,
                    ro.status,
                    ro.teacher_payment,
                    ro.meeting_live_status,
                    ro.teacher_joined_at,
                    ro.student_joined_at,
                    rs.title,
                    rs.description,
                    rs.meeting_link,
                    rs.google_meet_space_name,
                    rs.google_meeting_code,
                    rs.teacher_google_email,
                    rs.timezone,
                    rs.scheduled_timezone,
                    rs.teacher_rate,
                    rs.student_rate,
                    rs.recording_enabled,
                    rs.frequency,
                    rs.start_date AS series_start_date,
                    rs.end_date AS series_end_date,
                    u.name AS teacher_name,
                    COALESCE(ro.class_session_id, ro.id) AS id,
                    ro.scheduled_start_utc AS start_datetime,
                    ro.scheduled_start_utc AS start_time_utc,
                    ro.scheduled_end_utc AS end_datetime,
                    ro.scheduled_end_utc AS end_time_utc,
                    ro.actual_start_utc AS actual_start_time,
                    ro.actual_end_utc AS actual_end_time,
                    ro.duration_minutes AS actual_duration_minutes,
                    (SELECT GROUP_CONCAT(DISTINCT u2.name ORDER BY u2.name SEPARATOR ", ")
                     FROM recurring_series_students rss
                     INNER JOIN users u2 ON u2.id = rss.student_id
                     WHERE rss.series_id = rs.id) AS student_names
             FROM recurring_occurrences ro
             INNER JOIN recurring_series rs ON rs.id = ro.series_id
             INNER JOIN users u ON u.id = rs.teacher_id
             WHERE rs.status = "active"
               AND ro.scheduled_start_utc < :rng_end
               AND ro.scheduled_end_utc > :rng_start';

        $params = [
            'rng_start' => $rangeStartUtc,
            'rng_end' => $rangeEndUtc,
        ];

        if ($viewerRole === 'teacher') {
            $sql .= ' AND rs.teacher_id = :scope_tid';
            $params['scope_tid'] = $viewerUserId;
        } elseif ($viewerRole === 'student') {
            $sql .= ' AND EXISTS (
                SELECT 1 FROM recurring_series_students rss
                WHERE rss.series_id = rs.id AND rss.student_id = :scope_sid
            )';
            $params['scope_sid'] = $viewerUserId;
        } elseif ($viewerRole === 'admin') {
            if ($filterTeacherId !== null && $filterTeacherId > 0) {
                $sql .= ' AND rs.teacher_id = :ftid';
                $params['ftid'] = $filterTeacherId;
            }
            if ($filterStudentId !== null && $filterStudentId > 0) {
                $sql .= ' AND EXISTS (
                    SELECT 1 FROM recurring_series_students rss
                    WHERE rss.series_id = rs.id AND rss.student_id = :fsid
                )';
                $params['fsid'] = $filterStudentId;
            }
        } else {
            return [];
        }

        $sql .= ' ORDER BY ro.scheduled_start_utc ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];

        foreach ($rows as &$row) {
            $row['is_recurring_occurrence'] = true;
            $row['payout_amount'] = (float) ($row['teacher_rate'] ?? 0);
            $row['student_fee'] = (float) ($row['student_rate'] ?? 0);
        }
        unset($row);

        return $rows;
    }

    public static function findById(int $occurrenceId): ?array
    {
        if ($occurrenceId <= 0) {
            return null;
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT ro.*, rs.*
             FROM recurring_occurrences ro
             INNER JOIN recurring_series rs ON rs.id = ro.series_id
             WHERE ro.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $occurrenceId]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @return list<int>
     */
    public static function idsFromScope(int $occurrenceId, string $scope, \PDO $pdo): array
    {
        $stmt = $pdo->prepare('SELECT * FROM recurring_occurrences WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $occurrenceId]);
        $current = $stmt->fetch();
        if (!$current) {
            return [];
        }

        $seriesId = (int) $current['series_id'];
        if ($scope === 'current') {
            return [$occurrenceId];
        }

        if ($scope === 'all_future') {
            $list = $pdo->prepare(
                'SELECT id FROM recurring_occurrences
                 WHERE series_id = :sid
                   AND scheduled_start_utc >= :start
                   AND status IN ("scheduled", "rescheduled")
                 ORDER BY scheduled_start_utc ASC'
            );
            $list->execute([
                'sid' => $seriesId,
                'start' => $current['scheduled_start_utc'],
            ]);

            return array_map(static fn(array $r): int => (int) $r['id'], $list->fetchAll() ?: []);
        }

        $list = $pdo->prepare(
            'SELECT id FROM recurring_occurrences
             WHERE series_id = :sid
             ORDER BY scheduled_start_utc ASC'
        );
        $list->execute(['sid' => $seriesId]);

        return array_map(static fn(array $r): int => (int) $r['id'], $list->fetchAll() ?: []);
    }
}

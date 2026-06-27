<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';

class ClassSession
{
    public static function findUpcomingByStudent(int $studentId, int $limit = 10): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT cs.*, t.name AS teacher_name
             FROM class_sessions cs
             INNER JOIN users t ON t.id = cs.teacher_id
             INNER JOIN enrollments e ON e.class_id = cs.id
             WHERE e.student_id = :student_id
               AND cs.status IN ("scheduled", "rescheduled", "ongoing")
             ORDER BY cs.start_datetime ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':student_id', $studentId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public static function findUpcomingByTeacher(int $teacherId, int $limit = 10): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT *
             FROM class_sessions
             WHERE teacher_id = :teacher_id
               AND status IN ("scheduled", "rescheduled", "ongoing")
             ORDER BY start_datetime ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':teacher_id', $teacherId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public static function findCompletedByTeacher(int $teacherId, int $limit = 10): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT cs.*,
                    COALESCE(cr.recording_url, cs.recording_url) AS recording_url,
                    COALESCE(cr.recording_title, cs.title) AS recording_title,
                    cr.recording_duration,
                    COALESCE(cr.visible_to_student, "no") AS visible_to_student,
                    COALESCE(cr.sync_status, cs.recording_sync_status) AS recording_sync_status,
                    tga.recording_supported AS teacher_recording_supported
             FROM class_sessions cs
             LEFT JOIN teacher_google_accounts tga ON tga.teacher_id = cs.teacher_id
             LEFT JOIN class_recordings cr ON cr.class_id = cs.id
             WHERE cs.teacher_id = :teacher_id
               AND cs.status = "completed"
             ORDER BY cs.start_datetime DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':teacher_id', $teacherId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public static function countByStatus(): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT status, COUNT(*) as total FROM class_sessions GROUP BY status';
        $rows = $pdo->query($sql)->fetchAll() ?: [];
        $result = ['scheduled' => 0, 'ongoing' => 0, 'completed' => 0, 'cancelled' => 0, 'rescheduled' => 0];
        foreach ($rows as $row) {
            $status = $row['status'];
            if (isset($result[$status])) {
                $result[$status] = (int) $row['total'];
            }
        }
        return $result;
    }

    public static function formatActualDuration(?array $row): string
    {
        return formatDurationMinutes(classActualDurationMinutes($row));
        if ($row === null) {
            return '—';
        }

        if (!empty($row['actual_duration'])) {
            return (int) $row['actual_duration'] . ' min';
        }

        $a = $row['actual_start_time'] ?? null;
        $b = $row['actual_end_time'] ?? null;
        if (empty($a) || empty($b)) {
            return '—';
        }

        $seconds = strtotime((string) $b) - strtotime((string) $a);
        if ($seconds < 0) {
            return '—';
        }

        return (string) (int) round($seconds / 60) . ' min';
    }

    public static function findByTeacherBetween(int $teacherId, string $fromUtc, string $toUtc): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT *
             FROM class_sessions
             WHERE teacher_id = :teacher_id
               AND start_datetime >= :from
               AND start_datetime < :to
             ORDER BY start_datetime ASC'
        );
        $stmt->execute([
            'teacher_id' => $teacherId,
            'from' => $fromUtc,
            'to' => $toUtc,
        ]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array<string,mixed>>
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

        $sql = 'SELECT cs.*, u.name AS teacher_name,
            COALESCE(cr.recording_url, cs.recording_url) AS recording_url,
            COALESCE(cr.recording_title, cs.title) AS recording_title,
            cr.recording_duration,
            COALESCE(cr.visible_to_student, "no") AS visible_to_student,
            COALESCE(cr.sync_status, cs.recording_sync_status) AS recording_sync_status,
            tga.recording_supported AS teacher_recording_supported,
            (SELECT GROUP_CONCAT(DISTINCT u2.name ORDER BY u2.name SEPARATOR ", ")
             FROM enrollments e
             INNER JOIN users u2 ON u2.id = e.student_id
             WHERE e.class_id = cs.id AND e.status = "active") AS student_names
         FROM class_sessions cs
         INNER JOIN users u ON u.id = cs.teacher_id
         LEFT JOIN teacher_google_accounts tga ON tga.teacher_id = cs.teacher_id
         LEFT JOIN class_recordings cr ON cr.class_id = cs.id
         WHERE cs.start_datetime < :rng_end AND cs.end_datetime > :rng_start
           AND (cs.recurring_series_id IS NULL OR cs.recurring_series_id = 0)';

        $params = [
            'rng_start' => $rangeStartUtc,
            'rng_end' => $rangeEndUtc,
        ];

        if ($viewerRole === 'teacher') {
            $sql .= ' AND cs.teacher_id = :scope_tid';
            $params['scope_tid'] = $viewerUserId;
        } elseif ($viewerRole === 'student') {
            $sql .= ' AND EXISTS (
                SELECT 1 FROM enrollments e
                WHERE e.class_id = cs.id AND e.student_id = :scope_sid AND e.status = "active"
            )';
            $params['scope_sid'] = $viewerUserId;
        } elseif ($viewerRole === 'admin') {
            if ($filterTeacherId !== null && $filterTeacherId > 0) {
                $sql .= ' AND cs.teacher_id = :ftid';
                $params['ftid'] = $filterTeacherId;
            }
            if ($filterStudentId !== null && $filterStudentId > 0) {
                $sql .= ' AND EXISTS (
                    SELECT 1 FROM enrollments e
                    WHERE e.class_id = cs.id AND e.student_id = :fsid AND e.status = "active"
                )';
                $params['fsid'] = $filterStudentId;
            }
        } else {
            return [];
        }

        $sql .= ' ORDER BY cs.start_datetime ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public static function findCompletedByStudent(int $studentId, int $limit = 20): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT cs.*, t.name AS teacher_name,
                    COALESCE(cr.recording_url, cs.recording_url) AS recording_url,
                    COALESCE(cr.recording_title, cs.title) AS recording_title,
                    cr.recording_duration,
                    COALESCE(cr.visible_to_student, "no") AS visible_to_student,
                    COALESCE(cr.sync_status, cs.recording_sync_status) AS recording_sync_status,
                    tga.recording_supported AS teacher_recording_supported
             FROM class_sessions cs
             INNER JOIN users t ON t.id = cs.teacher_id
             INNER JOIN enrollments e ON e.class_id = cs.id
             LEFT JOIN teacher_google_accounts tga ON tga.teacher_id = cs.teacher_id
             LEFT JOIN class_recordings cr ON cr.class_id = cs.id
             WHERE e.student_id = :student_id
               AND cs.status = "completed"
             ORDER BY cs.completed_at DESC, cs.start_datetime DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':student_id', $studentId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public static function countCompletedWithTeacher(int $studentId, int $teacherId): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS c
             FROM enrollments e
             INNER JOIN class_sessions cs ON cs.id = e.class_id
             WHERE e.student_id = :sid
               AND cs.teacher_id = :tid
               AND cs.status = "completed"'
        );
        $stmt->execute(['sid' => $studentId, 'tid' => $teacherId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public static function findByIdForUser(int $classId, int $userId, string $role): ?array
    {
        $pdo = Database::connection();
        if ($role === 'teacher') {
            $stmt = $pdo->prepare(
                'SELECT * FROM class_sessions WHERE id = :id AND teacher_id = :uid LIMIT 1'
            );
            $stmt->execute(['id' => $classId, 'uid' => $userId]);
        } elseif ($role === 'student') {
            $stmt = $pdo->prepare(
                'SELECT cs.* FROM class_sessions cs
                 INNER JOIN enrollments e ON e.class_id = cs.id
                 WHERE cs.id = :id AND e.student_id = :uid LIMIT 1'
            );
            $stmt->execute(['id' => $classId, 'uid' => $userId]);
        } else {
            return null;
        }

        $row = $stmt->fetch();
        return $row ?: null;
    }
}

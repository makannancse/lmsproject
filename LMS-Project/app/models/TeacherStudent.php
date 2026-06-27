<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';

/**
 * teacher_students mapping — access control for scheduling, homework, reports, etc.
 */
class TeacherStudent
{
    /**
     * @return list<array{id: int, name: string, email: string}>
     */
    public static function studentsForTeacher(int $teacherId): array
    {
        if ($teacherId <= 0) {
            return [];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email
             FROM users u
             INNER JOIN teacher_students ts ON ts.student_id = u.id
             WHERE ts.teacher_id = :tid
               AND u.role = "student"
               AND u.status = "active"
             ORDER BY u.name ASC'
        );
        $stmt->execute(['tid' => $teacherId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<int>
     */
    public static function studentIdsForTeacher(int $teacherId): array
    {
        return array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            self::studentsForTeacher($teacherId)
        );
    }

    public static function isMapped(int $teacherId, int $studentId): bool
    {
        if ($teacherId <= 0 || $studentId <= 0) {
            return false;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT 1 FROM teacher_students
             WHERE teacher_id = :tid AND student_id = :sid
             LIMIT 1'
        );
        $stmt->execute(['tid' => $teacherId, 'sid' => $studentId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param list<int> $studentIds
     * @return list<int> invalid ids (not mapped to teacher)
     */
    public static function filterUnmappedStudentIds(int $teacherId, array $studentIds): array
    {
        $studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds), static fn (int $id): bool => $id > 0)));
        if ($teacherId <= 0 || $studentIds === []) {
            return $studentIds;
        }

        $allowed = self::studentIdsForTeacher($teacherId);
        $allowedMap = array_fill_keys($allowed, true);
        $invalid = [];
        foreach ($studentIds as $studentId) {
            if (!isset($allowedMap[$studentId])) {
                $invalid[] = $studentId;
            }
        }

        return $invalid;
    }

    public static function countForTeacher(int $teacherId): int
    {
        if ($teacherId <= 0) {
            return 0;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM teacher_students WHERE teacher_id = :tid');
        $stmt->execute(['tid' => $teacherId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function assignedStudentsDetailed(int $teacherId): array
    {
        if ($teacherId <= 0) {
            return [];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email, u.timezone, u.status,
                    cm.class_name, ts.created_at AS assigned_at
             FROM teacher_students ts
             INNER JOIN users u ON u.id = ts.student_id
             LEFT JOIN class_master cm ON cm.id = (
                 SELECT cs.class_master_id FROM class_sessions cs
                 INNER JOIN enrollments e ON e.class_id = cs.id AND e.student_id = ts.student_id
                 WHERE cs.teacher_id = ts.teacher_id
                 ORDER BY cs.start_datetime DESC LIMIT 1
             )
             WHERE ts.teacher_id = :tid
             ORDER BY u.name ASC'
        );
        $stmt->execute(['tid' => $teacherId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function assignedTeachersForStudent(int $studentId): array
    {
        if ($studentId <= 0) {
            return [];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email, u.timezone, u.status,
                    s.subject, cm.class_name AS class_name
             FROM teacher_students ts
             INNER JOIN users u ON u.id = ts.teacher_id
             LEFT JOIN students s ON s.user_id = ts.student_id
             LEFT JOIN class_master cm ON cm.id = (
                 SELECT cs.class_master_id FROM class_sessions cs
                 INNER JOIN enrollments e ON e.class_id = cs.id AND e.student_id = ts.student_id
                 WHERE cs.teacher_id = ts.teacher_id
                 ORDER BY cs.start_datetime DESC LIMIT 1
             )
             WHERE ts.student_id = :sid
             ORDER BY u.name ASC'
        );
        $stmt->execute(['sid' => $studentId]);

        return $stmt->fetchAll() ?: [];
    }
}

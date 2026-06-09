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
}

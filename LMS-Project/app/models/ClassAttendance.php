<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';

class ClassAttendance
{
    public static function markJoin(int $classId, int $userId, string $role, string $joinedAt): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO class_attendance (class_id, user_id, role, joined_at, left_at)
             VALUES (:class_id, :user_id, :role, :joined_at, NULL)
             ON DUPLICATE KEY UPDATE
                joined_at = COALESCE(class_attendance.joined_at, VALUES(joined_at)),
                left_at = NULL'
        );
        $stmt->execute([
            'class_id' => $classId,
            'user_id' => $userId,
            'role' => $role,
            'joined_at' => $joinedAt,
        ]);
    }

    public static function markLeave(int $classId, int $userId, string $role, string $leftAt): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE class_attendance
             SET left_at = COALESCE(left_at, :left_at)
             WHERE class_id = :class_id AND user_id = :user_id AND role = :role'
        );
        $stmt->execute([
            'class_id' => $classId,
            'user_id' => $userId,
            'role' => $role,
            'left_at' => $leftAt,
        ]);

        if ($stmt->rowCount() > 0) {
            return;
        }

        $fallback = $pdo->prepare(
            'INSERT INTO class_attendance (class_id, user_id, role, joined_at, left_at)
             VALUES (:class_id, :user_id, :role, NULL, :left_at)
             ON DUPLICATE KEY UPDATE
                left_at = COALESCE(class_attendance.left_at, VALUES(left_at))'
        );
        $fallback->execute([
            'class_id' => $classId,
            'user_id' => $userId,
            'role' => $role,
            'left_at' => $leftAt,
        ]);
    }

    public static function listForClass(int $classId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT ca.*, u.name, u.email
             FROM class_attendance ca
             INNER JOIN users u ON u.id = ca.user_id
             WHERE ca.class_id = :class_id
             ORDER BY ca.joined_at ASC, ca.id ASC'
        );
        $stmt->execute(['class_id' => $classId]);
        return $stmt->fetchAll() ?: [];
    }

    public static function syncActivity(int $classId, int $userId, string $role, ?string $joinedAt, ?string $leftAt): void
    {
        if ($classId <= 0 || $userId <= 0 || !in_array($role, ['teacher', 'student'], true)) {
            return;
        }

        $joinedAt = $joinedAt !== null ? trim($joinedAt) : null;
        $leftAt = $leftAt !== null ? trim($leftAt) : null;

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO class_attendance (class_id, user_id, role, joined_at, left_at)
             VALUES (:class_id, :user_id, :role, :joined_at, :left_at)
             ON DUPLICATE KEY UPDATE
                joined_at = CASE
                    WHEN VALUES(joined_at) IS NULL THEN class_attendance.joined_at
                    WHEN class_attendance.joined_at IS NULL THEN VALUES(joined_at)
                    ELSE LEAST(class_attendance.joined_at, VALUES(joined_at))
                END,
                left_at = CASE
                    WHEN VALUES(left_at) IS NULL THEN class_attendance.left_at
                    WHEN class_attendance.left_at IS NULL THEN VALUES(left_at)
                    ELSE GREATEST(class_attendance.left_at, VALUES(left_at))
                END'
        );
        $stmt->execute([
            'class_id' => $classId,
            'user_id' => $userId,
            'role' => $role,
            'joined_at' => $joinedAt,
            'left_at' => $leftAt,
        ]);
    }
}

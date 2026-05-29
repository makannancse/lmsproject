<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';

class RescheduleRequest
{
    public static function forStudent(int $studentId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT rr.*, cs.title AS class_title, cs.start_datetime, cs.start_time_utc, cs.end_time_utc, cs.scheduled_timezone
             FROM reschedule_requests rr
             INNER JOIN class_sessions cs ON cs.id = rr.class_id
             WHERE rr.student_id = :sid
             ORDER BY rr.created_at DESC'
        );
        $stmt->execute(['sid' => $studentId]);
        return $stmt->fetchAll() ?: [];
    }

    public static function pendingForTeacher(int $teacherId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT rr.*, cs.title AS class_title, cs.start_datetime, cs.start_time_utc, cs.end_time_utc,
                    cs.scheduled_timezone, u.name AS student_name
             FROM reschedule_requests rr
             INNER JOIN class_sessions cs ON cs.id = rr.class_id
             INNER JOIN users u ON u.id = rr.student_id
             WHERE rr.teacher_id = :tid
             ORDER BY rr.created_at DESC'
        );
        $stmt->execute(['tid' => $teacherId]);
        return $stmt->fetchAll() ?: [];
    }

    public static function allForAdmin(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query(
            'SELECT rr.*, cs.title AS class_title, cs.start_datetime, cs.start_time_utc, cs.end_time_utc,
                    cs.scheduled_timezone, s.name AS student_name, t.name AS teacher_name
             FROM reschedule_requests rr
             INNER JOIN class_sessions cs ON cs.id = rr.class_id
             INNER JOIN users s ON s.id = rr.student_id
             INNER JOIN users t ON t.id = rr.teacher_id
             ORDER BY rr.created_at DESC'
        );
        return $stmt->fetchAll() ?: [];
    }
}

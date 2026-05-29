<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/models/SystemConfig.php';

class TeacherPayout
{
    /**
     * When a class is marked completed, ensure a pending payout row exists.
     */
    public static function ensureForCompletedClass(int $classId): void
    {
        if ($classId <= 0) {
            return;
        }
        $pdo = Database::connection();
        $classStmt = $pdo->prepare('SELECT * FROM class_sessions WHERE id = :id LIMIT 1');
        $classStmt->execute(['id' => $classId]);
        $class = $classStmt->fetch();
        if (!$class) {
            return;
        }

        $amount = null;
        if (isset($class['payout_amount']) && (float) $class['payout_amount'] > 0) {
            $amount = round((float) $class['payout_amount'], 2);
        } else {
            $durationHours = max(
                0.0,
                (strtotime((string) $class['end_datetime']) - strtotime((string) $class['start_datetime'])) / 3600
            );
            $teacherStmt = $pdo->prepare(
                'SELECT u.id, t.hourly_rate, t.employment_type
                 FROM users u
                 LEFT JOIN teachers t ON t.user_id = u.id
                 WHERE u.id = :id
                 LIMIT 1'
            );
            $teacherStmt->execute(['id' => $class['teacher_id']]);
            $teacher = $teacherStmt->fetch() ?: [];

            $rate = 0.0;
            if (!empty($teacher['hourly_rate'])) {
                $rate = (float) $teacher['hourly_rate'];
            } else {
                $employmentType = $teacher['employment_type'] ?? 'part_time';
                if ($employmentType === 'full_time') {
                    $rate = (float) SystemConfig::get('payout_rate_full_time', '30');
                } else {
                    $rate = (float) SystemConfig::get('payout_rate_part_time', '20');
                }
            }

            if ($rate <= 0) {
                $rate = (float) SystemConfig::get('payout_rate_per_hour', '20');
            }

            $amount = round($durationHours * $rate, 2);
        }

        $payoutStmt = $pdo->prepare(
            'INSERT INTO teacher_payments (teacher_id, class_id, amount, status, created_at)
             VALUES (:teacher_id, :class_id, :amount, "pending", NOW())
             ON DUPLICATE KEY UPDATE amount = VALUES(amount)'
        );
        $payoutStmt->execute([
            'teacher_id' => $class['teacher_id'],
            'class_id' => $classId,
            'amount' => $amount,
        ]);
    }

    public static function totalByStatus(string $status): float
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT SUM(amount) as total FROM teacher_payments WHERE status = :status');
        $stmt->execute(['status' => $status]);
        $row = $stmt->fetch();
        return $row && $row['total'] !== null ? (float) $row['total'] : 0.0;
    }

    public static function totalForTeacher(int $teacherId, ?string $status = null): float
    {
        $pdo = Database::connection();
        if ($status !== null) {
            $stmt = $pdo->prepare(
                'SELECT SUM(amount) as total 
                 FROM teacher_payments 
                 WHERE teacher_id = :teacher_id AND status = :status'
            );
            $stmt->execute(['teacher_id' => $teacherId, 'status' => $status]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT SUM(amount) as total 
                 FROM teacher_payments 
                 WHERE teacher_id = :teacher_id'
            );
            $stmt->execute(['teacher_id' => $teacherId]);
        }
        $row = $stmt->fetch();
        return $row && $row['total'] !== null ? (float) $row['total'] : 0.0;
    }
}

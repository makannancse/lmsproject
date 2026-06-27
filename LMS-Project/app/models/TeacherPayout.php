<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/models/SystemConfig.php';

class TeacherPayout
{
    /**
     * When a class is marked completed, ensure a pending payout row exists in teacher_payouts.
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

        $amount = self::resolvePayoutAmount($class);

        $payoutStmt = $pdo->prepare(
            'INSERT INTO teacher_payouts (teacher_id, class_id, amount, status, calculated_at, created_at)
             VALUES (:teacher_id, :class_id, :amount, "pending", UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                amount = VALUES(amount),
                calculated_at = VALUES(calculated_at),
                updated_at = UTC_TIMESTAMP()'
        );
        $payoutStmt->execute([
            'teacher_id' => (int) $class['teacher_id'],
            'class_id' => $classId,
            'amount' => $amount,
        ]);

        $occurrenceId = (int) ($class['recurring_occurrence_id'] ?? 0);
        if ($occurrenceId > 0 && self::hasOccurrencePayoutColumn($pdo)) {
            $pdo->prepare(
                'UPDATE teacher_payouts SET recurring_occurrence_id = :oid WHERE teacher_id = :tid AND class_id = :cid'
            )->execute([
                'oid' => $occurrenceId,
                'tid' => (int) $class['teacher_id'],
                'cid' => $classId,
            ]);
        }

        if ($occurrenceId > 0) {
            require_once dirname(__DIR__) . '/lib/RecurringSeriesService.php';
            RecurringSeriesService::syncOccurrenceFromClassSession($classId, $class);
        }

        try {
            $logStmt = $pdo->prepare(
                'INSERT INTO teacher_payment_logs (teacher_id, class_id, amount, status, created_at)
                 VALUES (:teacher_id, :class_id, :amount, "pending", UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE amount = VALUES(amount)'
            );
            $logStmt->execute([
                'teacher_id' => (int) $class['teacher_id'],
                'class_id' => $classId,
                'amount' => $amount,
            ]);
        } catch (\Throwable $e) {
            writeStructuredLog('teacher_payout.log', [
                'event' => 'teacher_payment_log_sync_skipped',
                'class_id' => $classId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $class
     */
    private static function resolvePayoutAmount(array $class): float
    {
        if (isset($class['payout_amount']) && (float) $class['payout_amount'] > 0) {
            return parseInrAmount($class['payout_amount']);
        }

        $durationHours = max(
            0.0,
            (strtotime((string) $class['end_datetime']) - strtotime((string) $class['start_datetime'])) / 3600
        );

        $pdo = Database::connection();
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

        return parseInrAmount($durationHours * $rate);
    }

    public static function totalByStatus(string $status): float
    {
        if (!in_array($status, ['pending', 'paid'], true)) {
            return 0.0;
        }

        $pdo = Database::connection();
        try {
            $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) AS total FROM teacher_payouts WHERE status = :status');
            $stmt->execute(['status' => $status]);
            $row = $stmt->fetch();

            return $row ? (float) ($row['total'] ?? 0) : 0.0;
        } catch (\Throwable $e) {
            writeStructuredLog('teacher_payout.log', [
                'event' => 'total_by_status_failed',
                'status' => $status,
                'message' => $e->getMessage(),
            ]);

            return 0.0;
        }
    }

    public static function totalForTeacher(int $teacherId, ?string $status = null): float
    {
        if ($teacherId <= 0) {
            return 0.0;
        }

        $pdo = Database::connection();
        try {
            if ($status !== null && in_array($status, ['pending', 'paid'], true)) {
                $stmt = $pdo->prepare(
                    'SELECT COALESCE(SUM(amount), 0) AS total
                     FROM teacher_payouts
                     WHERE teacher_id = :teacher_id AND status = :status'
                );
                $stmt->execute(['teacher_id' => $teacherId, 'status' => $status]);
            } else {
                $stmt = $pdo->prepare(
                    'SELECT COALESCE(SUM(amount), 0) AS total
                     FROM teacher_payouts
                     WHERE teacher_id = :teacher_id'
                );
                $stmt->execute(['teacher_id' => $teacherId]);
            }
            $row = $stmt->fetch();

            return $row ? (float) ($row['total'] ?? 0) : 0.0;
        } catch (\Throwable $e) {
            writeStructuredLog('teacher_payout.log', [
                'event' => 'total_for_teacher_failed',
                'teacher_id' => $teacherId,
                'status' => $status,
                'message' => $e->getMessage(),
            ]);

            return 0.0;
        }
    }

    private static function hasOccurrencePayoutColumn(\PDO $pdo): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "teacher_payouts" AND COLUMN_NAME = "recurring_occurrence_id" LIMIT 1'
            );
            $stmt->execute();
            $cached = (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            $cached = false;
        }

        return $cached;
    }
}

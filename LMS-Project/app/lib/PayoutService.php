<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';

/**
 * Teacher payout totals from teacher_payouts (only completed classes generate rows).
 */
class PayoutService
{
    /**
     * Aggregate payout amounts for a teacher (pending vs paid vs combined).
     *
     * @return array{pending: float, paid: float, total: float, completed_classes: int}
     */
    public static function calculateTeacherPayout(int $teacherId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT 
                COALESCE(SUM(CASE WHEN status = "pending" THEN amount ELSE 0 END), 0) AS pending,
                COALESCE(SUM(CASE WHEN status = "paid" THEN amount ELSE 0 END), 0) AS paid
             FROM teacher_payouts
             WHERE teacher_id = :tid'
        );
        $stmt->execute(['tid' => $teacherId]);
        $row = $stmt->fetch() ?: ['pending' => 0, 'paid' => 0];
        $pending = (float) $row['pending'];
        $paid = (float) $row['paid'];

        $cntStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM class_sessions
             WHERE teacher_id = :tid AND status = "completed"'
        );
        $cntStmt->execute(['tid' => $teacherId]);
        $completedClasses = (int) $cntStmt->fetchColumn();

        return [
            'pending' => $pending,
            'paid' => $paid,
            'total' => $pending + $paid,
            'completed_classes' => $completedClasses,
        ];
    }
}

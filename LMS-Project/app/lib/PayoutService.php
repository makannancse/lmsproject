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
        require_once dirname(__DIR__, 2) . '/payments/payment_helper.php';
        
        $summary = getTeacherPayoutSummary($teacherId);
        $pending = (float) $summary['pending_amount'];
        $paid = (float) $summary['paid_amount'];

        $pdo = Database::connection();
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

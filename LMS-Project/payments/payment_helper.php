<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

function derivePayoutStatus(float $totalEarnings, float $paidAmount): string
{
    $pending = $totalEarnings - $paidAmount;
    if ($pending <= 0.0 && $paidAmount > $totalEarnings) {
        return 'advance';
    }
    if ($pending <= 0.0 && $totalEarnings > 0.0) {
        return 'paid';
    }
    if ($paidAmount <= 0.0) {
        return 'pending';
    }
    return 'partial';
}

function getTeacherPayoutSummary(int $teacherId): array
{
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT
            COALESCE(c.total_earnings, 0) AS total_earnings,
            COALESCE(p.paid_amount, 0) AS paid_amount
         FROM (SELECT :tid AS teacher_id) t
         LEFT JOIN (
            SELECT teacher_id, SUM(payout_amount) AS total_earnings
            FROM class_sessions
            WHERE status = "completed"
            GROUP BY teacher_id
         ) c ON c.teacher_id = t.teacher_id
         LEFT JOIN (
            SELECT teacher_id, SUM(amount) AS paid_amount
            FROM teacher_payment_logs
            WHERE status = "paid"
            GROUP BY teacher_id
         ) p ON p.teacher_id = t.teacher_id'
    );
    $stmt->execute(['tid' => $teacherId]);
    $row = $stmt->fetch() ?: ['total_earnings' => 0, 'paid_amount' => 0];

    $totalEarnings = (float) ($row['total_earnings'] ?? 0);
    $paidAmount = (float) ($row['paid_amount'] ?? 0);
    $pendingAmount = $totalEarnings - $paidAmount;
    $status = derivePayoutStatus($totalEarnings, $paidAmount);

    return [
        'teacher_id' => $teacherId,
        'total_earnings' => $totalEarnings,
        'paid_amount' => $paidAmount,
        'pending_amount' => $pendingAmount,
        'status' => $status,
    ];
}

function getAllTeacherPayoutSummaries(string $statusFilter = ''): array
{
    $pdo = db();
    $stmt = $pdo->query(
        'SELECT
            u.id AS teacher_id,
            u.name AS teacher_name,
            u.email AS teacher_email,
            COALESCE(c.total_earnings, 0) AS total_earnings,
            COALESCE(p.paid_amount, 0) AS paid_amount
         FROM users u
         LEFT JOIN (
            SELECT teacher_id, SUM(payout_amount) AS total_earnings
            FROM class_sessions
            WHERE status = "completed"
            GROUP BY teacher_id
         ) c ON c.teacher_id = u.id
         LEFT JOIN (
            SELECT teacher_id, SUM(amount) AS paid_amount
            FROM teacher_payment_logs
            WHERE status = "paid"
            GROUP BY teacher_id
         ) p ON p.teacher_id = u.id
         WHERE u.role = "teacher"
         ORDER BY u.name'
    );
    $rows = $stmt->fetchAll() ?: [];
    $items = [];
    foreach ($rows as $row) {
        $totalEarnings = (float) ($row['total_earnings'] ?? 0);
        $paidAmount = (float) ($row['paid_amount'] ?? 0);
        $pendingAmount = $totalEarnings - $paidAmount;
        $status = derivePayoutStatus($totalEarnings, $paidAmount);
        if ($statusFilter !== '' && $statusFilter !== $status) {
            continue;
        }
        $items[] = [
            'teacher_id' => (int) ($row['teacher_id'] ?? 0),
            'name' => (string) ($row['teacher_name'] ?? ''),
            'email' => (string) ($row['teacher_email'] ?? ''),
            'total_earnings' => $totalEarnings,
            'paid_amount' => $paidAmount,
            'pending_amount' => $pendingAmount,
            'status' => $status,
        ];
    }

    return $items;
}

function refreshTeacherPaymentLogs(int $teacherId): void
{
    $pdo = db();
    $ins = $pdo->prepare(
        'INSERT INTO teacher_payment_logs (teacher_id, class_id, amount, status, created_at)
         SELECT cs.teacher_id, cs.id, cs.payout_amount, "pending", UTC_TIMESTAMP()
         FROM class_sessions cs
         WHERE cs.teacher_id = :tid
           AND cs.status = "completed"
           AND NOT EXISTS (
             SELECT 1 FROM teacher_payment_logs tpl WHERE tpl.class_id = cs.id
           )'
    );
    $ins->execute(['tid' => $teacherId]);
}

function createTeacherPaymentEntry(int $teacherId, float $amount, string $remarks = ''): void
{
    $pdo = db();
    if ($amount <= 0) {
        return;
    }

    $pdo->beginTransaction();
    try {
        // Mark completed-class logs as paid, oldest first, up to amount.
        $remaining = $amount;
        $pendingStmt = $pdo->prepare(
            'SELECT id, amount
             FROM teacher_payment_logs
             WHERE teacher_id = :tid AND status = "pending"
             ORDER BY created_at ASC, id ASC'
        );
        $pendingStmt->execute(['tid' => $teacherId]);
        $pendingRows = $pendingStmt->fetchAll() ?: [];
        $markPaid = $pdo->prepare('UPDATE teacher_payment_logs SET status = "paid" WHERE id = :id');
        foreach ($pendingRows as $row) {
            $logAmount = (float) ($row['amount'] ?? 0);
            if ($remaining < $logAmount || $logAmount <= 0) {
                continue;
            }
            $markPaid->execute(['id' => (int) $row['id']]);
            $remaining -= $logAmount;
            if ($remaining <= 0) {
                break;
            }
        }

        $summary = getTeacherPayoutSummary($teacherId);
        $entryStatus = $summary['status'];

        // Keep teacher_payments as payment transaction history.
        $stmt = $pdo->prepare(
            'INSERT INTO teacher_payments (teacher_id, total_amount, paid_amount, balance_amount, payment_status, payment_date, remarks, created_at)
             VALUES (:teacher_id, :total_amount, :paid_amount, :balance_amount, :payment_status, UTC_TIMESTAMP(), :remarks, UTC_TIMESTAMP())'
        );
        $stmt->execute([
            'teacher_id' => $teacherId,
            'total_amount' => (float) $summary['total_earnings'],
            'paid_amount' => $amount,
            'balance_amount' => (float) $summary['pending_amount'],
            'payment_status' => $entryStatus,
            'remarks' => $remarks,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

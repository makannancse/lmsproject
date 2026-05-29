<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';

class StudentPayment
{
    public static function createPendingForEnrollment(int $classId, int $studentId, float $amount): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO student_payments (student_id, class_id, amount, currency, status, payment_date, created_at)
             VALUES (:student_id, :class_id, :amount, "INR", "pending", NULL, NOW())'
        );
        $stmt->execute([
            'student_id' => $studentId,
            'class_id' => $classId,
            'amount' => round($amount, 2),
        ]);
    }

    public static function markPaid(int $paymentId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE student_payments
             SET status = "paid",
                 payment_date = COALESCE(payment_date, UTC_TIMESTAMP())
             WHERE id = :id'
        );
        $stmt->execute(['id' => $paymentId]);
    }

    public static function listForAdmin(?string $status = null, ?int $studentId = null): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT sp.*, s.name AS student_name, s.email AS student_email, cs.title AS class_title, cs.start_datetime
                FROM student_payments sp
                INNER JOIN users s ON s.id = sp.student_id
                INNER JOIN class_sessions cs ON cs.id = sp.class_id';
        $where = [];
        $params = [];

        if ($status !== null && in_array($status, ['pending', 'paid'], true)) {
            $where[] = 'sp.status = :status';
            $params['status'] = $status;
        }
        if ($studentId !== null && $studentId > 0) {
            $where[] = 'sp.student_id = :student_id';
            $params['student_id'] = $studentId;
        }
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY sp.created_at DESC, sp.id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public static function listForStudent(int $studentId, ?string $status = null): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT sp.*, cs.title AS class_title, cs.start_datetime
                FROM student_payments sp
                INNER JOIN class_sessions cs ON cs.id = sp.class_id
                WHERE sp.student_id = :student_id';
        $params = ['student_id' => $studentId];
        if ($status !== null && in_array($status, ['pending', 'paid'], true)) {
            $sql .= ' AND sp.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY sp.created_at DESC, sp.id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }
}


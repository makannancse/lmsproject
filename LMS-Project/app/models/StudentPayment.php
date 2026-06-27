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
            'amount' => function_exists('parseInrAmount') ? parseInrAmount($amount) : round((float) $amount, 2),
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

    public static function listForAdmin(?string $status = null, ?int $studentId = null, ?int $limit = null, ?int $offset = null): array
    {
        [$sql, $params] = self::buildAdminListQuery($status, $studentId);
        $sql .= ' ORDER BY sp.created_at DESC, sp.id DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
            $stmt->bindValue(':offset', max(0, $offset ?? 0), \PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public static function countForAdmin(?string $status = null, ?int $studentId = null): int
    {
        [$sql, $params] = self::buildAdminListQuery($status, $studentId, true);
        $pdo = Database::connection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * @return array{0: string, 1: array<string, scalar>}
     */
    private static function buildAdminListQuery(?string $status, ?int $studentId, bool $countOnly = false): array
    {
        $sql = $countOnly
            ? 'SELECT COUNT(*) FROM student_payments sp INNER JOIN users s ON s.id = sp.student_id INNER JOIN class_sessions cs ON cs.id = sp.class_id'
            : 'SELECT sp.*, s.name AS student_name, s.email AS student_email, cs.title AS class_title, cs.start_datetime
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

        return [$sql, $params];
    }

    public static function listForStudent(int $studentId, ?string $status = null, ?int $limit = null, ?int $offset = null): array
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
        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
            $stmt->bindValue(':offset', max(0, $offset ?? 0), \PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public static function countForStudent(int $studentId, ?string $status = null): int
    {
        $pdo = Database::connection();
        $sql = 'SELECT COUNT(*) FROM student_payments sp WHERE sp.student_id = :student_id';
        $params = ['student_id' => $studentId];
        if ($status !== null && in_array($status, ['pending', 'paid'], true)) {
            $sql .= ' AND sp.status = :status';
            $params['status'] = $status;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) ($stmt->fetchColumn() ?: 0);
    }
}


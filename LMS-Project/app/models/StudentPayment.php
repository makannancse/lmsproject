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

    /**
     * Self-healing routine to:
     * 1. Auto-create missing student_payments records for active enrollments in class_sessions.
     * 2. Auto-correct pending student_payments amounts to match class_sessions.student_fee whenever they differ.
     */
    public static function syncPaymentsFromEnrollments(): void
    {
        try {
            $pdo = Database::connection();

            // 1. Auto-create missing payment rows for active enrollments
            $missingSql = '
                INSERT INTO student_payments (student_id, class_id, amount, currency, status, payment_date, created_at)
                SELECT e.student_id, e.class_id, COALESCE(cs.student_fee, 0.00), "INR", "pending", NULL, NOW()
                FROM enrollments e
                INNER JOIN class_sessions cs ON cs.id = e.class_id
                LEFT JOIN student_payments sp ON sp.class_id = e.class_id AND sp.student_id = e.student_id
                WHERE e.status = "active" AND sp.id IS NULL
            ';
            $pdo->exec($missingSql);

            // 2. Auto-correct pending student_payments amounts where sp.amount != cs.student_fee and cs.student_fee > 0
            $fixMismatchSql = '
                UPDATE student_payments sp
                INNER JOIN class_sessions cs ON cs.id = sp.class_id
                SET sp.amount = cs.student_fee
                WHERE sp.status = "pending"
                  AND cs.student_fee > 0
                  AND ABS(sp.amount - cs.student_fee) > 0.01
            ';
            $pdo->exec($fixMismatchSql);
        } catch (\Throwable $ignored) {
            // Fail safe
        }
    }

    /**
     * @param array<string, mixed>|string|null $filtersOrStatus
     */
    public static function listForAdmin(mixed $filtersOrStatus = null, ?int $studentId = null, ?int $limit = null, ?int $offset = null): array
    {
        self::syncPaymentsFromEnrollments();
        $filters = is_array($filtersOrStatus)
            ? $filtersOrStatus
            : array_filter(['status' => $filtersOrStatus, 'student_id' => $studentId]);

        [$sql, $params] = self::buildAdminListQuery($filters);
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

    /**
     * @param array<string, mixed>|string|null $filtersOrStatus
     */
    public static function countForAdmin(mixed $filtersOrStatus = null, ?int $studentId = null): int
    {
        self::syncPaymentsFromEnrollments();
        $filters = is_array($filtersOrStatus)
            ? $filtersOrStatus
            : array_filter(['status' => $filtersOrStatus, 'student_id' => $studentId]);

        [$sql, $params] = self::buildAdminListQuery($filters, 'count');
        $pdo = Database::connection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Calculate total, pending, and paid amounts for the current filter scope.
     *
     * @param array<string, mixed> $filters
     * @return array{total_amount: float, pending_amount: float, paid_amount: float, total_count: int}
     */
    public static function sumForAdmin(array $filters = []): array
    {
        self::syncPaymentsFromEnrollments();
        [$sql, $params] = self::buildAdminListQuery($filters, 'sum');
        $pdo = Database::connection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        return [
            'total_amount' => (float) ($row['total_amount'] ?? 0),
            'pending_amount' => (float) ($row['pending_amount'] ?? 0),
            'paid_amount' => (float) ($row['paid_amount'] ?? 0),
            'total_count' => (int) ($row['total_count'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, scalar>}
     */
    private static function buildAdminListQuery(array $filters, string $mode = 'select'): array
    {
        if ($mode === 'count') {
            $select = 'SELECT COUNT(*)';
        } elseif ($mode === 'sum') {
            $select = 'SELECT 
                SUM(sp.amount) AS total_amount,
                SUM(CASE WHEN sp.status = "pending" THEN sp.amount ELSE 0 END) AS pending_amount,
                SUM(CASE WHEN sp.status = "paid" THEN sp.amount ELSE 0 END) AS paid_amount,
                COUNT(*) AS total_count';
        } else {
            $select = 'SELECT sp.*, 
                s.name AS student_name, s.email AS student_email,
                st.parent_email,
                cs.title AS class_title, cs.status AS class_status, cs.start_datetime, cs.start_time_utc, cs.timezone, cs.scheduled_timezone,
                t.name AS teacher_name';
        }

        $sql = $select . '
            FROM student_payments sp
            INNER JOIN users s ON s.id = sp.student_id
            LEFT JOIN students st ON st.user_id = s.id
            INNER JOIN class_sessions cs ON cs.id = sp.class_id
            LEFT JOIN users t ON t.id = cs.teacher_id';

        $where = [];
        $params = [];

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, ['pending', 'paid'], true)) {
            $where[] = 'sp.status = :status';
            $params['status'] = $status;
        }

        $classStatusFilter = trim((string) ($filters['class_status'] ?? ''));
        if ($classStatusFilter === 'completed') {
            $where[] = 'cs.status = "completed"';
        } elseif ($classStatusFilter === 'pending') {
            $where[] = 'cs.status != "completed"';
        }

        $studentId = (int) ($filters['student_id'] ?? 0);
        if ($studentId > 0) {
            $where[] = 'sp.student_id = :student_id';
            $params['student_id'] = $studentId;
        }

        $teacherId = (int) ($filters['teacher_id'] ?? 0);
        if ($teacherId > 0) {
            $where[] = 'cs.teacher_id = :teacher_id';
            $params['teacher_id'] = $teacherId;
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateFrom !== '' || $dateTo !== '') {
            $user = Auth::user();
            $userTz = resolveUserTimezone($user, APP_TIMEZONE);
            $df = $dateFrom !== '' ? $dateFrom : '1970-01-01';
            $dt = $dateTo !== '' ? $dateTo : '2099-12-31';
            try {
                $tz = new DateTimeZone($userTz);
                $utcFrom = (new DateTimeImmutable($df . ' 00:00:00', $tz))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                $utcTo = (new DateTimeImmutable($dt . ' 23:59:59', $tz))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                $where[] = '(COALESCE(cs.scheduled_time_utc, cs.start_time_utc, cs.start_datetime, sp.created_at) >= :utc_from AND COALESCE(cs.scheduled_time_utc, cs.start_time_utc, cs.start_datetime, sp.created_at) <= :utc_to)';
                $params['utc_from'] = $utcFrom;
                $params['utc_to'] = $utcTo;
            } catch (\Throwable $e) {
                if ($dateFrom !== '') {
                    $where[] = 'DATE(COALESCE(cs.scheduled_time_utc, cs.start_time_utc, cs.start_datetime, sp.created_at)) >= :date_from';
                    $params['date_from'] = $dateFrom;
                }
                if ($dateTo !== '') {
                    $where[] = 'DATE(COALESCE(cs.scheduled_time_utc, cs.start_time_utc, cs.start_datetime, sp.created_at)) <= :date_to';
                    $params['date_to'] = $dateTo;
                }
            }
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(cs.title LIKE :q1 OR s.name LIKE :q2 OR s.email LIKE :q3 OR st.parent_email LIKE :q4)';
            $params['q1'] = '%' . $query . '%';
            $params['q2'] = '%' . $query . '%';
            $params['q3'] = '%' . $query . '%';
            $params['q4'] = '%' . $query . '%';
        }

        if ($where !== []) {
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


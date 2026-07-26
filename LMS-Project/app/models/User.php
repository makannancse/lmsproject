<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';

class User
{
    public static function findByEmail(string $email): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function isActive(?array $user): bool
    {
        if ($user === null) {
            return false;
        }

        return strtolower((string) ($user['status'] ?? 'active')) === 'active';
    }

    public static function allTeachers(bool $activeOnly = true): array
    {
        return self::allByRole('teacher', $activeOnly);
    }

    public static function allStudents(bool $activeOnly = true): array
    {
        return self::allByRole('student', $activeOnly);
    }

    public static function allByRole(string $role, bool $activeOnly = false): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT * FROM users WHERE role = :role';
        if ($activeOnly) {
            $sql .= " AND status = 'active'";
        }
        $sql .= ' ORDER BY name';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['role' => $role]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function search(string $role, ?string $query = null, ?string $status = null): array
    {
        if (!in_array($role, ['student', 'teacher', 'admin'], true)) {
            $role = 'student';
        }

        $pdo = Database::connection();
        $sql = 'SELECT u.* FROM users u WHERE u.role = :role';
        $params = ['role' => $role];

        if ($status !== null && $status !== '' && in_array($status, ['active', 'inactive'], true)) {
            $sql .= ' AND u.status = :status';
            $params['status'] = $status;
        }

        if ($query !== null && trim($query) !== '') {
            $like = '%' . trim($query) . '%';
            $nameParts = preg_split('/\s+/', trim($query), 2) ?: [];
            $firstPart = (string) ($nameParts[0] ?? '');
            $lastPart = (string) ($nameParts[1] ?? '');

            if (self::usersTableHasPhoneColumn($pdo)) {
                $sql .= ' AND (
                    u.name LIKE :q_name OR u.email LIKE :q_email OR IFNULL(u.phone, \'\') LIKE :q_phone
                    OR SUBSTRING_INDEX(u.name, \' \', 1) LIKE :q_first
                    OR SUBSTRING_INDEX(u.name, \' \', -1) LIKE :q_last
                )';
                $params['q_name'] = $like;
                $params['q_email'] = $like;
                $params['q_phone'] = $like;
                $params['q_first'] = '%' . $firstPart . '%';
                $params['q_last'] = $lastPart !== '' ? ('%' . $lastPart . '%') : $like;
            } else {
                $sql .= ' AND (
                    u.name LIKE :q_name OR u.email LIKE :q_email
                    OR SUBSTRING_INDEX(u.name, \' \', 1) LIKE :q_first
                    OR SUBSTRING_INDEX(u.name, \' \', -1) LIKE :q_last
                )';
                $params['q_name'] = $like;
                $params['q_email'] = $like;
                $params['q_first'] = '%' . $firstPart . '%';
                $params['q_last'] = $lastPart !== '' ? ('%' . $lastPart . '%') : $like;
            }
        }

        $sql .= ' ORDER BY u.name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public static function countSearch(string $role, ?string $query = null, ?string $status = null): int
    {
        [$sql, $params] = self::buildSearchQuery($role, $query, $status, true);
        $pdo = Database::connection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function searchPaged(
        string $role,
        ?string $query,
        ?string $status,
        int $limit,
        int $offset
    ): array {
        [$sql, $params] = self::buildSearchQuery($role, $query, $status, false);
        $sql .= ' LIMIT :limit OFFSET :offset';
        $pdo = Database::connection();
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return array{0: string, 1: array<string, scalar>}
     */
    private static function buildSearchQuery(string $role, ?string $query, ?string $status, bool $countOnly): array
    {
        if (!in_array($role, ['student', 'teacher', 'admin'], true)) {
            $role = 'student';
        }

        $sql = $countOnly ? 'SELECT COUNT(*) FROM users u WHERE u.role = :role' : 'SELECT u.* FROM users u WHERE u.role = :role';
        $params = ['role' => $role];

        if ($status !== null && $status !== '' && in_array($status, ['active', 'inactive'], true)) {
            $sql .= ' AND u.status = :status';
            $params['status'] = $status;
        }

        if ($query !== null && trim($query) !== '') {
            $like = '%' . trim($query) . '%';
            $nameParts = preg_split('/\s+/', trim($query), 2) ?: [];
            $firstPart = (string) ($nameParts[0] ?? '');
            $lastPart = (string) ($nameParts[1] ?? '');
            $pdo = Database::connection();

            if (self::usersTableHasPhoneColumn($pdo)) {
                $sql .= ' AND (
                    u.name LIKE :q_name OR u.email LIKE :q_email OR IFNULL(u.phone, \'\') LIKE :q_phone
                    OR SUBSTRING_INDEX(u.name, \' \', 1) LIKE :q_first
                    OR SUBSTRING_INDEX(u.name, \' \', -1) LIKE :q_last
                )';
                $params['q_name'] = $like;
                $params['q_email'] = $like;
                $params['q_phone'] = $like;
                $params['q_first'] = '%' . $firstPart . '%';
                $params['q_last'] = $lastPart !== '' ? ('%' . $lastPart . '%') : $like;
            } else {
                $sql .= ' AND (
                    u.name LIKE :q_name OR u.email LIKE :q_email
                    OR SUBSTRING_INDEX(u.name, \' \', 1) LIKE :q_first
                    OR SUBSTRING_INDEX(u.name, \' \', -1) LIKE :q_last
                )';
                $params['q_name'] = $like;
                $params['q_email'] = $like;
                $params['q_first'] = '%' . $firstPart . '%';
                $params['q_last'] = $lastPart !== '' ? ('%' . $lastPart . '%') : $like;
            }
        }

        if (!$countOnly) {
            $sql .= ' ORDER BY u.name ASC';
        }

        return [$sql, $params];
    }

    /**
     * @return array{first_name: string, last_name: string}
     */
    public static function splitName(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['first_name' => '', 'last_name' => ''];
        }

        $parts = preg_split('/\s+/', $name, 2) ?: [];

        return [
            'first_name' => (string) ($parts[0] ?? ''),
            'last_name' => (string) ($parts[1] ?? ''),
        ];
    }

    public static function combineName(string $firstName, string $lastName): string
    {
        return trim($firstName . ' ' . $lastName);
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, phone, password_hash, role, timezone, status)
             VALUES (:name, :email, :phone, :password_hash, :role, :timezone, :status)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password_hash' => $data['password_hash'],
            'role' => $data['role'],
            'timezone' => $data['timezone'] ?? APP_TIMEZONE,
            'status' => $data['status'] ?? 'active',
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function updateCore(int $userId, array $data): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE users
             SET name = :name,
                 email = :email,
                 phone = :phone,
                 timezone = :timezone,
                 status = :status,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $userId,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'timezone' => $data['timezone'] ?? APP_TIMEZONE,
            'status' => $data['status'] ?? 'active',
        ]);
    }

    public static function setStatus(int $userId, string $status): void
    {
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new \InvalidArgumentException('Invalid status');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE users SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute(['id' => $userId, 'status' => $status]);

        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare('SELECT status FROM users WHERE id = :id LIMIT 1');
            $check->execute(['id' => $userId]);
            $current = $check->fetchColumn();
            if ($current === false) {
                throw new \RuntimeException('User not found.');
            }
            if (strtolower((string) $current) !== $status) {
                throw new \RuntimeException('Status was not updated. Verify users.status column exists.');
            }
        }
    }

    /**
     * Merge extended profile row without overwriting users.id (students/teachers have their own id column).
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $profileRow
     * @return array<string, mixed>
     */
    private static function mergeProfileRow(array $user, array $profileRow): array
    {
        if (isset($profileRow['id'])) {
            $profileRow['profile_row_id'] = $profileRow['id'];
            unset($profileRow['id']);
        }
        unset($profileRow['user_id']);

        return array_merge($user, $profileRow);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function profileForUser(int $userId, string $role): ?array
    {
        $user = self::findById($userId);
        if ($user === null || (string) ($user['role'] ?? '') !== $role) {
            return null;
        }

        $pdo = Database::connection();
        if ($role === 'student') {
            $stmt = $pdo->prepare(
                'SELECT s.*, (
                    SELECT ts.teacher_id FROM teacher_students ts
                    WHERE ts.student_id = s.user_id
                    ORDER BY ts.id ASC LIMIT 1
                 ) AS assigned_teacher_id
                 FROM students s WHERE s.user_id = :uid LIMIT 1'
            );
            $stmt->execute(['uid' => $userId]);
            $row = $stmt->fetch() ?: [];

            return self::mergeProfileRow($user, $row);
        }

        if ($role === 'teacher') {
            $stmt = $pdo->prepare('SELECT * FROM teachers WHERE user_id = :uid LIMIT 1');
            $stmt->execute(['uid' => $userId]);
            $row = $stmt->fetch() ?: [];

            return self::mergeProfileRow($user, $row);
        }

        return $user;
    }

    public static function emailInUseByOtherUser(string $email, int $excludeUserId): bool
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return false;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id FROM users WHERE LOWER(TRIM(email)) = :email AND id != :id LIMIT 1'
        );
        $stmt->execute([
            'email' => $email,
            'id' => $excludeUserId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private static function usersTableHasPhoneColumn(\PDO $pdo): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'");
            $cached = (bool) $stmt->fetch();
        } catch (\Throwable) {
            $cached = false;
        }

        return $cached;
    }

    /**
     * Permanently remove a student or teacher and related records (admin only).
     */
    public static function permanentlyDelete(int $userId, string $expectedRole): void
    {
        if ($userId <= 0 || !in_array($expectedRole, ['student', 'teacher'], true)) {
            throw new \InvalidArgumentException('Invalid delete request.');
        }

        $user = self::findById($userId);
        if ($user === null || (string) ($user['role'] ?? '') !== $expectedRole) {
            throw new \RuntimeException('User not found.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if ($expectedRole === 'student') {
                $pdo->prepare('DELETE FROM homework_submissions WHERE student_id = :id')->execute(['id' => $userId]);
                $pdo->prepare('DELETE FROM homework_assigned_students WHERE student_id = :id')->execute(['id' => $userId]);
                $pdo->prepare('DELETE FROM feedback WHERE student_id = :id')->execute(['id' => $userId]);
                $pdo->prepare('DELETE FROM enrollments WHERE student_id = :id')->execute(['id' => $userId]);
                $pdo->prepare('DELETE FROM teacher_students WHERE student_id = :id')->execute(['id' => $userId]);
                $pdo->prepare('DELETE FROM student_payments WHERE student_id = :id')->execute(['id' => $userId]);
                $pdo->prepare('DELETE FROM recurring_series_students WHERE student_id = :id')->execute(['id' => $userId]);
                $pdo->prepare('DELETE FROM reschedule_requests WHERE student_id = :id')->execute(['id' => $userId]);
                $pdo->prepare('DELETE FROM student_reports WHERE student_id = :id')->execute(['id' => $userId]);
                $pdo->prepare('DELETE FROM students WHERE user_id = :id')->execute(['id' => $userId]);
            } else {
                // 1. Delete recurring series and dependencies for this teacher
                $seriesIdsStmt = $pdo->prepare('SELECT id FROM recurring_series WHERE teacher_id = :id');
                $seriesIdsStmt->execute(['id' => $userId]);
                $seriesIds = array_map(static fn(array $r): int => (int) $r['id'], $seriesIdsStmt->fetchAll() ?: []);
                if ($seriesIds !== []) {
                    $inSeries = implode(',', array_fill(0, count($seriesIds), '?'));
                    $pdo->prepare("DELETE FROM recurring_series_students WHERE series_id IN ($inSeries)")->execute($seriesIds);
                    $pdo->prepare("DELETE FROM recurring_occurrences WHERE series_id IN ($inSeries)")->execute($seriesIds);
                    $pdo->prepare("DELETE FROM recurring_series WHERE id IN ($inSeries)")->execute($seriesIds);
                }
                $pdo->prepare('DELETE FROM recurring_series WHERE teacher_id = :id')->execute(['id' => $userId]);

                // 2. Delete class sessions and dependencies for this teacher
                $classIdsStmt = $pdo->prepare('SELECT id FROM class_sessions WHERE teacher_id = :id');
                $classIdsStmt->execute(['id' => $userId]);
                $classIds = array_map(static fn(array $r): int => (int) $r['id'], $classIdsStmt->fetchAll() ?: []);
                if ($classIds !== []) {
                    $in = implode(',', array_fill(0, count($classIds), '?'));
                    $pdo->prepare("DELETE FROM enrollments WHERE class_id IN ($in)")->execute($classIds);
                    $pdo->prepare("DELETE FROM class_recordings WHERE class_id IN ($in)")->execute($classIds);
                    $pdo->prepare("DELETE FROM reschedule_requests WHERE class_id IN ($in)")->execute($classIds);
                    $pdo->prepare("DELETE FROM class_attendance WHERE class_id IN ($in)")->execute($classIds);
                    $pdo->prepare("DELETE FROM teacher_payouts WHERE class_id IN ($in)")->execute($classIds);
                    $pdo->prepare("DELETE FROM teacher_payment_logs WHERE class_id IN ($in)")->execute($classIds);
                    $pdo->prepare("DELETE FROM student_payments WHERE class_id IN ($in)")->execute($classIds);
                    $pdo->prepare("DELETE FROM meeting_activity_logs WHERE class_id IN ($in)")->execute($classIds);
                }
                $pdo->prepare('DELETE FROM class_sessions WHERE teacher_id = :id')->execute(['id' => $userId]);

                // 3. Delete homeworks created by or assigned to this teacher
                $hwStmt = $pdo->prepare('SELECT id FROM homeworks WHERE teacher_id = :id1 OR created_by = :id2');
                $hwStmt->execute(['id1' => $userId, 'id2' => $userId]);
                $hwIds = array_map(static fn(array $r): int => (int) $r['id'], $hwStmt->fetchAll() ?: []);
                if ($hwIds !== []) {
                    $inHw = implode(',', array_fill(0, count($hwIds), '?'));
                    $pdo->prepare("DELETE FROM homework_submissions WHERE homework_id IN ($inHw)")->execute($hwIds);
                    $pdo->prepare("DELETE FROM homework_attachments WHERE homework_id IN ($inHw)")->execute($hwIds);
                    $pdo->prepare("DELETE FROM homework_assigned_students WHERE homework_id IN ($inHw)")->execute($hwIds);
                    $pdo->prepare("DELETE FROM homeworks WHERE id IN ($inHw)")->execute($hwIds);
                }
                $pdo->prepare('DELETE FROM homeworks WHERE teacher_id = :id1 OR created_by = :id2')->execute(['id1' => $userId, 'id2' => $userId]);

                // 4. Delete remaining teacher-specific table entries
                $pdo->prepare('DELETE FROM feedback WHERE teacher_id = :id')->execute(['id' => $userId]);
                $pdo->prepare('DELETE FROM teacher_students WHERE teacher_id = :id')->execute(['id' => $userId]);
                $pdo->prepare('DELETE FROM teacher_google_accounts WHERE teacher_id = :id')->execute(['id' => $userId]);
                $pdo->prepare('DELETE FROM teacher_payments WHERE teacher_id = :id')->execute(['id' => $userId]);
                $pdo->prepare('DELETE FROM teacher_payment_logs WHERE teacher_id = :id')->execute(['id' => $userId]);
                $pdo->prepare('DELETE FROM teacher_payouts WHERE teacher_id = :id')->execute(['id' => $userId]);
                $pdo->prepare('DELETE FROM teacher_availability WHERE teacher_id = :id')->execute(['id' => $userId]);
                $pdo->prepare('DELETE FROM student_reports WHERE teacher_id = :id')->execute(['id' => $userId]);
                $pdo->prepare('DELETE FROM reschedule_requests WHERE teacher_id = :id1 OR requested_by = :id2')->execute(['id1' => $userId, 'id2' => $userId]);
                $pdo->prepare('DELETE FROM teachers WHERE user_id = :id')->execute(['id' => $userId]);
            }

            $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :id')->execute(['id' => $userId]);
            $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

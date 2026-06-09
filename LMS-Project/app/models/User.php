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
            $sql .= ' AND (u.name LIKE :q OR u.email LIKE :q OR u.phone LIKE :q)';
            $params['q'] = '%' . trim($query) . '%';
        }

        $sql .= ' ORDER BY u.name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
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
}

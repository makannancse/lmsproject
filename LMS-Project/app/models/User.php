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

    public static function allTeachers(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query("SELECT * FROM users WHERE role = 'teacher' ORDER BY name");
        return $stmt->fetchAll() ?: [];
    }

    public static function allStudents(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query("SELECT * FROM users WHERE role = 'student' ORDER BY name");
        return $stmt->fetchAll() ?: [];
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role, timezone, status)
             VALUES (:name, :email, :password_hash, :role, :timezone, :status)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => $data['password_hash'],
            'role' => $data['role'],
            'timezone' => $data['timezone'] ?? APP_TIMEZONE,
            'status' => $data['status'] ?? 'active',
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function allByRole(string $role): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE role = :role ORDER BY name');
        $stmt->execute(['role' => $role]);
        return $stmt->fetchAll() ?: [];
    }
}




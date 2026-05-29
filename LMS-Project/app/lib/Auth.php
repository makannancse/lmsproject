<?php

declare(strict_types=1);

class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            session_regenerate_id(true);
        }
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function role(): ?string
    {
        return $_SESSION['user']['role'] ?? null;
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function attempt(array $user): void
    {
        self::startSession();
        $_SESSION['user'] = $user;
    }

    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }

    public static function requireRole(array $roles): void
    {
        if (!self::check() || !in_array($_SESSION['user']['role'] ?? null, $roles, true)) {
            $base = defined('BASE_PATH') ? BASE_PATH : '';
            header('Location: ' . $base . '/login');
            exit;
        }
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function isTeacher(): bool
    {
        return self::role() === 'teacher';
    }

    public static function isStudent(): bool
    {
        return self::role() === 'student';
    }
}


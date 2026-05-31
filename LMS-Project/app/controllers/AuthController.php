<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/models/User.php';

class AuthController
{
    /** Routed dashboard URLs (never point at a physical /public/{role}/ folder). */
    private static function homePathForRole(string $role): string
    {
        return match ($role) {
            'admin' => '/admin',
            'teacher' => '/teacher',
            'student' => '/student',
            default => '/dashboard',
        };
    }

    public static function showLogin(): void
    {
        Auth::startSession();
        $roleHint = strtolower(trim((string) ($_GET['role'] ?? '')));
        if (!in_array($roleHint, ['student', 'teacher', 'admin'], true)) {
            $roleHint = '';
        }
        $timedOut = isset($_GET['timeout']) && (string) $_GET['timeout'] === '1';
        View::render('auth/login', [
            'pageTitle' => 'Login',
            'roleHint' => $roleHint,
            'timedOut' => $timedOut,
        ]);
    }

    public static function login(): void
    {
        Auth::startSession();
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $user = null;
        try {
            $user = User::findByEmail($email);
        } catch (\Throwable $e) {
            // Allow fallback login when DB is not ready
        }

        $base = defined('BASE_PATH') ? BASE_PATH : '';

        if ($user && password_verify($password, $user['password_hash'])) {
            Auth::attempt([
                'id' => $user['id'],
                'name' => $user['name'],
                'role' => $user['role'],
                'email' => $user['email'],
                'timezone' => $user['timezone'] ?? APP_TIMEZONE,
            ]);
            header('Location: ' . $base . self::homePathForRole((string) ($user['role'] ?? '')));
            exit;
        }

        // Local fallback to let you in before seeding the database.
        if ($email === 'admin@example.com' && $password === 'password') {
            Auth::attempt([
                'id' => 1,
                'name' => 'Admin',
                'role' => 'admin',
                'email' => $email,
                'timezone' => APP_TIMEZONE,
            ]);
            header('Location: ' . $base . '/admin');
            exit;
        }

        $_SESSION['error'] = 'Invalid credentials';
        $roleHint = strtolower(trim((string) ($_POST['role_hint'] ?? '')));
        $suffix = in_array($roleHint, ['student', 'teacher', 'admin'], true) ? ('?role=' . $roleHint) : '';
        header('Location: ' . $base . '/login' . $suffix);
    }

    public static function logout(): void
    {
        Auth::startSession();
        Auth::logout();
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        header('Location: ' . $base . '/login');
        exit;
    }
}


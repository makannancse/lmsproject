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
        $deactivated = isset($_GET['deactivated']) && (string) $_GET['deactivated'] === '1';
        if ($deactivated && empty($_SESSION['error'])) {
            $_SESSION['error'] = 'This account has been deactivated. Please contact the administrator.';
        }
        View::render('auth/login', [
            'pageTitle' => 'Login',
            'roleHint' => $roleHint,
            'timedOut' => $timedOut,
        ]);
    }

    public static function login(): void
    {
        Auth::startSession();
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $roleHint = strtolower(trim((string) ($_POST['role_hint'] ?? '')));

        logAdminLogin([
            'event' => 'login_attempt',
            'email' => $email,
            'role_hint' => $roleHint,
            'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);

        $user = null;
        $dbError = null;
        try {
            $user = User::findByEmail($email);
        } catch (\Throwable $e) {
            $dbError = $e->getMessage();
        }

        if ($user && password_verify($password, $user['password_hash'])) {
            if (!User::isActive($user)) {
                logAdminLogin([
                    'event' => 'login_rejected_inactive',
                    'email' => $email,
                    'user_id' => (int) ($user['id'] ?? 0),
                    'role' => (string) ($user['role'] ?? ''),
                    'result' => 'inactive',
                ]);
                $_SESSION['error'] = 'This account has been deactivated. Please contact the administrator.';
                $suffix = $roleHint !== '' ? ('?role=' . urlencode($roleHint)) : '';
                redirectTo('/login' . $suffix, 302, [
                    'event' => 'login_redirect_inactive',
                    'email' => $email,
                ]);
            }

            Auth::attempt([
                'id' => $user['id'],
                'name' => $user['name'],
                'role' => $user['role'],
                'email' => $user['email'],
                'timezone' => $user['timezone'] ?? APP_TIMEZONE,
                'status' => $user['status'] ?? 'active',
            ]);

            $home = self::homePathForRole((string) ($user['role'] ?? ''));
            logAdminLogin([
                'event' => 'login_success',
                'email' => $email,
                'user_id' => (int) ($user['id'] ?? 0),
                'role' => (string) ($user['role'] ?? ''),
                'session_created' => true,
                'authentication_result' => 'success',
                'final_destination' => $home,
            ]);

            redirectTo($home, 302, [
                'event' => 'login_redirect_success',
                'user_id' => (int) ($user['id'] ?? 0),
                'role' => (string) ($user['role'] ?? ''),
            ]);
        }

        // Local fallback to let you in before seeding the database.
        if ($email === 'admin@example.com' && $password === 'password') {
            Auth::attempt([
                'id' => 1,
                'name' => 'Admin',
                'role' => 'admin',
                'email' => $email,
                'timezone' => APP_TIMEZONE,
                'status' => 'active',
            ]);
            logAdminLogin([
                'event' => 'login_success_fallback',
                'email' => $email,
                'role' => 'admin',
                'authentication_result' => 'fallback',
                'final_destination' => '/admin',
            ]);
            redirectTo('/admin', 302, ['event' => 'login_redirect_fallback']);
        }

        logAdminLogin([
            'event' => 'login_failed',
            'email' => $email,
            'authentication_result' => 'invalid_credentials',
            'db_error' => $dbError,
        ]);

        $_SESSION['error'] = 'Invalid credentials';
        $suffix = in_array($roleHint, ['student', 'teacher', 'admin'], true) ? ('?role=' . $roleHint) : '';
        redirectTo('/login' . $suffix, 302, ['event' => 'login_redirect_failed']);
    }

    public static function logout(): void
    {
        Auth::startSession();
        logAdminLogin([
            'event' => 'logout',
            'user_id' => Auth::userId(),
            'role' => Auth::role(),
        ]);
        Auth::logout();
        redirectTo('/login', 302, ['event' => 'logout_redirect']);
    }

    public static function showForgotPassword(): void
    {
        Auth::startSession();
        View::render('auth/forgot-password', [
            'pageTitle' => 'Forgot Password',
        ]);
    }

    public static function sendForgotPassword(): void
    {
        Auth::startSession();
        require_once dirname(__DIR__) . '/lib/PasswordResetService.php';

        $email = trim((string) ($_POST['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'Please enter a valid email address.';
            redirectTo('/forgot-password');
        }

        PasswordResetService::createTokenForEmail($email);
        $_SESSION['flash_success'] = 'If an account exists for that email, a reset link has been sent.';
        redirectTo('/login');
    }

    public static function showResetPassword(): void
    {
        Auth::startSession();
        require_once dirname(__DIR__) . '/lib/PasswordResetService.php';

        $token = trim((string) ($_GET['token'] ?? ''));
        $check = PasswordResetService::validateToken($token);
        View::render('auth/reset-password', [
            'pageTitle' => 'Reset Password',
            'token' => $token,
            'valid' => $check['valid'],
            'error' => $check['error'] ?? null,
        ]);
    }

    public static function resetPassword(): void
    {
        Auth::startSession();
        require_once dirname(__DIR__) . '/lib/PasswordResetService.php';

        $token = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        $result = PasswordResetService::resetPassword($token, $password, $confirm);
        if (!$result['success']) {
            $_SESSION['error'] = (string) ($result['error'] ?? 'Could not reset password.');
            redirectTo('/reset-password?token=' . urlencode($token));
        }

        $_SESSION['flash_success'] = 'Your password has been updated. Please sign in.';
        redirectTo('/login');
    }
}

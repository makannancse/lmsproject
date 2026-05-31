<?php

declare(strict_types=1);

class Auth
{
    public const SESSION_TIMEOUT_SECONDS = 900;

    private static bool $activityChecked = false;

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::configureSession();
            session_start();
        }

        if (self::$activityChecked) {
            return;
        }
        self::$activityChecked = true;

        if (!self::check()) {
            return;
        }

        if (self::isActivityExpired()) {
            self::expireSession('timeout');
            self::respondToExpiredSession();
        }

        self::touchActivityIfInteractive();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']) && is_array($_SESSION['user']);
    }

    public static function role(): ?string
    {
        return $_SESSION['role'] ?? ($_SESSION['user']['role'] ?? null);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function userId(): int
    {
        if (isset($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }

        return (int) ($_SESSION['user']['id'] ?? 0);
    }

    public static function attempt(array $user): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::configureSession();
            session_start();
        }

        session_regenerate_id(true);

        $_SESSION['user'] = $user;
        $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
        $_SESSION['role'] = (string) ($user['role'] ?? '');
        $_SESSION['last_activity'] = time();

        self::$activityChecked = true;

        self::logSessionEvent('login', [
            'user_id' => self::userId(),
            'role' => self::role(),
            'email' => (string) ($user['email'] ?? ''),
            'session_regenerated' => true,
        ]);
    }

    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::configureSession();
            session_start();
        }

        if (self::check()) {
            self::logSessionEvent('logout', [
                'user_id' => self::userId(),
                'role' => self::role(),
            ]);
        }

        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    (bool) ($params['secure'] ?? false),
                    (bool) ($params['httponly'] ?? true)
                );
            }
            session_destroy();
        }

        self::$activityChecked = false;
    }

    public static function touchActivity(): void
    {
        if (!self::check()) {
            return;
        }

        $_SESSION['last_activity'] = time();
    }

    /**
     * Extend the session only for interactive use (page loads, form posts, keep-alive).
     * Background JSON polling must not reset the inactivity timer.
     */
    public static function touchActivityIfInteractive(): void
    {
        if (!self::check() || self::isBackgroundSessionRequest()) {
            return;
        }

        self::touchActivity();
    }

    private static function isBackgroundSessionRequest(): bool
    {
        if (self::isKeepAliveRequest()) {
            return false;
        }

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            return false;
        }

        return self::wantsJsonResponse();
    }

    private static function isKeepAliveRequest(): bool
    {
        $path = strtolower((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ''));

        return str_contains($path, '/ajax/keepalive');
    }

    public static function lastActivity(): int
    {
        return (int) ($_SESSION['last_activity'] ?? 0);
    }

    public static function secondsUntilTimeout(): int
    {
        if (!self::check()) {
            return 0;
        }

        $last = self::lastActivity();
        if ($last <= 0) {
            return self::SESSION_TIMEOUT_SECONDS;
        }

        return max(0, self::SESSION_TIMEOUT_SECONDS - (time() - $last));
    }

    public static function requireRole(array $roles): void
    {
        self::startSession();

        if (!self::check() || !in_array(self::role(), $roles, true)) {
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

    /**
     * @param array<string, mixed> $context
     */
    public static function logSessionEvent(string $event, array $context = []): void
    {
        if (!function_exists('writeStructuredLog')) {
            return;
        }

        $context['event'] = $event;
        $context['session_id'] = session_status() === PHP_SESSION_ACTIVE ? session_id() : null;
        $context['ip'] = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $context['user_agent'] = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 180);
        writeStructuredLog('session_debug.log', $context);
    }

    private static function configureSession(): void
    {
        ini_set('session.gc_maxlifetime', (string) self::SESSION_TIMEOUT_SECONDS);
        ini_set('session.cookie_lifetime', '0');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        $secure = self::isHttpsRequest();

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'httponly' => true,
                'secure' => $secure,
                'samesite' => 'Lax',
            ]);
        } else {
            session_set_cookie_params(0, '/; samesite=Lax', '', $secure, true);
        }

        if (session_status() === PHP_SESSION_NONE && session_name() === 'PHPSESSID') {
            session_name('LEARNWISESESSID');
        }
    }

    private static function isHttpsRequest(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        return (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    private static function isActivityExpired(): bool
    {
        $last = (int) ($_SESSION['last_activity'] ?? 0);
        if ($last <= 0) {
            $_SESSION['last_activity'] = time();

            return false;
        }

        return (time() - $last) > self::SESSION_TIMEOUT_SECONDS;
    }

    private static function expireSession(string $reason): void
    {
        self::logSessionEvent($reason, [
            'user_id' => self::userId(),
            'role' => self::role(),
            'last_activity' => self::lastActivity(),
            'idle_seconds' => self::lastActivity() > 0 ? (time() - self::lastActivity()) : null,
        ]);

        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        self::$activityChecked = false;
    }

    private static function respondToExpiredSession(): void
    {
        $base = defined('BASE_PATH') ? BASE_PATH : '';

        if (self::wantsJsonResponse()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'status' => 'expired',
                'message' => 'Session expired due to inactivity.',
            ], JSON_UNESCAPED_SLASHES);
            exit;
        }

        header('Location: ' . $base . '/login?timeout=1');
        exit;
    }

    private static function wantsJsonResponse(): bool
    {
        $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');

        return $xhr || str_contains($accept, 'application/json');
    }
}

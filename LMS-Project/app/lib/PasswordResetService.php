<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/Mailer.php';
require_once dirname(__DIR__) . '/lib/EmailTemplate.php';
require_once dirname(__DIR__) . '/models/User.php';

class PasswordResetService
{
    private const TOKEN_TTL_SECONDS = 3600;

    public static function createTokenForEmail(string $email): bool
    {
        $email = trim(strtolower($email));
        if ($email === '') {
            return true;
        }

        $user = User::findByEmail($email);
        if ($user === null || !User::isActive($user)) {
            return true;
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = gmdate('Y-m-d H:i:s', time() + self::TOKEN_TTL_SECONDS);

        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :uid OR expires_at < UTC_TIMESTAMP()')
            ->execute(['uid' => (int) $user['id']]);

        $pdo->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (:uid, :hash, :expires)'
        )->execute([
            'uid' => (int) $user['id'],
            'hash' => $hash,
            'expires' => $expires,
        ]);

        $resetUrl = EmailTemplate::sanitizeUrlForEmail(url('reset-password?token=' . urlencode($token)));
        $safeName = htmlspecialchars((string) ($user['name'] ?? 'User'), ENT_QUOTES, 'UTF-8');
        $subject = EmailTemplate::subject('password_reset');
        $intro = '<p>Hi ' . $safeName . ',</p>'
            . '<p>We received a request to reset your password. This link expires in 1 hour.</p>'
            . '<p>If you did not request this, you can ignore this email.</p>';
        $body = EmailTemplate::wrap('Password Reset', $intro, [], 'Reset Password', $resetUrl);

        Mailer::send((string) $user['email'], $subject, $body, true);

        return true;
    }

    /**
     * @return array{valid:bool,user?:array<string,mixed>,error?:string}
     */
    public static function validateToken(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            return ['valid' => false, 'error' => 'Invalid reset link.'];
        }

        $hash = hash('sha256', $token);
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT prt.*, u.id, u.email, u.name, u.role, u.status
             FROM password_reset_tokens prt
             INNER JOIN users u ON u.id = prt.user_id
             WHERE prt.token_hash = :hash
               AND prt.used_at IS NULL
               AND prt.expires_at > UTC_TIMESTAMP()
             LIMIT 1'
        );
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['valid' => false, 'error' => 'This reset link is invalid or has expired.'];
        }

        return ['valid' => true, 'user' => $row];
    }

    public static function resetPassword(string $token, string $password, string $confirmPassword): array
    {
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
        }
        if (!hash_equals($password, $confirmPassword)) {
            return ['success' => false, 'error' => 'Passwords do not match.'];
        }

        $check = self::validateToken($token);
        if (!$check['valid']) {
            return ['success' => false, 'error' => (string) ($check['error'] ?? 'Invalid token.')];
        }

        $userId = (int) ($check['user']['user_id'] ?? $check['user']['id'] ?? 0);
        if ($userId <= 0) {
            return ['success' => false, 'error' => 'Invalid user.'];
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
                ->execute([
                    'hash' => password_hash($password, PASSWORD_BCRYPT),
                    'id' => $userId,
                ]);
            $pdo->prepare(
                'UPDATE password_reset_tokens SET used_at = UTC_TIMESTAMP() WHERE user_id = :uid AND used_at IS NULL'
            )->execute(['uid' => $userId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();

            return ['success' => false, 'error' => 'Could not reset password.'];
        }

        return ['success' => true];
    }
}

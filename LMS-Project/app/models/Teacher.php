<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';

class Teacher
{
    public static function ensureForUser(int $userId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM teachers WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        if ($stmt->fetch()) {
            return;
        }

        $ins = $pdo->prepare(
            'INSERT INTO teachers (user_id, employment_type, hourly_rate, notes, created_at, updated_at)
             VALUES (:uid, "part_time", NULL, NULL, NOW(), NOW())'
        );
        $ins->execute(['uid' => $userId]);
    }

    public static function setGoogleRefreshTokenEncrypted(int $userId, string $encryptedToken): void
    {
        self::ensureForUser($userId);
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE teachers
             SET google_refresh_token = :token, google_connected_at = NOW(), updated_at = NOW()
             WHERE user_id = :uid'
        );
        $stmt->execute(['token' => $encryptedToken, 'uid' => $userId]);
    }

    public static function getGoogleRefreshTokenEncrypted(int $userId): ?string
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT google_refresh_token FROM teachers WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $val = $stmt->fetchColumn();
        if ($val === false || $val === null || trim((string) $val) === '') {
            return null;
        }
        return (string) $val;
    }
}

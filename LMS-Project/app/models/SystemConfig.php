<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';

class SystemConfig
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT value FROM system_config WHERE `key` = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch();
        if ($row === false) {
            return $default;
        }
        return $row['value'] ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO system_config (`key`, `value`, updated_at) 
             VALUES (:key, :value, NOW())
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()'
        );
        $stmt->execute(['key' => $key, 'value' => $value]);
    }
}


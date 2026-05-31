<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';

class ClassMaster
{
    public static function allActive(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query(
            "SELECT * FROM class_master WHERE status = 'active' ORDER BY class_name"
        );
        return $stmt->fetchAll() ?: [];
    }

    public static function all(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT * FROM class_master ORDER BY class_name');
        return $stmt->fetchAll() ?: [];
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM class_master WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}

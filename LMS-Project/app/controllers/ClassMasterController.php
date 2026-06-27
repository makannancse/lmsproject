<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/View.php';

class ClassMasterController
{
    public static function index(): void
    {
        Auth::requireRole(['admin']);
        $pdo = Database::connection();
        $rows = $pdo->query('SELECT * FROM class_master ORDER BY class_name')->fetchAll() ?: [];

        View::render('admin/class_types/index', [
            'pageTitle' => 'Class Types',
            'types' => $rows,
        ]);
    }

    public static function createForm(): void
    {
        Auth::requireRole(['admin']);
        View::render('admin/class_types/create', ['pageTitle' => 'Add Class Type']);
    }

    public static function store(): void
    {
        Auth::requireRole(['admin']);
        $pdo = Database::connection();

        $name = trim($_POST['class_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if ($name === '' || !in_array($status, ['active', 'inactive'], true)) {
            $base = appWebPath();
            redirectTo('/admin/class-types/create');
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO class_master (class_name, description, status) VALUES (:n, :d, :s)'
        );
        $stmt->execute(['n' => $name, 'd' => $description ?: null, 's' => $status]);

        $base = appWebPath();
        redirectTo('/admin/class-types');
    }

    public static function editForm(): void
    {
        Auth::requireRole(['admin']);
        $id = (int) ($_GET['id'] ?? 0);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM class_master WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            echo 'Not found';
            return;
        }
        View::render('admin/class_types/edit', [
            'pageTitle' => 'Edit Class Type',
            'type' => $row,
        ]);
    }

    public static function update(): void
    {
        Auth::requireRole(['admin']);
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['class_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if ($id <= 0 || $name === '' || !in_array($status, ['active', 'inactive'], true)) {
            $base = appWebPath();
            redirectTo('/admin/class-types');
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE class_master SET class_name = :n, description = :d, status = :s WHERE id = :id'
        );
        $stmt->execute(['n' => $name, 'd' => $description ?: null, 's' => $status, 'id' => $id]);

        $base = appWebPath();
        redirectTo('/admin/class-types');
    }
}

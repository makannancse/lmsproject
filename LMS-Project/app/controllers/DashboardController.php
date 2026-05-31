<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';

class DashboardController
{
    public static function index(): void
    {
        Auth::requireRole(['admin', 'teacher', 'student']);
        $user = Auth::user();
        $role = $user['role'] ?? 'user';
        $base = defined('BASE_PATH') ? BASE_PATH : '';

        // Simple role-based redirect to dedicated dashboards
        if ($role === 'admin') {
            header('Location: ' . $base . '/admin');
            return;
        }
        if ($role === 'teacher') {
            header('Location: ' . $base . '/teacher');
            return;
        }
        if ($role === 'student') {
            header('Location: ' . $base . '/student');
            return;
        }

        // Fallback
        View::render('dashboard/index', [
            'pageTitle' => 'Dashboard',
        ]);
    }
}


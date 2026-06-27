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
        $base = appWebPath();

        // Simple role-based redirect to dedicated dashboards
        if ($role === 'admin') {
            redirectTo('/admin');
            return;
        }
        if ($role === 'teacher') {
            redirectTo('/teacher');
            return;
        }
        if ($role === 'student') {
            redirectTo('/student');
            return;
        }

        // Fallback
        View::render('dashboard/index', [
            'pageTitle' => 'Dashboard',
        ]);
    }
}


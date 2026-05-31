<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/models/ClassSession.php';
require_once dirname(__DIR__) . '/models/ClassRecording.php';

class StudentController
{
    public static function dashboard(): void
    {
        Auth::requireRole(['student']);
        $user = Auth::user();
        $studentId = (int) ($user['id'] ?? 0);

        $upcoming = ClassSession::findUpcomingByStudent($studentId);
        $completed = ClassSession::findCompletedByStudent($studentId, 15);
        $recordings = ClassRecording::listVisibleForStudent($studentId, 12);

        View::render('student/dashboard', [
            'pageTitle' => 'Student Dashboard',
            'upcomingClasses' => $upcoming,
            'completedClasses' => $completed,
            'recordings' => $recordings,
        ]);
    }
}

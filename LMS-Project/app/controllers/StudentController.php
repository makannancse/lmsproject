<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/models/ClassSession.php';
require_once dirname(__DIR__) . '/models/ClassRecording.php';
require_once dirname(__DIR__) . '/models/TeacherStudent.php';

class StudentController
{
    public static function dashboard(): void
    {
        Auth::requireRole(['student']);
        $user = Auth::user();
        $studentId = (int) ($user['id'] ?? 0);
        $pdo = Database::connection();

        $assignedTeachers = TeacherStudent::assignedTeachersForStudent($studentId);
        $upcomingCount = ClassSession::countUpcomingAppointmentsForStudent($studentId);
        $upcomingClasses = ClassSession::findUpcomingByStudent($studentId, 5);
        $nextClass = $upcomingClasses[0] ?? null;
        $recordings = ClassRecording::listVisibleForStudent($studentId, 6);
        $completed = ClassSession::findCompletedByStudent($studentId, 5);
        $attendancePercent = ClassSession::studentAttendancePercent($studentId);

        $homeworkStmt = $pdo->prepare(
            'SELECT h.id,
                    h.title,
                    h.due_date,
                    EXISTS(
                        SELECT 1 FROM homework_submissions s
                        WHERE s.homework_id = h.id AND s.student_id = :sid_sub
                    ) AS is_submitted
             FROM homeworks h
             INNER JOIN homework_assigned_students hass ON hass.homework_id = h.id AND hass.student_id = :sid_assign
             ORDER BY h.due_date IS NULL, h.due_date ASC, h.created_at DESC
             LIMIT 8'
        );
        $homeworkStmt->execute(['sid_sub' => $studentId, 'sid_assign' => $studentId]);
        $homeworkItems = $homeworkStmt->fetchAll() ?: [];
        $homeworkPending = 0;
        $homeworkSubmitted = 0;
        foreach ($homeworkItems as $hw) {
            if (!empty($hw['is_submitted'])) {
                $homeworkSubmitted++;
            } else {
                $homeworkPending++;
            }
        }

        $fbStmt = $pdo->prepare('SELECT COUNT(*) FROM feedback WHERE student_id = :sid');
        $fbStmt->execute(['sid' => $studentId]);
        $feedbackCount = (int) ($fbStmt->fetchColumn() ?: 0);

        $reportStmt = $pdo->prepare('SELECT COUNT(*) FROM student_reports WHERE student_id = :sid');
        $reportStmt->execute(['sid' => $studentId]);
        $reportCount = (int) ($reportStmt->fetchColumn() ?: 0);

        $announcements = self::announcements();

        $bannerPng = dirname(__DIR__, 2) . '/public/assets/images/banner.png';
        $bannerJpg = dirname(__DIR__, 2) . '/public/assets/images/banner.jpg';
        $hasBanner = is_file($bannerPng) || is_file($bannerJpg);
        $bannerSrc = is_file($bannerPng)
            ? url('assets/images/banner.png')
            : (is_file($bannerJpg) ? url('assets/images/banner.jpg') : '');

        View::render('student/dashboard', [
            'pageTitle' => 'Student Dashboard',
            'studentName' => (string) ($user['name'] ?? 'Student'),
            'studentTimezone' => resolveUserTimezone($user, APP_TIMEZONE),
            'assignedTeachers' => $assignedTeachers,
            'hasBanner' => $hasBanner,
            'bannerSrc' => $bannerSrc,
            'upcomingCount' => $upcomingCount,
            'nextClass' => $nextClass,
            'upcomingClasses' => $upcomingClasses,
            'completedClasses' => $completed,
            'recordings' => $recordings,
            'homeworkItems' => $homeworkItems,
            'homeworkPending' => $homeworkPending,
            'homeworkSubmitted' => $homeworkSubmitted,
            'feedbackCount' => $feedbackCount,
            'reportCount' => $reportCount,
            'attendancePercent' => $attendancePercent,
            'announcements' => $announcements,
        ]);
    }

    /**
     * @return list<array{title: string, body: string, date: string}>
     */
    private static function announcements(): array
    {
        $brand = defined('APP_NAME') && APP_NAME !== 'LMS' ? APP_NAME : 'LearnWise';

        return [
            [
                'title' => 'Welcome to ' . $brand,
                'body' => 'Your dashboard brings classes, homework, recordings, and progress together in one place.',
                'date' => date('M j, Y'),
            ],
            [
                'title' => 'Join classes on time',
                'body' => 'Use the Join Class button when your session starts. Recordings appear after teacher approval.',
                'date' => date('M j, Y', strtotime('-2 days')),
            ],
            [
                'title' => 'Stay on top of homework',
                'body' => 'Check pending assignments regularly and upload submissions before the due date.',
                'date' => date('M j, Y', strtotime('-5 days')),
            ],
        ];
    }
}

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/models/ClassSession.php';

class FeedbackController
{
    /** Teachers can send feedback anytime for students they have enrolled in at least one class. */
    public static function teacherIndex(): void
    {
        Auth::requireRole(['teacher']);
        $teacherId = (int) (Auth::user()['id'] ?? 0);
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT e.student_id, MAX(u.name) AS student_name,
                    SUM(CASE WHEN cs.status = "completed" THEN 1 ELSE 0 END) AS completed_count
             FROM enrollments e
             INNER JOIN class_sessions cs ON cs.id = e.class_id
             INNER JOIN users u ON u.id = e.student_id
             WHERE cs.teacher_id = :tid
             GROUP BY e.student_id
             ORDER BY MAX(u.name) ASC'
        );
        $stmt->execute(['tid' => $teacherId]);
        $eligible = $stmt->fetchAll() ?: [];

        View::render('feedback/teacher_index', [
            'pageTitle' => 'Student Feedback',
            'eligible' => $eligible,
        ]);
    }

    public static function teacherCreateForm(): void
    {
        Auth::requireRole(['teacher']);
        $teacherId = (int) (Auth::user()['id'] ?? 0);
        $studentId = (int) ($_GET['student_id'] ?? 0);
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        if ($studentId <= 0) {
            header('Location: ' . $base . '/teacher/feedback');
            return;
        }

        if (!self::studentEnrolledWithTeacher($studentId, $teacherId)) {
            $_SESSION['flash_warning'] = 'Pick a student from your roster.';
            header('Location: ' . $base . '/teacher/feedback');
            return;
        }

        $count = ClassSession::countCompletedWithTeacher($studentId, $teacherId);

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT name FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $studentId]);
        $stu = $stmt->fetch();

        View::render('feedback/teacher_create', [
            'pageTitle' => 'Give Feedback',
            'studentId' => $studentId,
            'studentName' => $stu['name'] ?? 'Student',
            'completedCount' => $count,
        ]);
    }

    public static function teacherStore(): void
    {
        Auth::requireRole(['teacher']);
        $teacherId = (int) (Auth::user()['id'] ?? 0);
        $base = defined('BASE_PATH') ? BASE_PATH : '';

        $studentId = (int) ($_POST['student_id'] ?? 0);
        $comments = trim($_POST['comments'] ?? '');
        $rating = (int) ($_POST['rating'] ?? 5);

        if ($studentId <= 0 || $comments === '' || $rating < 1 || $rating > 5) {
            header('Location: ' . $base . '/teacher/feedback');
            return;
        }

        if (!self::studentEnrolledWithTeacher($studentId, $teacherId)) {
            $_SESSION['flash_warning'] = 'That student is not on your roster.';
            header('Location: ' . $base . '/teacher/feedback');
            return;
        }

        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO feedback (student_id, teacher_id, comments, rating)
             VALUES (:sid, :tid, :c, :r)
             ON DUPLICATE KEY UPDATE comments = VALUES(comments), rating = VALUES(rating), created_at = NOW()'
        )->execute([
            'sid' => $studentId,
            'tid' => $teacherId,
            'c' => $comments,
            'r' => $rating,
        ]);

        $_SESSION['flash_success'] = 'Feedback saved.';
        header('Location: ' . $base . '/teacher/feedback');
    }

    private static function studentEnrolledWithTeacher(int $studentId, int $teacherId): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT 1 FROM enrollments e
             INNER JOIN class_sessions cs ON cs.id = e.class_id
             WHERE cs.teacher_id = :tid AND e.student_id = :sid
             LIMIT 1'
        );
        $stmt->execute(['tid' => $teacherId, 'sid' => $studentId]);

        return (bool) $stmt->fetchColumn();
    }
}

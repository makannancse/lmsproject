<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/models/ClassSession.php';
require_once dirname(__DIR__) . '/models/TeacherStudent.php';

class FeedbackController
{
    /** Teachers can send feedback for students mapped to them in teacher_students. */
    public static function teacherIndex(): void
    {
        Auth::requireRole(['teacher']);
        $teacherId = (int) (Auth::user()['id'] ?? 0);
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT ts.student_id,
                    u.name AS student_name,
                    COALESCE(SUM(CASE WHEN cs.status = "completed" THEN 1 ELSE 0 END), 0) AS completed_count
             FROM teacher_students ts
             INNER JOIN users u ON u.id = ts.student_id
             LEFT JOIN enrollments e ON e.student_id = ts.student_id
             LEFT JOIN class_sessions cs ON cs.id = e.class_id AND cs.teacher_id = ts.teacher_id
             WHERE ts.teacher_id = :tid
             GROUP BY ts.student_id, u.name
             ORDER BY u.name ASC'
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
        $base = appWebPath();
        if ($studentId <= 0) {
            redirectTo('/teacher/feedback');
            return;
        }

        if (!TeacherStudent::isMapped($teacherId, $studentId)) {
            $_SESSION['flash_warning'] = 'Pick a student from your mapped roster.';
            redirectTo('/teacher/feedback');
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
        $base = appWebPath();

        $studentId = (int) ($_POST['student_id'] ?? 0);
        $comments = trim($_POST['comments'] ?? '');
        $rating = (int) ($_POST['rating'] ?? 5);

        if ($studentId <= 0 || $comments === '' || $rating < 1 || $rating > 5) {
            redirectTo('/teacher/feedback');
            return;
        }

        if (!TeacherStudent::isMapped($teacherId, $studentId)) {
            $_SESSION['flash_warning'] = 'That student is not mapped to you.';
            redirectTo('/teacher/feedback');
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
        redirectTo('/teacher/feedback');
    }

    public static function studentIndex(): void
    {
        Auth::requireRole(['student']);
        $studentId = (int) (Auth::user()['id'] ?? 0);
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT f.*, u.name AS teacher_name, s.subject
             FROM feedback f
             INNER JOIN users u ON u.id = f.teacher_id
             LEFT JOIN students s ON s.user_id = f.student_id
             WHERE f.student_id = :sid
             ORDER BY f.created_at DESC'
        );
        $stmt->execute(['sid' => $studentId]);
        $items = $stmt->fetchAll() ?: [];

        View::render('feedback/student_index', [
            'pageTitle' => 'My Feedback',
            'items' => $items,
        ]);
    }

    public static function adminIndex(): void
    {
        Auth::requireRole(['admin']);
        $pdo = Database::connection();
        $stmt = $pdo->query(
            'SELECT f.*,
                    su.name AS student_name,
                    tu.name AS teacher_name,
                    st.subject
             FROM feedback f
             INNER JOIN users su ON su.id = f.student_id
             INNER JOIN users tu ON tu.id = f.teacher_id
             LEFT JOIN students st ON st.user_id = f.student_id
             ORDER BY f.created_at DESC, f.id DESC'
        );
        $items = $stmt->fetchAll() ?: [];

        View::render('feedback/admin_index', [
            'pageTitle' => 'Student Feedback',
            'items' => $items,
        ]);
    }
}

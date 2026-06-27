<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/lib/Database.php';

/**
 * Admin-only: maintain teacher_students rows so teachers can assign homework,
 * create reports, etc., only for linked students.
 */
class TeacherStudentMapController
{
    public static function form(): void
    {
        Auth::requireRole(['admin']);
        $pdo = Database::connection();

        $teachers = $pdo->query(
            "SELECT id, name, email FROM users WHERE role = 'teacher' AND status = 'active' ORDER BY name"
        )->fetchAll() ?: [];
        $students = $pdo->query(
            "SELECT id, name, email FROM users WHERE role = 'student' AND status = 'active' ORDER BY name"
        )->fetchAll() ?: [];

        $teacherId = (int) ($_GET['teacher_id'] ?? 0);
        $teacherIds = array_map(static fn(array $t): int => (int) ($t['id'] ?? 0), $teachers);
        if ($teacherId > 0 && !in_array($teacherId, $teacherIds, true)) {
            $teacherId = 0;
        }
        if ($teacherId <= 0 && $teachers !== []) {
            $teacherId = (int) ($teachers[0]['id'] ?? 0);
        }

        $assignedIds = [];
        if ($teacherId > 0) {
            $stmt = $pdo->prepare('SELECT student_id FROM teacher_students WHERE teacher_id = :tid');
            $stmt->execute(['tid' => $teacherId]);
            foreach ($stmt->fetchAll() ?: [] as $row) {
                $assignedIds[(int) ($row['student_id'] ?? 0)] = true;
            }
        }

        View::render('admin/teacher_student_mapping', [
            'pageTitle' => 'Teacher–Student mapping',
            'teachers' => $teachers,
            'students' => $students,
            'teacherId' => $teacherId,
            'assignedIds' => $assignedIds,
            'errors' => [],
        ]);
    }

    public static function store(): void
    {
        Auth::requireRole(['admin']);
        $base = appWebPath();
        $pdo = Database::connection();

        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        $studentIds = array_values(array_unique(array_filter(array_map('intval', $_POST['student_ids'] ?? []))));

        $errors = [];
        if ($teacherId <= 0) {
            $errors[] = 'Please select a teacher.';
        }
        if ($studentIds === []) {
            $errors[] = 'Select at least one student.';
        }

        $chk = $pdo->prepare('SELECT id FROM users WHERE id = :id AND role = "teacher" LIMIT 1');
        $chk->execute(['id' => $teacherId]);
        if ($teacherId > 0 && !$chk->fetch()) {
            $errors[] = 'Invalid teacher selected.';
        }

        $validStudent = $pdo->prepare('SELECT id FROM users WHERE id = :id AND role = "student" LIMIT 1');
        foreach ($studentIds as $sid) {
            $validStudent->execute(['id' => $sid]);
            if (!$validStudent->fetch()) {
                $errors[] = 'One or more student ids are invalid.';
                break;
            }
        }

        if (!empty($errors)) {
            $teachers = $pdo->query("SELECT id, name, email FROM users WHERE role = 'teacher' ORDER BY name")->fetchAll() ?: [];
            $students = $pdo->query("SELECT id, name, email FROM users WHERE role = 'student' ORDER BY name")->fetchAll() ?: [];
            $assignedIds = [];
            foreach ($studentIds as $sid) {
                $assignedIds[$sid] = true;
            }
            View::render('admin/teacher_student_mapping', [
                'pageTitle' => 'Teacher–Student mapping',
                'teachers' => $teachers,
                'students' => $students,
                'teacherId' => $teacherId,
                'assignedIds' => $assignedIds,
                'errors' => $errors,
            ]);
            return;
        }

        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare('DELETE FROM teacher_students WHERE teacher_id = :tid');
            $del->execute(['tid' => $teacherId]);

            $ins = $pdo->prepare('INSERT INTO teacher_students (teacher_id, student_id) VALUES (:tid, :sid)');
            foreach ($studentIds as $sid) {
                $ins->execute(['tid' => $teacherId, 'sid' => $sid]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $_SESSION['flash_warning'] = 'Could not save mapping: ' . $e->getMessage();
            redirectTo('/admin/teacher-students?teacher_id=' . $teacherId);
            return;
        }

        require_once dirname(__DIR__) . '/lib/NotificationMailer.php';
        $teacherRow = $pdo->prepare('SELECT name, email FROM users WHERE id = :id LIMIT 1');
        $teacherRow->execute(['id' => $teacherId]);
        $teacher = $teacherRow->fetch() ?: [];
        $studentStmt = $pdo->prepare(
            'SELECT u.name, s.subject FROM users u LEFT JOIN students s ON s.user_id = u.id WHERE u.id = :id LIMIT 1'
        );
        $assignedDate = date('Y-m-d');
        foreach ($studentIds as $sid) {
            $studentStmt->execute(['id' => $sid]);
            $student = $studentStmt->fetch() ?: [];
            NotificationMailer::notifyTeacherStudentAssigned(
                (string) ($teacher['email'] ?? ''),
                (string) ($teacher['name'] ?? 'Teacher'),
                (string) ($student['name'] ?? 'Student'),
                (string) ($student['subject'] ?? ''),
                $assignedDate
            );
        }

        $_SESSION['flash_success'] = 'Teacher–student mapping saved.';
        redirectTo('/admin/teacher-students?teacher_id=' . $teacherId);
    }
}

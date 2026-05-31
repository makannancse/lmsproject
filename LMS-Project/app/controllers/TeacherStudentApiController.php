<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/models/TeacherStudent.php';
require_once dirname(__DIR__) . '/models/User.php';

class TeacherStudentApiController
{
    /** GET /api/teacher-students?teacher_id= — mapped students for scheduling UI. */
    public static function listForTeacher(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        Auth::startSession();

        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
            return;
        }

        $user = Auth::user();
        $role = (string) ($user['role'] ?? '');
        if (!in_array($role, ['admin', 'teacher'], true)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Forbidden']);
            return;
        }

        $teacherId = (int) ($_GET['teacher_id'] ?? 0);
        if ($role === 'teacher') {
            $teacherId = (int) ($user['id'] ?? 0);
        }

        if ($teacherId <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'teacher_id is required']);
            return;
        }

        $teacher = null;
        foreach (User::allTeachers() as $row) {
            if ((int) ($row['id'] ?? 0) === $teacherId) {
                $teacher = $row;
                break;
            }
        }
        if ($teacher === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Teacher not found']);
            return;
        }

        $students = TeacherStudent::studentsForTeacher($teacherId);
        $payload = [];
        foreach ($students as $student) {
            $payload[] = [
                'id' => (int) ($student['id'] ?? 0),
                'name' => (string) ($student['name'] ?? ''),
                'email' => (string) ($student['email'] ?? ''),
                'label' => trim((string) ($student['name'] ?? '') . ' (' . (string) ($student['email'] ?? '') . ')'),
            ];
        }

        echo json_encode([
            'ok' => true,
            'teacher_id' => $teacherId,
            'count' => count($payload),
            'students' => $payload,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/Mailer.php';

class HomeworkController
{
    private const UPLOAD_MAX_BYTES = 5 * 1024 * 1024;
    private const ALLOWED_EXT = ['pdf', 'doc', 'docx', 'txt', 'png', 'jpg', 'jpeg'];
    private const HOMEWORK_UPLOAD_SUBDIR = 'uploads/homework';
    private const SUBMISSION_UPLOAD_SUBDIR = 'uploads/homework_submissions';

    public static function teacherIndex(): void
    {
        Auth::requireRole(['teacher', 'admin']);
        $user = Auth::user();
        $pdo = Database::connection();

        if (($user['role'] ?? '') === 'admin') {
            $stmt = $pdo->query(
                'SELECT h.*, u.name AS teacher_name, u.timezone AS teacher_timezone,
                        (SELECT COUNT(*) FROM homework_assigned_students hs WHERE hs.homework_id = h.id) AS assigned_count,
                        (SELECT COUNT(*) FROM homework_submissions s WHERE s.homework_id = h.id) AS submitted_count
                 FROM homeworks h
                 INNER JOIN users u ON u.id = h.teacher_id
                 ORDER BY h.created_at DESC'
            );
            $homeworks = $stmt->fetchAll() ?: [];
        } else {
            $stmt = $pdo->prepare(
                'SELECT h.*, u.name AS teacher_name, u.timezone AS teacher_timezone,
                        (SELECT COUNT(*) FROM homework_assigned_students hs WHERE hs.homework_id = h.id) AS assigned_count,
                        (SELECT COUNT(*) FROM homework_submissions s WHERE s.homework_id = h.id) AS submitted_count
                 FROM homeworks h
                 INNER JOIN users u ON u.id = h.teacher_id
                 WHERE h.teacher_id = :tid
                 ORDER BY h.created_at DESC'
            );
            $stmt->execute(['tid' => (int) ($user['id'] ?? 0)]);
            $homeworks = $stmt->fetchAll() ?: [];
        }

        View::render('homework/teacher_index_modern', [
            'pageTitle' => 'Homework',
            'homeworks' => $homeworks,
            'attachmentsByHomework' => self::fetchAttachmentsByHomework($pdo, array_map(static fn(array $r): int => (int) $r['id'], $homeworks)),
            'isAdmin' => (($user['role'] ?? '') === 'admin'),
        ]);
    }

    public static function teacherCreateForm(): void
    {
        Auth::requireRole(['teacher', 'admin']);
        $user = Auth::user();
        $teachers = self::fetchTeachersForAdmin($user);
        $studentListTeacherId = 0;
        $students = [];
        if (($user['role'] ?? '') === 'admin') {
            // Admins must pick students that belong to the homework teacher via teacher_students (same rule as store()).
            $studentListTeacherId = (int) ($_GET['teacher_id'] ?? 0);
            if ($studentListTeacherId <= 0 && $teachers !== []) {
                $studentListTeacherId = (int) ($teachers[0]['id'] ?? 0);
            }
            if ($studentListTeacherId > 0) {
                $students = self::fetchAssignableStudentsForTeacher($studentListTeacherId, $user);
            }
        } else {
            $students = self::fetchAssignableStudentsForTeacher((int) ($user['id'] ?? 0), $user);
        }

        View::render('homework/teacher_create_modern', [
            'pageTitle' => 'Assign Homework',
            'students' => $students,
            'teachers' => $teachers,
            'studentListTeacherId' => $studentListTeacherId,
            'errors' => [],
            'old' => [],
            'isAdmin' => (($user['role'] ?? '') === 'admin'),
        ]);
    }

    public static function teacherStore(): void
    {
        Auth::requireRole(['teacher', 'admin']);
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        $user = Auth::user();
        $pdo = Database::connection();

        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $dueRaw = trim((string) ($_POST['due_date'] ?? ''));
        $selectedTeacherId = (int) ($_POST['teacher_id'] ?? 0);
        $studentIds = array_values(array_unique(array_filter(array_map('intval', $_POST['student_ids'] ?? []))));
        $isAdminUser = (($user['role'] ?? '') === 'admin');
        $teacherId = $isAdminUser ? $selectedTeacherId : (int) ($user['id'] ?? 0);
        if (!$isAdminUser && $teacherId <= 0) {
            $teacherId = (int) ($user['id'] ?? 0);
        }

        $errors = [];
        if ($isAdminUser && $teacherId <= 0) {
            $errors[] = 'Please select a teacher.';
        }
        if ($title === '') {
            $errors[] = 'Title is required.';
        }
        if (empty($studentIds)) {
            $errors[] = 'Select at least one student.';
        }

        $dueTimezone = normalizeTimezone(
            (string) ($_POST['due_timezone'] ?? ($user['timezone'] ?? APP_TIMEZONE)),
            APP_TIMEZONE
        );
        $dueDate = null;
        if ($dueRaw !== '') {
            try {
                $dueDate = (new DateTime($dueRaw, new DateTimeZone($dueTimezone)))
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                $errors[] = 'Invalid due date.';
            }
        }

        $allowedStudents = self::fetchAssignableStudentsForTeacher($teacherId, $user);
        $allowedMap = [];
        foreach ($allowedStudents as $st) {
            $allowedMap[(int) $st['id']] = true;
        }
        foreach ($studentIds as $sid) {
            if (!isset($allowedMap[$sid])) {
                $errors[] = 'One or more selected students are not linked to this teacher in teacher_students. Refresh the page, choose the correct teacher, then pick only students from the list.';
                break;
            }
        }

        $attachmentsCheck = self::validateUploadFiles($_FILES['attachments'] ?? null, 'attachments');
        if (!empty($attachmentsCheck['errors'])) {
            $errors = array_merge($errors, $attachmentsCheck['errors']);
        }

        if (!empty($errors)) {
            $teachers = self::fetchTeachersForAdmin($user);
            $retryStudents = [];
            $retryListTeacherId = 0;
            if (($user['role'] ?? '') === 'admin') {
                $retryListTeacherId = $teacherId > 0 ? $teacherId : (int) ($_POST['teacher_id'] ?? 0);
                if ($retryListTeacherId > 0) {
                    $retryStudents = self::fetchAssignableStudentsForTeacher($retryListTeacherId, $user);
                }
            } else {
                $retryStudents = self::fetchAssignableStudentsForTeacher((int) ($user['id'] ?? 0), $user);
            }
            View::render('homework/teacher_create_modern', [
                'pageTitle' => 'Assign Homework',
                'students' => $retryStudents,
                'teachers' => $teachers,
                'studentListTeacherId' => $retryListTeacherId,
                'errors' => $errors,
                'old' => $_POST,
                'isAdmin' => (($user['role'] ?? '') === 'admin'),
            ]);
            return;
        }

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO homeworks (title, description, teacher_id, due_date, due_timezone, created_by, status)
                 VALUES (:title, :desc, :tid, :due, :due_timezone, :uid, "pending")'
            );
            $ins->execute([
                'title' => $title,
                'desc' => $description !== '' ? $description : null,
                'due' => $dueDate,
                'due_timezone' => $dueTimezone,
                'tid' => $teacherId,
                'uid' => (int) $user['id'],
            ]);
            $homeworkId = (int) $pdo->lastInsertId();

            self::syncAssignedStudents($pdo, $homeworkId, $studentIds);
            self::storeHomeworkAttachments($pdo, $homeworkId, $_FILES['attachments'] ?? null);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $_SESSION['flash_warning'] = 'Could not assign homework: ' . $e->getMessage();
            header('Location: ' . $base . '/teacher/homework/create');
            return;
        }

        self::notifyAssignedStudents($pdo, $homeworkId, $title, $description, $dueDate, $dueTimezone);

        $_SESSION['flash_success'] = 'Homework assigned successfully.';
        header('Location: ' . $base . '/teacher/homework');
    }

    public static function teacherViewClass(): void
    {
        Auth::requireRole(['teacher', 'admin']);
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        $user = Auth::user();
        $homeworkId = (int) ($_GET['homework_id'] ?? 0);
        $pdo = Database::connection();

        $homework = self::fetchHomeworkForManager($homeworkId, $user);
        if (!$homework) {
            $_SESSION['flash_warning'] = 'Homework not found or not allowed.';
            header('Location: ' . $base . '/teacher/homework');
            return;
        }

        View::render('homework/teacher_class_modern', [
            'pageTitle' => 'Homework Details',
            'homework' => $homework,
            'attachments' => self::fetchHomeworkAttachments($pdo, $homeworkId),
            'assignedStudents' => self::fetchAssignedStudents($pdo, $homeworkId),
        ]);
    }

    public static function teacherEditForm(): void
    {
        Auth::requireRole(['teacher', 'admin']);
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        $user = Auth::user();
        $homeworkId = (int) ($_GET['homework_id'] ?? 0);
        $pdo = Database::connection();

        $homework = self::fetchHomeworkForManager($homeworkId, $user);
        if (!$homework) {
            $_SESSION['flash_warning'] = 'Homework not found or not allowed.';
            header('Location: ' . $base . '/teacher/homework');
            return;
        }

        $students = self::fetchAssignableStudentsForTeacher((int) $homework['teacher_id'], $user);
        $assigned = self::fetchAssignedStudentIds($pdo, $homeworkId);
        $attachments = self::fetchHomeworkAttachments($pdo, $homeworkId);

        View::render('homework/teacher_edit_modern', [
            'pageTitle' => 'Edit Homework',
            'homework' => $homework,
            'students' => $students,
            'assignedStudentIds' => $assigned,
            'attachments' => $attachments,
            'errors' => [],
        ]);
    }

    public static function teacherUpdate(): void
    {
        Auth::requireRole(['teacher', 'admin']);
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        $user = Auth::user();
        $pdo = Database::connection();

        $homeworkId = (int) ($_POST['homework_id'] ?? 0);
        $homework = self::fetchHomeworkForManager($homeworkId, $user);
        if (!$homework) {
            $_SESSION['flash_warning'] = 'Homework not found or not allowed.';
            header('Location: ' . $base . '/teacher/homework');
            return;
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $dueRaw = trim((string) ($_POST['due_date'] ?? ''));
        $studentIds = array_values(array_unique(array_filter(array_map('intval', $_POST['student_ids'] ?? []))));
        $removeAttachmentIds = array_values(array_unique(array_filter(array_map('intval', $_POST['remove_attachment_ids'] ?? []))));

        $errors = [];
        if ($title === '') {
            $errors[] = 'Title is required.';
        }
        if (empty($studentIds)) {
            $errors[] = 'Select at least one student.';
        }

        $dueTimezone = normalizeTimezone(
            (string) ($_POST['due_timezone'] ?? ($homework['due_timezone'] ?? $homework['teacher_timezone'] ?? APP_TIMEZONE)),
            APP_TIMEZONE
        );
        $dueDate = null;
        if ($dueRaw !== '') {
            try {
                $dueDate = (new DateTime($dueRaw, new DateTimeZone($dueTimezone)))
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                $errors[] = 'Invalid due date.';
            }
        }

        $students = self::fetchAssignableStudentsForTeacher((int) $homework['teacher_id'], $user);
        $allowedMap = [];
        foreach ($students as $st) {
            $allowedMap[(int) $st['id']] = true;
        }
        foreach ($studentIds as $sid) {
            if (!isset($allowedMap[$sid])) {
                $errors[] = 'Selected students include unauthorized entries.';
                break;
            }
        }

        $attachmentsCheck = self::validateUploadFiles($_FILES['attachments'] ?? null, 'attachments');
        if (!empty($attachmentsCheck['errors'])) {
            $errors = array_merge($errors, $attachmentsCheck['errors']);
        }

        if (!empty($errors)) {
            $homework['title'] = $title;
            $homework['description'] = $description;
            $homework['due_date'] = $dueDate;
            $homework['due_timezone'] = $dueTimezone;
            View::render('homework/teacher_edit_modern', [
                'pageTitle' => 'Edit Homework',
                'homework' => $homework,
                'students' => $students,
                'assignedStudentIds' => $studentIds,
                'attachments' => self::fetchHomeworkAttachments($pdo, $homeworkId),
                'errors' => $errors,
            ]);
            return;
        }

        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare(
                'UPDATE homeworks
                 SET title = :title, description = :desc, due_date = :due, due_timezone = :due_timezone
                 WHERE id = :id'
            );
            $upd->execute([
                'title' => $title,
                'desc' => $description !== '' ? $description : null,
                'due' => $dueDate,
                'due_timezone' => $dueTimezone,
                'id' => $homeworkId,
            ]);

            self::syncAssignedStudents($pdo, $homeworkId, $studentIds);
            self::removeAttachmentRows($pdo, $homeworkId, $removeAttachmentIds);
            self::storeHomeworkAttachments($pdo, $homeworkId, $_FILES['attachments'] ?? null);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $_SESSION['flash_warning'] = 'Update failed: ' . $e->getMessage();
            header('Location: ' . $base . '/teacher/homework/edit?homework_id=' . $homeworkId);
            return;
        }

        $_SESSION['flash_success'] = 'Homework updated.';
        header('Location: ' . $base . '/teacher/homework');
    }

    public static function teacherDelete(): void
    {
        Auth::requireRole(['teacher', 'admin']);
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        $user = Auth::user();
        $homeworkId = (int) ($_POST['homework_id'] ?? 0);
        $pdo = Database::connection();

        $homework = self::fetchHomeworkForManager($homeworkId, $user);
        if (!$homework) {
            $_SESSION['flash_warning'] = 'Homework not found or not allowed.';
            header('Location: ' . $base . '/teacher/homework');
            return;
        }

        $pdo->beginTransaction();
        try {
            self::removeAllAttachmentRows($pdo, $homeworkId);

            $pdo->prepare('DELETE FROM homework_assigned_students WHERE homework_id = :hid')
                ->execute(['hid' => $homeworkId]);
            $pdo->prepare('DELETE FROM homework_submissions WHERE homework_id = :hid')
                ->execute(['hid' => $homeworkId]);
            $pdo->prepare('DELETE FROM homeworks WHERE id = :hid LIMIT 1')
                ->execute(['hid' => $homeworkId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $_SESSION['flash_warning'] = 'Delete failed: ' . $e->getMessage();
            header('Location: ' . $base . '/teacher/homework');
            return;
        }

        $_SESSION['flash_success'] = 'Homework deleted.';
        header('Location: ' . $base . '/teacher/homework');
    }

    public static function teacherSubmissions(): void
    {
        Auth::requireRole(['teacher', 'admin']);
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        $user = Auth::user();
        $homeworkId = (int) ($_GET['homework_id'] ?? 0);
        $pdo = Database::connection();

        $homework = self::fetchHomeworkForManager($homeworkId, $user);
        if (!$homework) {
            $_SESSION['flash_warning'] = 'Homework not found or not allowed.';
            header('Location: ' . $base . '/teacher/homework');
            return;
        }

        if (($user['role'] ?? '') === 'teacher') {
            $stmt = $pdo->prepare(
                'SELECT s.*, u.name AS student_name, u.email AS student_email
                 FROM homework_submissions s
                 INNER JOIN users u ON u.id = s.student_id
                 INNER JOIN teacher_students ts ON ts.student_id = s.student_id AND ts.teacher_id = :tid
                 WHERE s.homework_id = :hid
                 ORDER BY s.submitted_at DESC'
            );
            $stmt->execute([
                'hid' => $homeworkId,
                'tid' => (int) $user['id'],
            ]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT s.*, u.name AS student_name, u.email AS student_email
                 FROM homework_submissions s
                 INNER JOIN users u ON u.id = s.student_id
                 WHERE s.homework_id = :hid
                 ORDER BY s.submitted_at DESC'
            );
            $stmt->execute(['hid' => $homeworkId]);
        }
        $submissions = $stmt->fetchAll() ?: [];

        View::render('homework/teacher_submissions', [
            'pageTitle' => 'Homework Submissions',
            'homework' => $homework,
            'submissions' => $submissions,
        ]);
    }

    public static function markCompleted(): void
    {
        Auth::requireRole(['teacher', 'admin']);
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        $user = Auth::user();
        $homeworkId = (int) ($_POST['homework_id'] ?? 0);
        $homework = self::fetchHomeworkForManager($homeworkId, $user);
        if (!$homework) {
            $_SESSION['flash_warning'] = 'Homework not found or not allowed.';
            header('Location: ' . $base . '/teacher/homework');
            return;
        }
        $pdo = Database::connection();
        $pdo->prepare('UPDATE homeworks SET status = "completed", completed_at = UTC_TIMESTAMP() WHERE id = :id')
            ->execute(['id' => $homeworkId]);
        $_SESSION['flash_success'] = 'Homework marked as completed.';
        header('Location: ' . $base . '/teacher/homework');
    }

    public static function studentIndex(): void
    {
        Auth::requireRole(['student']);
        $studentId = (int) (Auth::user()['id'] ?? 0);
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT h.*, t.name AS teacher_name, t.timezone AS teacher_timezone
             FROM homeworks h
             INNER JOIN homework_assigned_students hass ON hass.homework_id = h.id AND hass.student_id = :sid
             INNER JOIN users t ON t.id = h.teacher_id
             ORDER BY h.due_date IS NULL, h.due_date ASC, h.created_at DESC'
        );
        $stmt->execute(['sid' => $studentId]);
        $items = $stmt->fetchAll() ?: [];
        $homeworkIds = array_map(static fn(array $r): int => (int) $r['id'], $items);

        $attachmentsByHomework = self::fetchAttachmentsByHomework($pdo, $homeworkIds);
        $submissionsByHomework = self::fetchStudentSubmissionRows($pdo, $studentId, $homeworkIds);

        View::render('homework/student_index_modern', [
            'pageTitle' => 'My Homework',
            'items' => $items,
            'attachmentsByHomework' => $attachmentsByHomework,
            'submissionsByHomework' => $submissionsByHomework,
        ]);
    }

    public static function studentUpload(): void
    {
        Auth::requireRole(['student']);
        $studentId = (int) (Auth::user()['id'] ?? 0);
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        $pdo = Database::connection();

        $homeworkId = (int) ($_POST['homework_id'] ?? 0);
        if ($homeworkId <= 0) {
            $_SESSION['flash_warning'] = 'Invalid homework selection.';
            header('Location: ' . $base . '/student/homework');
            return;
        }

        $hwStmt = $pdo->prepare(
            'SELECT h.* FROM homeworks h
             INNER JOIN homework_assigned_students hass ON hass.homework_id = h.id
             WHERE h.id = :hid AND hass.student_id = :sid
             LIMIT 1'
        );
        $hwStmt->execute(['hid' => $homeworkId, 'sid' => $studentId]);
        $homework = $hwStmt->fetch();
        if (!$homework) {
            $_SESSION['flash_warning'] = 'You are not assigned to this homework.';
            header('Location: ' . $base . '/student/homework');
            return;
        }

        $check = self::validateUploadFiles($_FILES['files'] ?? null, 'submission files');
        if (!empty($check['errors'])) {
            $_SESSION['flash_warning'] = implode(' ', $check['errors']);
            header('Location: ' . $base . '/student/homework');
            return;
        }
        if (empty($check['items'])) {
            $_SESSION['flash_warning'] = 'Please choose at least one file.';
            header('Location: ' . $base . '/student/homework');
            return;
        }

        try {
            foreach ($check['items'] as $meta) {
                $saved = self::moveValidatedFile($meta, self::SUBMISSION_UPLOAD_SUBDIR);
                $ins = $pdo->prepare(
                    'INSERT INTO homework_submissions (homework_id, student_id, file_name, file_path, submitted_at)
                     VALUES (:hid, :sid, :fn, :fp, UTC_TIMESTAMP())'
                );
                $ins->execute([
                    'hid' => $homeworkId,
                    'sid' => $studentId,
                    'fn' => $saved['original_name'],
                    'fp' => $saved['relative_path'],
                ]);
            }
        } catch (\Throwable $e) {
            $_SESSION['flash_warning'] = 'Upload failed: ' . $e->getMessage();
            header('Location: ' . $base . '/student/homework');
            return;
        }

        $_SESSION['flash_success'] = 'Submission uploaded successfully.';
        header('Location: ' . $base . '/student/homework');
    }

    /** Secure file download gateway for attachments/submissions by role. */
    public static function download(): void
    {
        Auth::requireRole(['admin', 'teacher', 'student']);
        $user = Auth::user();
        $kind = (string) ($_GET['kind'] ?? '');
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0 || !in_array($kind, ['attachment', 'submission'], true)) {
            http_response_code(400);
            echo 'Invalid download request.';
            return;
        }

        $pdo = Database::connection();
        $row = null;
        if ($kind === 'attachment') {
            $stmt = $pdo->prepare(
                'SELECT a.*, h.id AS homework_id, h.teacher_id
                 FROM homework_attachments a
                 INNER JOIN homeworks h ON h.id = a.homework_id
                 WHERE a.id = :id LIMIT 1'
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch() ?: null;
            if ($row && !self::canAccessHomework($row, $user, $pdo)) {
                $row = null;
            }
            $downloadName = (string) ($row['file_name'] ?? '');
            $filePath = (string) ($row['file_path'] ?? '');
        } else {
            $stmt = $pdo->prepare(
                'SELECT s.*, h.teacher_id
                 FROM homework_submissions s
                 INNER JOIN homeworks h ON h.id = s.homework_id
                 WHERE s.id = :id LIMIT 1'
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch() ?: null;
            if ($row && !self::canAccessSubmissionRow($row, $user, $pdo)) {
                $row = null;
            }
            $downloadName = (string) ($row['file_name'] ?? 'submission');
            $filePath = (string) ($row['file_path'] ?? '');
        }

        if (!$row) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        $abs = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($filePath, '/\\'));
        if (!is_file($abs)) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurlencode($downloadName) . '"');
        header('Content-Length: ' . (string) filesize($abs));
        readfile($abs);
    }

    private static function fetchTeachersForAdmin(?array $user): array
    {
        if (($user['role'] ?? '') !== 'admin') {
            return [];
        }
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT id, name, email FROM users WHERE role = "teacher" ORDER BY name');
        return $stmt->fetchAll() ?: [];
    }

    private static function fetchAssignableStudentsForTeacher(int $teacherId, ?array $viewer): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email
             FROM users u
             INNER JOIN teacher_students ts ON ts.student_id = u.id
             WHERE ts.teacher_id = :tid
             ORDER BY u.name'
        );
        $stmt->execute([
            'tid' => $teacherId,
        ]);
        return $stmt->fetchAll() ?: [];
    }

    private static function fetchHomeworkForManager(int $homeworkId, ?array $user): ?array
    {
        if ($homeworkId <= 0 || !$user) {
            return null;
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT h.*, u.name AS teacher_name, u.timezone AS teacher_timezone
             FROM homeworks h
             INNER JOIN users u ON u.id = h.teacher_id
             WHERE h.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $homeworkId]);
        $row = $stmt->fetch() ?: null;
        if (!$row) {
            return null;
        }
        if (($user['role'] ?? '') === 'teacher' && (int) $row['teacher_id'] !== (int) $user['id']) {
            return null;
        }
        return $row;
    }

    private static function fetchAssignedStudents(PDO $pdo, int $homeworkId): array
    {
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email
             FROM homework_assigned_students hs
             INNER JOIN users u ON u.id = hs.student_id
             WHERE hs.homework_id = :hid
             ORDER BY u.name'
        );
        $stmt->execute(['hid' => $homeworkId]);
        return $stmt->fetchAll() ?: [];
    }

    private static function fetchHomeworkAttachments(\PDO $pdo, int $homeworkId): array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM homework_attachments WHERE homework_id = :hid ORDER BY uploaded_at DESC, id DESC'
        );
        $stmt->execute(['hid' => $homeworkId]);
        return $stmt->fetchAll() ?: [];
    }

    private static function fetchAttachmentsByHomework(\PDO $pdo, array $homeworkIds): array
    {
        $homeworkIds = array_values(array_unique(array_filter(array_map('intval', $homeworkIds))));
        if (empty($homeworkIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($homeworkIds), '?'));
        $stmt = $pdo->prepare(
            'SELECT * FROM homework_attachments
             WHERE homework_id IN (' . $placeholders . ')
             ORDER BY uploaded_at DESC, id DESC'
        );
        $stmt->execute($homeworkIds);
        $rows = $stmt->fetchAll() ?: [];
        $map = [];
        foreach ($rows as $row) {
            $hid = (int) $row['homework_id'];
            $map[$hid][] = $row;
        }
        return $map;
    }

    private static function fetchStudentSubmissionRows(\PDO $pdo, int $studentId, array $homeworkIds): array
    {
        $homeworkIds = array_values(array_unique(array_filter(array_map('intval', $homeworkIds))));
        if (empty($homeworkIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($homeworkIds), '?'));
        $params = array_merge([$studentId], $homeworkIds);
        $stmt = $pdo->prepare(
            'SELECT * FROM homework_submissions
             WHERE student_id = ?
               AND homework_id IN (' . $placeholders . ')
             ORDER BY submitted_at DESC, id DESC'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];
        $map = [];
        foreach ($rows as $row) {
            $hid = (int) $row['homework_id'];
            $map[$hid][] = $row;
        }
        return $map;
    }

    private static function fetchAssignedStudentIds(\PDO $pdo, int $homeworkId): array
    {
        $stmt = $pdo->prepare('SELECT student_id FROM homework_assigned_students WHERE homework_id = :hid');
        $stmt->execute(['hid' => $homeworkId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []);
    }

    private static function syncAssignedStudents(\PDO $pdo, int $homeworkId, array $studentIds): void
    {
        $pdo->prepare('DELETE FROM homework_assigned_students WHERE homework_id = :hid')
            ->execute(['hid' => $homeworkId]);
        if (empty($studentIds)) {
            return;
        }
        $ins = $pdo->prepare(
            'INSERT INTO homework_assigned_students (homework_id, student_id)
             VALUES (:hid, :sid)'
        );
        foreach ($studentIds as $sid) {
            $ins->execute([
                'hid' => $homeworkId,
                'sid' => (int) $sid,
            ]);
        }
    }

    private static function notifyAssignedStudents(
        \PDO $pdo,
        int $homeworkId,
        string $title,
        string $description,
        ?string $dueDate,
        string $dueTimezone
    ): void
    {
        $stmt = $pdo->prepare(
            'SELECT u.email, u.name, u.timezone
             FROM homework_assigned_students hs
             INNER JOIN users u ON u.id = hs.student_id
             WHERE hs.homework_id = :hid'
        );
        $stmt->execute(['hid' => $homeworkId]);
        $students = $stmt->fetchAll() ?: [];
        foreach ($students as $stu) {
            if (empty($stu['email'])) {
                continue;
            }
            $studentTimezone = resolveUserTimezone($stu, $dueTimezone);
            $dueInStudentTimezone = $dueDate !== null && trim($dueDate) !== ''
                ? formatUtcForTimezone($dueDate, $studentTimezone, 'd M Y h:i A T')
                : 'Not set';
            $dueInScheduledTimezone = $dueDate !== null && trim($dueDate) !== ''
                ? formatUtcForTimezone($dueDate, $dueTimezone, 'd M Y h:i A T')
                : 'Not set';
            $subj = 'New homework: ' . $title;
            $body = '<p>Hi ' . htmlspecialchars((string) $stu['name'], ENT_QUOTES, 'UTF-8') . ',</p>'
                . '<p><strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong></p>'
                . '<p>' . nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')) . '</p>'
                . '<p><strong>Due in your timezone:</strong> ' . htmlspecialchars($dueInStudentTimezone, ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p><strong>Original due timezone:</strong> ' . htmlspecialchars($dueInScheduledTimezone, ENT_QUOTES, 'UTF-8') . '<br>'
                . htmlspecialchars($dueTimezone, ENT_QUOTES, 'UTF-8') . '</p>';
            try {
                Mailer::send((string) $stu['email'], $subj, $body, true);
            } catch (\Throwable $e) {
                // Keep the assignment flow resilient.
            }
        }
    }

    private static function removeAttachmentRows(\PDO $pdo, int $homeworkId, array $attachmentIds): void
    {
        if (empty($attachmentIds)) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($attachmentIds), '?'));
        $params = array_merge([$homeworkId], $attachmentIds);
        $sel = $pdo->prepare(
            'SELECT id, file_path FROM homework_attachments
             WHERE homework_id = ?
               AND id IN (' . $placeholders . ')'
        );
        $sel->execute($params);
        $rows = $sel->fetchAll() ?: [];
        if (empty($rows)) {
            return;
        }

        $del = $pdo->prepare(
            'DELETE FROM homework_attachments
             WHERE homework_id = :hid
               AND id = :id'
        );
        foreach ($rows as $row) {
            $del->execute([
                'hid' => $homeworkId,
                'id' => (int) $row['id'],
            ]);
            self::removeStoredFile((string) $row['file_path']);
        }
    }

    private static function removeAllAttachmentRows(\PDO $pdo, int $homeworkId): void
    {
        $rows = self::fetchHomeworkAttachments($pdo, $homeworkId);
        $pdo->prepare('DELETE FROM homework_attachments WHERE homework_id = :hid')
            ->execute(['hid' => $homeworkId]);
        foreach ($rows as $row) {
            self::removeStoredFile((string) $row['file_path']);
        }
    }

    private static function storeHomeworkAttachments(\PDO $pdo, int $homeworkId, $files): void
    {
        $check = self::validateUploadFiles($files, 'attachments');
        if (empty($check['items'])) {
            return;
        }
        $ins = $pdo->prepare(
            'INSERT INTO homework_attachments (homework_id, file_name, file_path, uploaded_at)
             VALUES (:hid, :name, :path, UTC_TIMESTAMP())'
        );
        foreach ($check['items'] as $meta) {
            $saved = self::moveValidatedFile($meta, self::HOMEWORK_UPLOAD_SUBDIR);
            $ins->execute([
                'hid' => $homeworkId,
                'name' => $saved['original_name'],
                'path' => $saved['relative_path'],
            ]);
        }
    }

    private static function validateUploadFiles($files, string $label): array
    {
        $result = ['errors' => [], 'items' => []];
        if (!$files || !isset($files['name'])) {
            return $result;
        }

        $names = $files['name'];
        $tmpNames = $files['tmp_name'] ?? [];
        $sizes = $files['size'] ?? [];
        $errors = $files['error'] ?? [];
        if (!is_array($names)) {
            $names = [$names];
            $tmpNames = [$tmpNames];
            $sizes = [$sizes];
            $errors = [$errors];
        }
        foreach ($names as $i => $originalName) {
            $name = (string) $originalName;
            $err = (int) ($errors[$i] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE || $name === '') {
                continue;
            }
            if ($err !== UPLOAD_ERR_OK) {
                $result['errors'][] = ucfirst($label) . ': upload failed for ' . $name . '.';
                continue;
            }
            $size = (int) ($sizes[$i] ?? 0);
            if ($size <= 0 || $size > self::UPLOAD_MAX_BYTES) {
                $result['errors'][] = ucfirst($label) . ': ' . $name . ' exceeds max 5MB.';
                continue;
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXT, true)) {
                $result['errors'][] = ucfirst($label) . ': invalid file type for ' . $name . '.';
                continue;
            }
            $tmp = (string) ($tmpNames[$i] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                $result['errors'][] = ucfirst($label) . ': invalid upload source for ' . $name . '.';
                continue;
            }
            $result['items'][] = [
                'original_name' => $name,
                'extension' => $ext,
                'tmp_name' => $tmp,
                'size' => $size,
            ];
        }

        return $result;
    }

    private static function moveValidatedFile(array $meta, string $subdir): array
    {
        $projectRoot = dirname(__DIR__, 2);
        $targetDir = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subdir);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Could not create upload directory: ' . $subdir);
        }

        $safeBase = preg_replace('/[^a-zA-Z0-9._-]+/', '_', (string) $meta['original_name']);
        $safeBase = trim((string) $safeBase, '._-');
        if ($safeBase === '') {
            $safeBase = 'file';
        }
        $stored = uniqid('hw_', true) . '_' . $safeBase;
        $absolute = $targetDir . DIRECTORY_SEPARATOR . $stored;

        if (!move_uploaded_file((string) $meta['tmp_name'], $absolute)) {
            throw new \RuntimeException('Could not move uploaded file: ' . $meta['original_name']);
        }

        $relative = rtrim($subdir, '/') . '/' . $stored;
        return [
            'stored_name' => $stored,
            'relative_path' => $relative,
            'original_name' => (string) $meta['original_name'],
        ];
    }

    private static function removeStoredFile(string $relativePath): void
    {
        if ($relativePath === '') {
            return;
        }
        $abs = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    private static function canAccessHomework(array $row, ?array $user, \PDO $pdo): bool
    {
        if (!$user) {
            return false;
        }
        $role = (string) ($user['role'] ?? '');
        if ($role === 'admin') {
            return true;
        }
        if ($role === 'teacher') {
            return (int) ($row['teacher_id'] ?? 0) === (int) ($user['id'] ?? 0);
        }
        $stmt = $pdo->prepare(
            'SELECT 1 FROM homework_assigned_students
             WHERE homework_id = :hid
               AND student_id = :sid
             LIMIT 1'
        );
        $stmt->execute([
            'hid' => (int) ($row['homework_id'] ?? $row['id'] ?? 0),
            'sid' => (int) $user['id'],
        ]);
        return (bool) $stmt->fetchColumn();
    }

    private static function canAccessSubmissionRow(array $row, ?array $user, \PDO $pdo): bool
    {
        if (!$user) {
            return false;
        }
        $role = (string) ($user['role'] ?? '');
        if ($role === 'admin') {
            return true;
        }
        if ($role === 'student') {
            return (int) $row['student_id'] === (int) $user['id'];
        }
        if ($role === 'teacher') {
            if ((int) $row['teacher_id'] !== (int) $user['id']) {
                return false;
            }
            $stmt = $pdo->prepare(
                'SELECT 1 FROM teacher_students
                 WHERE teacher_id = :tid
                   AND student_id = :sid
                 LIMIT 1'
            );
            $stmt->execute([
                'tid' => (int) $user['id'],
                'sid' => (int) $row['student_id'],
            ]);
            return (bool) $stmt->fetchColumn();
        }
        return false;
    }
}

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/MeetingTrackingService.php';
require_once dirname(__DIR__) . '/models/ClassRecording.php';
require_once dirname(__DIR__) . '/models/User.php';

class RecordingController
{
    public static function adminIndex(): void
    {
        Auth::requireRole(['admin']);
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'teacher_id' => (int) ($_GET['teacher_id'] ?? 0),
            'student_id' => (int) ($_GET['student_id'] ?? 0),
        ];

        View::render('admin/recordings/index', [
            'pageTitle' => 'Recordings',
            'recordings' => ClassRecording::listForAdmin($filters),
            'filters' => $filters,
            'teachers' => User::allTeachers(),
            'students' => User::allStudents(),
        ]);
    }

    public static function toggleVisibility(): void
    {
        Auth::requireRole(['admin']);
        $recordingId = (int) ($_POST['recording_id'] ?? 0);
        $classId = (int) ($_POST['class_id'] ?? 0);
        $visible = (string) ($_POST['visible_to_student'] ?? 'no');
        if ($recordingId > 0) {
            ClassRecording::setVisibility($recordingId, $visible);
            $_SESSION['flash_success'] = 'Recording visibility updated.';
        } elseif ($classId > 0) {
            ClassRecording::setVisibilityForClass($classId, $visible);
            $_SESSION['flash_success'] = 'Recording visibility will apply when the file syncs from Google Drive.';
        }

        $base = appWebPath();
        redirect($_SERVER['HTTP_REFERER'] ?? url('admin/recordings'));
        exit;
    }

    public static function manualSave(): void
    {
        Auth::requireRole(['admin', 'teacher']);
        $classId = (int) ($_POST['class_id'] ?? 0);
        $recordingUrl = trim((string) ($_POST['recording_url'] ?? ''));
        $recordingTitle = trim((string) ($_POST['recording_title'] ?? ''));
        $duration = (int) ($_POST['recording_duration'] ?? 0);
        $visible = (string) ($_POST['visible_to_student'] ?? 'no');
        $base = appWebPath();

        if ($classId <= 0 || $recordingUrl === '') {
            $_SESSION['flash_warning'] = 'Recording URL is required.';
            redirect($_SERVER['HTTP_REFERER'] ?? url('admin/recordings'));
            exit;
        }

        $service = new MeetingTrackingService();
        $class = $service->getClassById($classId);
        if ($class === null) {
            $_SESSION['flash_warning'] = 'Class not found.';
            redirect($_SERVER['HTTP_REFERER'] ?? url('admin/recordings'));
            exit;
        }

        $user = Auth::user() ?: [];
        if (Auth::isTeacher() && (int) ($class['teacher_id'] ?? 0) !== (int) ($user['id'] ?? 0)) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        ClassRecording::upsertForClass($classId, (int) $class['teacher_id'], [
            'recording_url' => $recordingUrl,
            'recording_file_id' => null,
            'recording_title' => $recordingTitle !== '' ? $recordingTitle : ((string) ($class['title'] ?? 'Class Recording')),
            'recording_duration' => $duration > 0 ? $duration : null,
            'visible_to_student' => Auth::isAdmin() ? ($visible === 'yes' ? 'yes' : 'no') : 'no',
            'sync_status' => 'ready',
            'source' => 'manual',
        ]);
        $pdo = Database::connection();
        $pdo->prepare(
            'UPDATE class_sessions
             SET recording_url = :recording_url,
                 recording_sync_status = "ready",
                 recording_sync_error = NULL,
                 recording_synced_at = UTC_TIMESTAMP()
             WHERE id = :id'
        )->execute([
            'recording_url' => $recordingUrl,
            'id' => $classId,
        ]);

        $_SESSION['flash_success'] = 'Recording saved successfully.';
        redirect($_SERVER['HTTP_REFERER'] ?? url('admin/recordings'));
        exit;
    }
}

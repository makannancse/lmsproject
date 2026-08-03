<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/MeetingTrackingService.php';
require_once dirname(__DIR__) . '/models/ClassRecording.php';
require_once dirname(__DIR__) . '/models/User.php';
require_once dirname(__DIR__) . '/lib/Pagination.php';

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
        $req = Pagination::fromRequest();
        $total = ClassRecording::countForAdmin($filters);
        $recordings = ClassRecording::listForAdmin($filters, $req['per_page'], $req['offset']);
        $pagination = Pagination::meta($total, $req['page'], $req['per_page']);

        View::render('admin/recordings/index', [
            'pageTitle' => 'Recordings',
            'recordings' => $recordings,
            'filters' => $filters,
            'teachers' => User::allTeachers(),
            'students' => User::allStudents(),
            'pagination' => $pagination,
            'queryParams' => array_filter([
                'q' => $filters['q'] !== '' ? $filters['q'] : null,
                'teacher_id' => $filters['teacher_id'] > 0 ? $filters['teacher_id'] : null,
                'student_id' => $filters['student_id'] > 0 ? $filters['student_id'] : null,
            ], static fn($v) => $v !== null && $v !== ''),
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
            if ($visible === 'yes') {
                $pdo = Database::connection();
                $stmt = $pdo->prepare('SELECT teacher_id, recording_file_id, source FROM class_recordings WHERE id = :id');
                $stmt->execute(['id' => $recordingId]);
                $row = $stmt->fetch();
                if ($row && $row['source'] === 'google_drive' && !empty($row['recording_file_id'])) {
                    require_once dirname(__DIR__) . '/lib/GoogleDriveRecordingService.php';
                    $gDrive = new GoogleDriveRecordingService();
                    $gDrive->shareFileWithAnyone((int) $row['teacher_id'], $row['recording_file_id']);
                }
            }
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

    /**
     * Clear (detach) a mismatched or wrong Drive recording from a class so it can be re-synced.
     * Resets recording_file_id, recording_url, and sync_status on both class_recordings and class_sessions.
     */
    public static function clearRecording(): void
    {
        Auth::requireRole(['admin']);
        $recordingId = (int) ($_POST['recording_id'] ?? 0);
        if ($recordingId <= 0) {
            $_SESSION['flash_warning'] = 'Invalid recording ID.';
            redirect($_SERVER['HTTP_REFERER'] ?? url('admin/recordings'));
            exit;
        }

        $pdo = Database::connection();

        // Fetch the recording row to get class_id for the class_sessions update.
        $stmt = $pdo->prepare('SELECT class_id FROM class_recordings WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $recordingId]);
        $row = $stmt->fetch();
        if (!$row) {
            $_SESSION['flash_warning'] = 'Recording not found.';
            redirect($_SERVER['HTTP_REFERER'] ?? url('admin/recordings'));
            exit;
        }

        $classId = (int) ($row['class_id'] ?? 0);

        // Reset the class_recordings row.
        $pdo->prepare(
            'UPDATE class_recordings
             SET recording_url = NULL,
                 recording_file_id = NULL,
                 recording_title = NULL,
                 recording_duration = NULL,
                 sync_status = "processing",
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        )->execute(['id' => $recordingId]);

        // Also reset the class_sessions snapshot.
        if ($classId > 0) {
            $pdo->prepare(
                'UPDATE class_sessions
                 SET recording_url = NULL,
                     recording_sync_status = "processing",
                     recording_sync_error = NULL,
                     recording_synced_at = NULL
                 WHERE id = :id'
            )->execute(['id' => $classId]);
        }

        $_SESSION['flash_success'] = 'Recording cleared. You can now retry sync to find the correct file.';
        redirect($_SERVER['HTTP_REFERER'] ?? url('admin/recordings'));
        exit;
    }
}

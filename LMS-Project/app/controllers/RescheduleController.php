<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/GoogleCalendarMeetingService.php';
require_once dirname(__DIR__) . '/models/ClassSession.php';
require_once dirname(__DIR__) . '/models/RescheduleRequest.php';
require_once dirname(__DIR__) . '/lib/NotificationMailer.php';

class RescheduleController
{
    /**
     * @param array<string, mixed> $class
     * @param array<string, mixed> $req
     */
    private static function notifyAdminReschedule(array $class, array $req, string $requestedByLabel, string $actionLabel = 'Request'): void
    {
        $pdo = Database::connection();
        $teacherName = '';
        $studentName = '';
        $tStmt = $pdo->prepare('SELECT name FROM users WHERE id = :id LIMIT 1');
        $tStmt->execute(['id' => (int) ($req['teacher_id'] ?? $class['teacher_id'] ?? 0)]);
        $teacherName = (string) ($tStmt->fetchColumn() ?: '');
        $sStmt = $pdo->prepare('SELECT name FROM users WHERE id = :id LIMIT 1');
        $sStmt->execute(['id' => (int) ($req['student_id'] ?? 0)]);
        $studentName = (string) ($sStmt->fetchColumn() ?: '');

        $originalDate = formatClassScheduledAt($class, 'Y-m-d h:i A T');
        $requestedDate = trim((string) ($req['requested_date'] ?? '')) . ' '
            . trim((string) ($req['requested_time'] ?? ''))
            . ' (' . (string) ($req['new_timezone'] ?? $req['old_timezone'] ?? APP_TIMEZONE) . ')';

        NotificationMailer::notifyAdminReschedule([
            'action_label' => $actionLabel,
            'class_title' => (string) ($class['title'] ?? 'Class'),
            'teacher_name' => $teacherName,
            'student_name' => $studentName,
            'original_date' => $originalDate,
            'requested_date' => $requestedDate,
            'requested_by' => $requestedByLabel,
        ]);
    }
    /** Student: list enrolled upcoming classes + request form */
    public static function studentIndex(): void
    {
        Auth::requireRole(['student']);
        $user = Auth::user();
        $studentId = (int) ($user['id'] ?? 0);

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT cs.*, t.name AS teacher_name
             FROM class_sessions cs
             INNER JOIN users t ON t.id = cs.teacher_id
             INNER JOIN enrollments e ON e.class_id = cs.id
             WHERE e.student_id = :sid
               AND cs.status IN ("scheduled", "rescheduled")
             ORDER BY cs.start_datetime ASC'
        );
        $stmt->execute(['sid' => $studentId]);
        $classes = $stmt->fetchAll() ?: [];

        $myRequests = RescheduleRequest::forStudent($studentId);

        View::render('student/reschedule_modern', [
            'pageTitle' => 'Reschedule Requests',
            'classes' => $classes,
            'requests' => $myRequests,
        ]);
    }

    public static function studentStore(): void
    {
        Auth::requireRole(['student']);
        $user = Auth::user();
        $studentId = (int) ($user['id'] ?? 0);
        $base = appWebPath();

        $classId = (int) ($_POST['class_id'] ?? 0);
        $reqDate = trim($_POST['requested_date'] ?? '');
        $reqTime = trim($_POST['requested_time'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        if ($classId <= 0 || $reqDate === '' || $reqTime === '') {
            redirectTo('/student/reschedule');
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT cs.* FROM class_sessions cs
             INNER JOIN enrollments e ON e.class_id = cs.id
             WHERE cs.id = :cid AND e.student_id = :sid LIMIT 1'
        );
        $stmt->execute(['cid' => $classId, 'sid' => $studentId]);
        $class = $stmt->fetch();
        if (!$class) {
            redirectTo('/student/reschedule');
            return;
        }

        $oldTimezone = classScheduledTimezone($class, APP_TIMEZONE);
        $newTimezone = normalizeTimezone((string) ($_POST['new_timezone'] ?? $oldTimezone), $oldTimezone);

        $ins = $pdo->prepare(
            'INSERT INTO reschedule_requests
                (class_id, student_id, teacher_id, requested_by, initiated_by, requested_date, requested_time, old_timezone, new_timezone, reason, status)
             VALUES
                (:class_id, :student_id, :teacher_id, "student", "student", :requested_date, :requested_time, :old_timezone, :new_timezone, :reason, "pending")'
        );
        $ins->execute([
            'class_id' => $classId,
            'student_id' => $studentId,
            'teacher_id' => (int) $class['teacher_id'],
            'requested_date' => $reqDate,
            'requested_time' => strlen($reqTime) === 5 ? $reqTime . ':00' : $reqTime,
            'old_timezone' => $oldTimezone,
            'new_timezone' => $newTimezone,
            'reason' => $reason ?: null,
        ]);

        self::notifyAdminReschedule($class, [
            'teacher_id' => (int) $class['teacher_id'],
            'student_id' => $studentId,
            'requested_date' => $reqDate,
            'requested_time' => strlen($reqTime) === 5 ? $reqTime . ':00' : $reqTime,
            'old_timezone' => $oldTimezone,
            'new_timezone' => $newTimezone,
        ], 'Student', 'Request');

        $_SESSION['flash_success'] = 'Reschedule request submitted.';
        redirectTo('/student/reschedule');
    }

    public static function teacherIndex(): void
    {
        Auth::requireRole(['teacher']);
        $user = Auth::user();
        $teacherId = (int) ($user['id'] ?? 0);

        $requests = RescheduleRequest::pendingForTeacher($teacherId);

        View::render('teacher/reschedule_modern', [
            'pageTitle' => 'Reschedule Requests',
            'requests' => $requests,
            'canManageAll' => false,
        ]);
    }

    public static function adminIndex(): void
    {
        Auth::requireRole(['admin']);
        $requests = RescheduleRequest::allForAdmin();
        View::render('teacher/reschedule_modern', [
            'pageTitle' => 'Reschedule Requests',
            'requests' => $requests,
            'canManageAll' => true,
        ]);
    }

    public static function teacherDecide(): void
    {
        Auth::requireRole(['teacher']);
        $user = Auth::user();
        $teacherId = (int) ($user['id'] ?? 0);
        $base = appWebPath();

        $requestId = (int) ($_POST['request_id'] ?? 0);
        $decision = $_POST['decision'] ?? '';
        $comment = trim($_POST['teacher_comment'] ?? '');

        if ($requestId <= 0 || !in_array($decision, ['approved', 'rejected'], true)) {
            redirectTo('/teacher/reschedule');
            return;
        }

        self::processDecision($requestId, $decision, $teacherId, false, $comment, null);

        $_SESSION['flash_success'] = $decision === 'approved' ? 'Request approved. Class updated.' : 'Request rejected.';
        redirectTo('/teacher/reschedule');
    }

    public static function adminDecide(): void
    {
        Auth::requireRole(['admin']);
        $base = appWebPath();
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? '');
        $comment = trim((string) ($_POST['admin_comment'] ?? ''));
        if ($requestId <= 0 || !in_array($decision, ['approved', 'rejected'], true)) {
            redirectTo('/admin/reschedule');
            return;
        }
        self::processDecision($requestId, $decision, 0, true, null, $comment);
        $_SESSION['flash_success'] = $decision === 'approved' ? 'Request approved by admin.' : 'Request rejected by admin.';
        redirectTo('/admin/reschedule');
    }

    public static function teacherInitiateForm(): void
    {
        Auth::requireRole(['teacher', 'admin']);
        $user = Auth::user();
        $teacherId = (int) ($user['id'] ?? 0);
        $isAdmin = (($user['role'] ?? '') === 'admin');

        $pdo = Database::connection();
        $sql = 'SELECT cs.id AS class_id, cs.title AS class_title, cs.teacher_id, cs.start_time_utc, cs.end_time_utc,
                       cs.scheduled_timezone, t.name AS teacher_name, e.student_id, u.name AS student_name
             FROM class_sessions cs
             INNER JOIN users t ON t.id = cs.teacher_id
             INNER JOIN enrollments e ON e.class_id = cs.id
             INNER JOIN users u ON u.id = e.student_id
             WHERE cs.status IN ("scheduled", "rescheduled")';
        $params = [];
        if (!$isAdmin) {
            $sql .= ' AND cs.teacher_id = :tid';
            $params['tid'] = $teacherId;
        }
        $sql .= ' ORDER BY cs.start_datetime ASC, u.name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $enrollmentRows = $stmt->fetchAll() ?: [];

        View::render('teacher/reschedule_initiate_modern', [
            'pageTitle' => 'Propose Reschedule',
            'enrollmentRows' => $enrollmentRows,
            'isAdmin' => $isAdmin,
        ]);
    }

    public static function teacherInitiateStore(): void
    {
        Auth::requireRole(['teacher', 'admin']);
        $user = Auth::user();
        $role = (string) ($user['role'] ?? '');
        $teacherId = (int) ($_POST['teacher_id'] ?? ($user['id'] ?? 0));
        $base = appWebPath();

        $pair = trim($_POST['class_student'] ?? '');
        $reqDate = trim($_POST['requested_date'] ?? '');
        $reqTime = trim($_POST['requested_time'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        $classId = 0;
        $studentId = 0;
        if ($pair !== '' && strpos($pair, ':') !== false) {
            $parts = explode(':', $pair);
            $a = $parts[0] ?? '0';
            $b = $parts[1] ?? '0';
            $c = $parts[2] ?? '0';
            $classId = (int) $a;
            $studentId = (int) $b;
            if ($role === 'admin' && (int) $c > 0) {
                $teacherId = (int) $c;
            }
        }

        if ($classId <= 0 || $studentId <= 0 || $reqDate === '' || $reqTime === '') {
            redirectTo('/teacher/reschedule/new');
            return;
        }

        $pdo = Database::connection();
        $chkSql = 'SELECT cs.* FROM class_sessions cs INNER JOIN enrollments e ON e.class_id = cs.id WHERE cs.id = :cid AND e.student_id = :sid';
        $chkParams = ['cid' => $classId, 'sid' => $studentId];
        if ($role === 'teacher') {
            $chkSql .= ' AND cs.teacher_id = :tid';
            $chkParams['tid'] = (int) ($user['id'] ?? 0);
        }
        $chkSql .= ' LIMIT 1';
        $chk = $pdo->prepare($chkSql);
        $chk->execute($chkParams);
        $class = $chk->fetch();
        if (!$class) {
            redirectTo('/teacher/reschedule/new');
            return;
        }

        $oldTimezone = classScheduledTimezone($class, APP_TIMEZONE);
        $newTimezone = normalizeTimezone((string) ($_POST['new_timezone'] ?? $oldTimezone), $oldTimezone);

        // Direct reschedule by teacher/admin: immediately approved and applied.
        $requestedBy = $role === 'admin' ? 'admin' : 'teacher';
        $ins = $pdo->prepare(
            'INSERT INTO reschedule_requests
                (class_id, student_id, teacher_id, requested_by, initiated_by, requested_date, requested_time, old_timezone, new_timezone, reason, status, teacher_comment, admin_comment)
             VALUES
                (:class_id, :student_id, :teacher_id, :requested_by, :initiated_by, :requested_date, :requested_time, :old_timezone, :new_timezone, :reason, "approved", :teacher_comment, :admin_comment)'
        );
        $ins->execute([
            'class_id' => $classId,
            'student_id' => $studentId,
            'teacher_id' => $teacherId,
            'requested_by' => $requestedBy,
            'initiated_by' => $requestedBy,
            'requested_date' => $reqDate,
            'requested_time' => strlen($reqTime) === 5 ? $reqTime . ':00' : $reqTime,
            'old_timezone' => $oldTimezone,
            'new_timezone' => $newTimezone,
            'reason' => $reason ?: null,
            'teacher_comment' => $role === 'teacher' ? ($reason ?: null) : null,
            'admin_comment' => $role === 'admin' ? ($reason ?: null) : null,
        ]);

        $requestId = (int) $pdo->lastInsertId();
        self::processDecision($requestId, 'approved', (int) ($user['id'] ?? 0), $role === 'admin', null, null, true);

        self::notifyAdminReschedule($class, [
            'teacher_id' => $teacherId,
            'student_id' => $studentId,
            'requested_date' => $reqDate,
            'requested_time' => strlen($reqTime) === 5 ? $reqTime . ':00' : $reqTime,
            'old_timezone' => $oldTimezone,
            'new_timezone' => $newTimezone,
        ], $role === 'admin' ? 'Admin' : 'Teacher', 'Reschedule');

        $_SESSION['flash_success'] = 'Class rescheduled successfully.';
        redirectTo(($role === 'admin') ? '/admin/reschedule' : '/teacher/reschedule');
    }

    private static function processDecision(
        int $requestId,
        string $decision,
        int $actorTeacherId,
        bool $isAdmin,
        ?string $teacherComment,
        ?string $adminComment,
        bool $alreadyApproved = false
    ): void {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $sql = 'SELECT * FROM reschedule_requests WHERE id = :id';
            $params = ['id' => $requestId];
            if (!$isAdmin) {
                $sql .= ' AND teacher_id = :tid';
                $params['tid'] = $actorTeacherId;
            }
            $sql .= ' LIMIT 1';
            $reqStmt = $pdo->prepare($sql);
            $reqStmt->execute($params);
            $req = $reqStmt->fetch();
            if (!$req) {
                $pdo->rollBack();
                return;
            }
            if (!$alreadyApproved && $req['status'] !== 'pending') {
                $pdo->rollBack();
                return;
            }

            if (!$alreadyApproved) {
                $upd = $pdo->prepare(
                    'UPDATE reschedule_requests
                     SET status = :st, teacher_comment = COALESCE(:tc, teacher_comment), admin_comment = COALESCE(:ac, admin_comment)
                     WHERE id = :id'
                );
                $upd->execute([
                    'st' => $decision,
                    'tc' => $teacherComment ?: null,
                    'ac' => $adminComment ?: null,
                    'id' => $requestId,
                ]);
            }

            if ($decision === 'approved' || $alreadyApproved) {
                $classStmt = $pdo->prepare('SELECT * FROM class_sessions WHERE id = :id LIMIT 1');
                $classStmt->execute(['id' => (int) $req['class_id']]);
                $class = $classStmt->fetch();
                if ($class) {
                    $oldTimezone = normalizeTimezone(
                        (string) ($req['old_timezone'] ?? classScheduledTimezone($class, APP_TIMEZONE)),
                        classScheduledTimezone($class, APP_TIMEZONE)
                    );
                    $newTimezone = normalizeTimezone(
                        (string) ($req['new_timezone'] ?? $oldTimezone),
                        $oldTimezone
                    );
                    $timeStr = (string) $req['requested_time'];
                    if (strlen($timeStr) === 5) {
                        $timeStr .= ':00';
                    }
                    $localStart = new DateTimeImmutable($req['requested_date'] . ' ' . $timeStr, new DateTimeZone($newTimezone));
                    $oldStart = new DateTimeImmutable((string) classStartUtcValue($class), new DateTimeZone('UTC'));
                    $oldEnd = new DateTimeImmutable((string) classEndUtcValue($class), new DateTimeZone('UTC'));
                    $durationSec = max(0, $oldEnd->getTimestamp() - $oldStart->getTimestamp());
                    $newStartUtc = $localStart->setTimezone(new DateTimeZone('UTC'));
                    $newEndUtc = $newStartUtc->modify('+' . $durationSec . ' seconds');

                    if (!empty($class['google_event_id']) && !empty($class['teacher_id'])) {
                        $meetingService = new GoogleCalendarMeetingService();
                        $meetingService->updateMeeting(
                            (int) $class['teacher_id'],
                            (string) $class['google_event_id'],
                            utcToTimezoneIso8601($newStartUtc->format('Y-m-d H:i:s'), 'UTC'),
                            utcToTimezoneIso8601($newEndUtc->format('Y-m-d H:i:s'), 'UTC'),
                            'UTC',
                            (string) ($class['title'] ?? '')
                        );
                    }

                    $pdo->prepare(
                        'UPDATE class_sessions
                         SET start_datetime = :start_datetime,
                             scheduled_time_utc = :scheduled_time_utc,
                             start_time_utc = :start_time_utc,
                             end_datetime = :end_datetime,
                             end_time_utc = :end_time_utc,
                             timezone = :timezone,
                             scheduled_timezone = :scheduled_timezone,
                             google_conference_id = NULL,
                             meeting_live_status = "pending",
                             meeting_participant_count = NULL,
                             teacher_joined_at = NULL,
                             teacher_join_delay_minutes = NULL,
                             student_joined_at = NULL,
                             actual_start_time = NULL,
                             actual_end_time = NULL,
                             actual_duration = NULL,
                             actual_duration_minutes = NULL,
                             completed_at = NULL,
                             status = "rescheduled"
                         WHERE id = :id'
                    )->execute([
                        'start_datetime' => $newStartUtc->format('Y-m-d H:i:s'),
                        'scheduled_time_utc' => $newStartUtc->format('Y-m-d H:i:s'),
                        'start_time_utc' => $newStartUtc->format('Y-m-d H:i:s'),
                        'end_datetime' => $newEndUtc->format('Y-m-d H:i:s'),
                        'end_time_utc' => $newEndUtc->format('Y-m-d H:i:s'),
                        'timezone' => $newTimezone,
                        'scheduled_timezone' => $newTimezone,
                        'id' => (int) $class['id'],
                    ]);
                    logTimezoneConversion([
                        'event' => 'reschedule_approved_to_utc',
                        'class_id' => (int) $class['id'],
                        'old_timezone' => $oldTimezone,
                        'new_timezone' => $newTimezone,
                        'requested_date' => $req['requested_date'] ?? null,
                        'requested_time' => $req['requested_time'] ?? null,
                        'start_time_utc' => $newStartUtc->format('Y-m-d H:i:s'),
                        'end_time_utc' => $newEndUtc->format('Y-m-d H:i:s'),
                    ]);

                    if (!$alreadyApproved) {
                        self::notifyAdminReschedule($class, $req, 'System', 'Approved');
                    }
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

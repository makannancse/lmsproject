<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/MeetingTrackingLog.php';
require_once dirname(__DIR__) . '/lib/GoogleDriveRecordingService.php';
require_once dirname(__DIR__) . '/lib/GoogleCalendarMeetingService.php';
require_once dirname(__DIR__) . '/lib/GoogleMeetLiveTrackingService.php';
require_once dirname(__DIR__) . '/lib/SyncLog.php';
require_once dirname(__DIR__) . '/models/ClassAttendance.php';
require_once dirname(__DIR__) . '/models/ClassRecording.php';
require_once dirname(__DIR__) . '/models/TeacherPayout.php';
require_once dirname(__DIR__) . '/models/TeacherGoogleAccount.php';

class MeetingTrackingService
{
    /**
     * @return array<string, mixed>
     */
    public function markJoin(int $classId, int $userId, string $role): array
    {
        $class = $this->getClassById($classId);
        if ($class === null) {
            throw new RuntimeException('Class not found.');
        }

        $now = $this->utcNow();

        $pdo = Database::connection();
        if ($role === 'teacher') {
            MeetingTrackingLog::write('teacher_join_signal_received', [
                'class_id' => $classId,
                'teacher_id' => $userId,
                'google_event_id' => $class['google_event_id'] ?? null,
            ]);
            logMeetingHost([
                'event' => 'teacher_join_signal_received',
                'class_id' => $classId,
                'teacher_id' => $userId,
                'teacher_google_email' => $class['teacher_google_email'] ?? null,
                'google_event_id' => $class['google_event_id'] ?? null,
            ]);
        } else {
            ClassAttendance::markJoin($classId, $userId, $role, $now);
            $stmt = $pdo->prepare(
                'UPDATE class_sessions
                 SET student_joined_at = COALESCE(student_joined_at, :ts)
                 WHERE id = :id'
            );
            $stmt->execute(['ts' => $now, 'id' => $classId]);
            MeetingTrackingLog::write('student_joined', [
                'class_id' => $classId,
                'student_id' => $userId,
                'google_event_id' => $class['google_event_id'] ?? null,
            ]);
        }

        return $this->getClassById($classId) ?? $class;
    }

    /**
     * Teacher confirms the Meet recording reminder, then we mark the class ongoing.
     * Important: do not call markJoin() first — that would set status=ongoing before acknowledgement.
     *
     * @return array<string, mixed>
     */
    public function startTeacherSession(int $classId, int $teacherId): array
    {
        $class = $this->getClassById($classId);
        if ($class === null) {
            throw new RuntimeException('Class not found.');
        }

        $now = $this->utcNow();
        $acknowledgedAt = $now;

        $pdo = Database::connection();
        if ((int) ($class['teacher_id'] ?? 0) !== $teacherId) {
            throw new RuntimeException('Only the assigned teacher can start this class.');
        }

        $startUtc = classStartUtcValue($class);
        $delayMinutes = null;
        if ($startUtc !== null && $startUtc !== '') {
            $schedTs = $this->parseUtcTimestamp($startUtc);
            $nowTs = $this->parseUtcTimestamp($now);
            if ($schedTs !== null && $nowTs !== null) {
                $delayMinutes = max(0, (int) ceil(($nowTs - $schedTs) / 60));
            }
        }

        $stmt = $pdo->prepare(
            'UPDATE class_sessions
             SET recording_acknowledged_at = COALESCE(recording_acknowledged_at, :acknowledged_at),
                 recording_acknowledged_by = COALESCE(recording_acknowledged_by, :teacher_id),
                 status = CASE
                     WHEN status IN ("scheduled", "rescheduled") THEN "ongoing"
                     ELSE status
                 END,
                 teacher_joined_at = COALESCE(teacher_joined_at, :now),
                 teacher_join_delay_minutes = COALESCE(teacher_join_delay_minutes, :delay_minutes),
                 meeting_live_status = CASE
                     WHEN meeting_live_status = "ended" THEN meeting_live_status
                     ELSE "pending"
                 END
             WHERE id = :id'
        );
        $stmt->execute([
            'acknowledged_at' => $acknowledgedAt,
            'teacher_id' => $teacherId,
            'now' => $now,
            'delay_minutes' => $delayMinutes,
            'id' => $classId,
        ]);

        MeetingTrackingLog::write('recording_acknowledged', [
            'class_id' => $classId,
            'teacher_id' => $teacherId,
            'google_event_id' => $class['google_event_id'] ?? null,
            'recording_acknowledged_at' => $acknowledgedAt,
        ]);
        SyncLog::write('meeting_completion.log', [
            'message' => 'Teacher acknowledged recording; awaiting Google Meet host join',
            'class_id' => $classId,
            'teacher_id' => $teacherId,
            'recording_acknowledged_at' => $acknowledgedAt,
        ]);
        logMeetingHost([
            'event' => 'recording_acknowledged',
            'class_id' => $classId,
            'teacher_id' => $teacherId,
            'teacher_google_email' => $class['teacher_google_email'] ?? null,
            'google_event_id' => $class['google_event_id'] ?? null,
            'recording_acknowledged_at' => $acknowledgedAt,
        ]);

        return $this->getClassById($classId) ?? $class;
    }

    public function markLeave(int $classId, int $userId, string $role): void
    {
        $class = $this->getClassById($classId);
        if ($class === null) {
            throw new RuntimeException('Class not found.');
        }

        $now = $this->utcNow();
        MeetingTrackingLog::write($role === 'teacher' ? 'teacher_leave_requested' : 'student_left', [
            'class_id' => $classId,
            'user_id' => $userId,
            'role' => $role,
            'google_event_id' => $class['google_event_id'] ?? null,
        ]);

        if ($role === 'teacher') {
            $this->finalizeTeacherHostLeave($classId, 'teacher_leave_request');
            return;
        }

        ClassAttendance::markLeave($classId, $userId, $role, $now);
    }

    /**
     * After the host leaves Google Meet, poll conference activity and mark the class completed
     * only when the Google Meet conference itself has ended (i.e., all participants have left).
     *
     * If the conference is still active after the host leaves (students still present),
     * the class remains "ongoing" and the background cron will complete it once everyone leaves.
     *
     * @return array<string, mixed>
     */
    public function finalizeTeacherHostLeave(int $classId, string $trigger = 'teacher_leave_request'): array
    {
        $liveService = new GoogleMeetLiveTrackingService();
        $sync = $liveService->syncClassAfterHostLeave($classId, $trigger);

        // Check if the sync already marked the class completed (conference fully ended).
        $refreshed = $this->getClassById($classId);
        $currentStatus = (string) ($refreshed['status'] ?? '');

        if ($currentStatus === 'completed' || ($sync['status'] ?? '') === 'completed') {
            // Already completed by the live sync — no further action needed.
            SyncLog::write('google_meet_status.log', [
                'event' => 'host_leave_class_already_completed',
                'class_id' => $classId,
                'trigger' => $trigger,
                'sync_status' => $sync['status'] ?? 'unknown',
            ]);
            return [
                'sync' => $sync,
                'class' => $refreshed ?? [],
                'status' => 'completed',
            ];
        }

        // Conference still active (other participants remain). Keep the class ongoing.
        // The background cron (sync_meeting_status.php) will poll and complete it when the
        // conference end_time is set by Google Meet (meaning all participants have left).
        SyncLog::write('google_meet_status.log', [
            'event' => 'host_left_conference_still_active',
            'class_id' => $classId,
            'trigger' => $trigger,
            'sync_status' => $sync['status'] ?? 'unknown',
            'meeting_live_status' => $sync['meeting_live_status'] ?? null,
            'participant_count' => $sync['participant_count'] ?? null,
            'note' => 'Class kept ongoing; cron will complete when all participants leave',
        ]);

        return [
            'sync' => $sync,
            'class' => $refreshed ?? [],
            'status' => $currentStatus,
        ];
    }

    public function completeClass(int $classId, ?string $endedAt = null, string $trigger = 'meeting_ended'): void
    {
        $class = $this->getClassById($classId);
        if ($class === null) {
            throw new RuntimeException('Class not found.');
        }

        try {
            $liveService = new GoogleMeetLiveTrackingService();
            if (in_array($trigger, ['teacher_leave_request', 'manual_end_request', 'meeting_ended', 'google_webhook', 'workspace_event'], true)) {
                $liveService->syncClassAfterHostLeave($classId, $trigger);
            } else {
                $liveService->syncClass($classId, $trigger);
            }
            $refreshed = $this->getClassById($classId);
            if (is_array($refreshed)) {
                $class = $refreshed;
            }
        } catch (\Throwable $ignored) {
            // Fall through only when Meet sync is unavailable.
        }

        if ((string) ($class['status'] ?? '') === 'completed') {
            TeacherPayout::ensureForCompletedClass($classId);
            require_once dirname(__DIR__) . '/lib/RecurringSeriesService.php';
            RecurringSeriesService::syncOccurrenceFromClassSession($classId, $class);
            SyncLog::write('google_meet_status.log', [
                'event' => 'class_already_completed',
                'class_id' => $classId,
                'trigger' => $trigger,
                'actual_end_time' => $class['actual_end_time'] ?? null,
            ]);
            return;
        }

        $endTime = $this->normalizeUtcValue((string) ($class['actual_end_time'] ?? ''));
        if ($endTime === null) {
            $endTime = $this->normalizeUtcValue((string) $endedAt) ?? $this->utcNow();
        }

        $actualStart = $this->resolveActualClassStartUtc($class);
        if ($actualStart === null) {
            $actualStart = $this->normalizeUtcValue((string) ($class['teacher_joined_at'] ?? ''))
                ?? classStartUtcValue($class)
                ?? $this->utcNow();
        }

        $endTimeTs = $this->parseUtcTimestamp($endTime) ?? time();
        $actualStartTs = $this->parseUtcTimestamp($actualStart);
        $duration = null;
        if ($actualStartTs !== null) {
            $duration = max(0, (int) round(($endTimeTs - $actualStartTs) / 60));
        }

        $teacherJoinDelay = null;
        $joinTimeUtc = $class['teacher_joined_at'] ?? $actualStart;
        $schedStartUtc = classStartUtcValue($class);
        if ($joinTimeUtc !== null && $joinTimeUtc !== '' && $schedStartUtc !== null && $schedStartUtc !== '') {
            $joinTs = $this->parseUtcTimestamp((string) $joinTimeUtc);
            $schedTs = $this->parseUtcTimestamp((string) $schedStartUtc);
            if ($joinTs !== null && $schedTs !== null && $joinTs > $schedTs) {
                $teacherJoinDelay = (int) ceil(($joinTs - $schedTs) / 60);
            } elseif ($joinTs !== null && $schedTs !== null) {
                $teacherJoinDelay = 0;
            }
        }
        if ($teacherJoinDelay === null && isset($class['teacher_join_delay_minutes']) && $class['teacher_join_delay_minutes'] !== null) {
            $teacherJoinDelay = (int) $class['teacher_join_delay_minutes'];
        }

        $recordingStatus = $this->isRecordingSyncEligible($class)
            ? (trim((string) ($class['recording_url'] ?? '')) === '' ? 'processing' : 'ready')
            : 'pending';

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE class_sessions
             SET status = "completed",
                 actual_start_time = COALESCE(actual_start_time, :actual_start_time),
                 actual_end_time = COALESCE(actual_end_time, :actual_end_time),
                 teacher_join_delay_minutes = COALESCE(teacher_join_delay_minutes, :teacher_join_delay_minutes),
                 actual_duration = CASE
                     WHEN :has_actual_start_for_duration = 1 AND (actual_duration IS NULL OR actual_duration <= 0) THEN :actual_duration
                     ELSE actual_duration
                 END,
                 actual_duration_minutes = CASE
                     WHEN :has_actual_start_for_minutes = 1 AND (actual_duration_minutes IS NULL OR actual_duration_minutes <= 0) THEN :actual_duration_minutes
                     ELSE actual_duration_minutes
                 END,
                 completed_at = COALESCE(completed_at, :completed_at),
                 recording_sync_status = :recording_sync_status,
                 recording_sync_error = CASE
                     WHEN :clear_recording_sync_error = 1 THEN NULL
                     ELSE recording_sync_error
                 END
             WHERE id = :id'
        );
        $stmt->execute([
            'actual_start_time' => $actualStart,
            'actual_end_time' => $endTime,
            'teacher_join_delay_minutes' => $teacherJoinDelay,
            'actual_duration' => $duration,
            'actual_duration_minutes' => $duration,
            'has_actual_start_for_duration' => $actualStartTs !== null ? 1 : 0,
            'has_actual_start_for_minutes' => $actualStartTs !== null ? 1 : 0,
            'completed_at' => $endTime,
            'recording_sync_status' => $recordingStatus,
            'clear_recording_sync_error' => $recordingStatus !== 'processing' ? 1 : 0,
            'id' => $classId,
        ]);

        $refreshedClass = $this->getClassById($classId);
        if (is_array($refreshedClass)) {
            require_once dirname(__DIR__) . '/lib/RecurringSeriesService.php';
            RecurringSeriesService::syncOccurrenceFromClassSession($classId, $refreshedClass);
        }

        TeacherPayout::ensureForCompletedClass($classId);
        MeetingTrackingLog::write('meeting_ended', [
            'class_id' => $classId,
            'google_event_id' => $class['google_event_id'] ?? null,
            'trigger' => $trigger,
            'actual_start_time' => $actualStart,
            'actual_end_time' => $endTime,
            'actual_duration' => $duration,
        ]);
        SyncLog::write('meeting_completion.log', [
            'message' => 'Class completed',
            'class_id' => $classId,
            'trigger' => $trigger,
            'actual_end_time' => $endTime,
            'actual_duration_minutes' => $duration,
        ]);
        SyncLog::write('google_meet_status.log', [
            'event' => 'class_completed',
            'class_id' => $classId,
            'trigger' => $trigger,
            'host_left' => true,
            'actual_end_time' => $endTime,
            'actual_duration_minutes' => $duration,
            'status_update_result' => 'completed',
        ]);
    }

    /**
     * @return array{status:string,message:string,recording:?array}
     */
    public function syncRecordingForClass(int $classId, bool $force = false): array
    {
        $class = $this->getClassById($classId);
        if ($class === null) {
            throw new RuntimeException('Class not found.');
        }

        if (($class['status'] ?? '') !== 'completed' && !$force) {
            return [
                'status' => 'pending',
                'message' => 'Recording sync waits until the class is completed.',
                'recording' => null,
            ];
        }

        if ((int) ($class['recording_enabled'] ?? 0) !== 1) {
            $message = 'Recording disabled for this class.';
            $this->logRecordingSyncDebug([
                'message' => 'Recording sync skipped because recording is disabled',
                'class_id' => $classId,
                'teacher_id' => (int) ($class['teacher_id'] ?? 0),
                'teacher_email' => $class['teacher_google_email'] ?? null,
                'status' => 'disabled',
            ]);

            return [
                'status' => 'disabled',
                'message' => $message,
                'recording' => null,
            ];
        }



        $this->setClassRecordingSyncState($classId, 'processing', null);

        try {
            $service = new GoogleDriveRecordingService();
            $search = $service->findRecordingForClassDetailed($class);
            $recording = $search['recording'] ?? null;
            $driveDebug = (array) ($search['debug'] ?? []);
            if ($recording === null) {
                if ($this->hasRecordingSyncTimedOut($class)) {
                    $message = 'Recording not found in Google Drive within 60 minutes of class completion.';
                    $this->setClassRecordingSyncState($classId, 'failed', $message);
                    MeetingTrackingLog::write('recording_sync_failed_timeout', [
                        'class_id' => $classId,
                        'google_event_id' => $class['google_event_id'] ?? null,
                    ]);
                    $this->logRecordingSyncDebug([
                        'message' => 'Recording sync timed out',
                        'class_id' => $classId,
                        'teacher_id' => (int) ($class['teacher_id'] ?? 0),
                        'teacher_email' => $class['teacher_google_email'] ?? null,
                        'status' => 'failed',
                        'result' => $message,
                        'drive_debug' => $driveDebug,
                    ]);

                    return ['status' => 'failed', 'message' => $message, 'recording' => null];
                }

                $message = 'Recording processing in progress';
                $this->setClassRecordingSyncState($classId, 'processing', $message);
                MeetingTrackingLog::write('recording_sync_pending', [
                    'class_id' => $classId,
                    'google_event_id' => $class['google_event_id'] ?? null,
                ]);
                logMeetingHost([
                    'event' => 'recording_sync_pending',
                    'class_id' => $classId,
                    'teacher_id' => (int) ($class['teacher_id'] ?? 0),
                    'teacher_google_email' => $class['teacher_google_email'] ?? null,
                    'google_event_id' => $class['google_event_id'] ?? null,
                ]);
                $this->logRecordingSyncDebug([
                    'message' => 'Recording sync still processing',
                    'class_id' => $classId,
                    'teacher_id' => (int) ($class['teacher_id'] ?? 0),
                    'teacher_email' => $class['teacher_google_email'] ?? null,
                    'status' => 'processing',
                    'result' => $message,
                    'drive_debug' => $driveDebug,
                ]);

                return ['status' => 'processing', 'message' => $message, 'recording' => null];
            }

            $visibility = $this->existingVisibilityForClass($classId);
            ClassRecording::upsertForClass($classId, (int) $class['teacher_id'], [
                'recording_url' => $recording['recording_url'] ?? null,
                'recording_file_id' => $recording['recording_file_id'] ?? null,
                'recording_title' => $recording['recording_title'] ?? null,
                'recording_duration' => $recording['recording_duration'] ?? null,
                'visible_to_student' => $visibility,
                'sync_status' => 'ready',
                'source' => $recording['source'] ?? 'google_drive',
            ]);
            
            if ($visibility === 'yes' && !empty($recording['recording_file_id']) && ($recording['source'] ?? 'google_drive') === 'google_drive') {
                require_once dirname(__DIR__) . '/lib/GoogleDriveRecordingService.php';
                $gDrive = new GoogleDriveRecordingService();
                $gDrive->shareFileWithAnyone((int) $class['teacher_id'], $recording['recording_file_id']);
            }
            $this->setClassRecordingSyncState($classId, 'ready', null, (string) ($recording['recording_url'] ?? ''));

            MeetingTrackingLog::write('recording_synced', [
                'class_id' => $classId,
                'google_event_id' => $class['google_event_id'] ?? null,
                'recording_file_id' => $recording['recording_file_id'] ?? null,
                'recording_url' => $recording['recording_url'] ?? null,
            ]);
            logMeetingHost([
                'event' => 'recording_synced',
                'class_id' => $classId,
                'teacher_id' => (int) ($class['teacher_id'] ?? 0),
                'teacher_google_email' => $class['teacher_google_email'] ?? null,
                'google_event_id' => $class['google_event_id'] ?? null,
                'recording_file_id' => $recording['recording_file_id'] ?? null,
                'recording_url' => $recording['recording_url'] ?? null,
            ]);
            $this->logRecordingSyncDebug([
                'message' => 'Recording synced',
                'class_id' => $classId,
                'teacher_id' => (int) ($class['teacher_id'] ?? 0),
                'teacher_email' => $class['teacher_google_email'] ?? null,
                'status' => 'synced',
                'recording_file_id' => $recording['recording_file_id'] ?? null,
                'recording_url' => $recording['recording_url'] ?? null,
                'drive_debug' => $driveDebug,
            ]);

            return ['status' => 'synced', 'message' => 'Recording synced successfully.', 'recording' => $recording];
        } catch (\Throwable $e) {
            $this->setClassRecordingSyncState($classId, 'failed', $e->getMessage());
            MeetingTrackingLog::write('recording_sync_failed', [
                'class_id' => $classId,
                'google_event_id' => $class['google_event_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
            logMeetingHost([
                'event' => 'recording_sync_failed',
                'class_id' => $classId,
                'teacher_id' => (int) ($class['teacher_id'] ?? 0),
                'teacher_google_email' => $class['teacher_google_email'] ?? null,
                'google_event_id' => $class['google_event_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
            $this->logRecordingSyncDebug([
                'message' => 'Recording sync failed',
                'class_id' => $classId,
                'teacher_id' => (int) ($class['teacher_id'] ?? 0),
                'teacher_email' => $class['teacher_google_email'] ?? null,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findOngoingClasses(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query(
            'SELECT *
             FROM class_sessions
             WHERE (status = "ongoing" OR (status IN ("scheduled", "rescheduled") AND COALESCE(start_time_utc, scheduled_time_utc, start_datetime) <= UTC_TIMESTAMP()))
               AND meeting_link IS NOT NULL
               AND TRIM(meeting_link) <> ""
             ORDER BY COALESCE(actual_start_time, teacher_joined_at, start_datetime) ASC'
        );
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Ongoing classes visible to the current user (for automatic Meet status polling).
     *
     * @return list<array<string, mixed>>
     */
    public function findOngoingClassesForUser(int $userId, string $role): array
    {
        $pdo = Database::connection();
        if ($role === 'admin') {
            return $this->findOngoingClasses();
        }

        if ($role === 'teacher') {
            $stmt = $pdo->prepare(
                'SELECT *
                 FROM class_sessions
                 WHERE (status = "ongoing" OR (status IN ("scheduled", "rescheduled") AND COALESCE(start_time_utc, scheduled_time_utc, start_datetime) <= UTC_TIMESTAMP()))
                   AND teacher_id = :teacher_id
                   AND meeting_link IS NOT NULL
                   AND TRIM(meeting_link) <> ""
                 ORDER BY COALESCE(actual_start_time, teacher_joined_at, start_datetime) ASC'
            );
            $stmt->execute(['teacher_id' => $userId]);

            return $stmt->fetchAll() ?: [];
        }

        if ($role === 'student') {
            $stmt = $pdo->prepare(
                'SELECT cs.*
                 FROM class_sessions cs
                 INNER JOIN enrollments e ON e.class_id = cs.id AND e.status = "active"
                 WHERE (cs.status = "ongoing" OR (cs.status IN ("scheduled", "rescheduled") AND COALESCE(cs.start_time_utc, cs.scheduled_time_utc, cs.start_datetime) <= UTC_TIMESTAMP()))
                   AND e.student_id = :student_id
                   AND cs.meeting_link IS NOT NULL
                   AND TRIM(cs.meeting_link) <> ""
                 ORDER BY COALESCE(cs.actual_start_time, cs.teacher_joined_at, cs.start_datetime) ASC'
            );
            $stmt->execute(['student_id' => $userId]);

            return $stmt->fetchAll() ?: [];
        }

        return [];
    }

    /**
     * Poll Google Meet for ongoing classes and mark completed when the host has left.
     * Does not require the teacher to click "End Class".
     *
     * @return array{checked:int,completed:list<int>,still_ongoing:list<int>,errors:array<int,string>}
     */
    public function autoSyncOngoingClasses(?int $userId = null, ?string $role = null): array
    {
        $liveService = new GoogleMeetLiveTrackingService();
        $classes = ($userId !== null && $userId > 0 && $role !== null && $role !== '')
            ? $this->findOngoingClassesForUser($userId, $role)
            : $this->findOngoingClasses();

        $result = [
            'checked' => 0,
            'completed' => [],
            'still_ongoing' => [],
            'errors' => [],
        ];

        foreach ($classes as $class) {
            $classId = (int) ($class['id'] ?? 0);
            if ($classId <= 0) {
                continue;
            }

            $result['checked']++;
            try {
                $sync = $liveService->syncClass($classId, 'auto_poll');
                $refreshed = $this->getClassById($classId) ?? $class;

                if ((string) ($refreshed['status'] ?? '') === 'completed' || ($sync['status'] ?? '') === 'completed') {
                    $result['completed'][] = $classId;
                    continue;
                }

                $liveStatus = (string) ($sync['meeting_live_status'] ?? ($refreshed['meeting_live_status'] ?? ''));
                $hasHostEnd = trim((string) ($sync['actual_end_time'] ?? ($refreshed['actual_end_time'] ?? ''))) !== '';
                $scheduledEndUtc = classEndUtcValue($refreshed);
                $isTimeExpired = false;
                if ($scheduledEndUtc !== null) {
                    $schedEndTs = $this->parseUtcTimestamp($scheduledEndUtc);
                    if ($schedEndTs !== null && time() >= $schedEndTs) {
                        $isTimeExpired = true;
                    }
                }

                if ($liveStatus === 'ended' || $hasHostEnd || $isTimeExpired) {
                    $leaveSync = $liveService->syncClassAfterHostLeave($classId, 'auto_poll');
                    $refreshed = $this->getClassById($classId) ?? $refreshed;

                    if ((string) ($refreshed['status'] ?? '') !== 'completed') {
                        try {
                            $this->completeClass($classId, null, $isTimeExpired ? 'auto_poll_time_expired' : 'auto_poll');
                            $refreshed = $this->getClassById($classId) ?? $refreshed;
                        } catch (\Throwable $ignored) {
                            // Fall through to mark completed if retry fails
                        }
                    }

                    if ((string) ($refreshed['status'] ?? '') === 'completed' || ($leaveSync['status'] ?? '') === 'completed') {
                        $result['completed'][] = $classId;
                        continue;
                    }
                }

                if ((string) ($refreshed['status'] ?? '') === 'ongoing') {
                    $result['still_ongoing'][] = $classId;
                }
            } catch (\Throwable $e) {
                $result['errors'][$classId] = $e->getMessage();
                SyncLog::write('google_meet_status.log', [
                    'event' => 'auto_poll_failed',
                    'class_id' => $classId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($result['completed'] !== []) {
            SyncLog::write('google_meet_status.log', [
                'event' => 'auto_poll_completed_classes',
                'class_ids' => $result['completed'],
                'user_id' => $userId,
                'role' => $role,
            ]);
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findCompletedClassesPendingRecordingSync(int $limit = 25): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT cs.*, cr.id AS class_recording_id, cr.sync_status AS class_recording_sync_status
             FROM class_sessions cs
             LEFT JOIN teacher_google_accounts tga ON tga.teacher_id = cs.teacher_id
             LEFT JOIN class_recordings cr ON cr.class_id = cs.id
             WHERE cs.status = "completed"
               AND cs.recording_enabled = 1
               AND COALESCE(tga.recording_supported, 0) = 1
               AND (
                    cr.id IS NULL
                    OR cr.sync_status IN ("pending", "processing", "failed")
                    OR cs.recording_sync_status IN ("pending", "processing", "failed")
                    OR cs.recording_url IS NULL
                    OR TRIM(cs.recording_url) = ""
               )
             ORDER BY COALESCE(cs.actual_end_time, cs.completed_at, cs.end_datetime) DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return array{checked:int,completed:int,skipped:int,skip_reasons:array<string,int>}
     */
    public function syncOngoingClassStatuses(int $bufferMinutes = 10): array
    {
        $liveService = new GoogleMeetLiveTrackingService();

        return $liveService->syncClassesForLiveWindow(max(12, $bufferMinutes), 6);
    }

    /**
     * @return array{checked:int,synced:int,processing:int,failed:int,disabled:int}
     */
    public function syncPendingRecordings(int $limit = 25): array
    {
        $classes = $this->findCompletedClassesPendingRecordingSync($limit);
        $result = ['checked' => 0, 'synced' => 0, 'processing' => 0, 'failed' => 0, 'disabled' => 0];

        foreach ($classes as $class) {
            $result['checked']++;
            $classId = (int) ($class['id'] ?? 0);
            if ($classId <= 0) {
                $result['failed']++;
                continue;
            }

            try {
                $sync = $this->syncRecordingForClass($classId, true);
                $st = (string) ($sync['status'] ?? '');
                if ($st === 'synced') {
                    $result['synced']++;
                } elseif ($st === 'failed') {
                    $result['failed']++;
                } elseif ($st === 'disabled') {
                    $result['disabled']++;
                } else {
                    $result['processing']++;
                }
                SyncLog::write('recording_sync.log', [
                    'message' => 'Recording sync processed',
                    'class_id' => $classId,
                    'teacher_id' => (int) ($class['teacher_id'] ?? 0),
                    'google_event_id' => $class['google_event_id'] ?? null,
                    'status' => $sync['status'] ?? 'unknown',
                ]);
            } catch (\Throwable $e) {
                $result['failed']++;
                SyncLog::write('recording_sync.log', [
                    'message' => 'Recording sync failed',
                    'class_id' => $classId,
                    'teacher_id' => (int) ($class['teacher_id'] ?? 0),
                    'google_event_id' => $class['google_event_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getClassById(int $classId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM class_sessions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $classId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getClassByGoogleEventId(string $googleEventId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM class_sessions WHERE google_event_id = :event_id LIMIT 1');
        $stmt->execute(['event_id' => $googleEventId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function existingVisibilityForClass(int $classId): string
    {
        $existing = ClassRecording::findByClassId($classId);
        return ($existing['visible_to_student'] ?? 'no') === 'yes' ? 'yes' : 'no';
    }

    public function utcNow(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }

    private function hasLikelyActiveParticipants(int $classId, int $bufferMinutes): bool
    {
        $pdo = Database::connection();
        $threshold = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('-' . max(1, $bufferMinutes) . ' minutes')
            ->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM class_attendance
             WHERE class_id = :class_id
               AND left_at IS NULL
               AND joined_at IS NOT NULL
               AND joined_at >= :recent_threshold'
        );
        $stmt->execute([
            'class_id' => $classId,
            'recent_threshold' => $threshold,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function incrementSkipReason(array &$result, string $reason): void
    {
        $result['skipped']++;
        if (!isset($result['skip_reasons'][$reason])) {
            $result['skip_reasons'][$reason] = 0;
        }
        $result['skip_reasons'][$reason]++;
    }

    /**
     * @param array<string, mixed> $class
     */
    private function resolveActualClassStartUtc(array $class): ?string
    {
        foreach (['actual_start_time', 'teacher_joined_at'] as $key) {
            $value = $this->normalizeUtcValue((string) ($class[$key] ?? ''));
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $class
     */
    private function resolveClassEndUtc(array $class): ?string
    {
        foreach (['end_time_utc', 'end_datetime'] as $key) {
            $value = $this->normalizeUtcValue((string) ($class[$key] ?? ''));
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $class
     */
    private function resolveRecordingCompletionReferenceUtc(array $class): ?string
    {
        foreach (['actual_end_time', 'completed_at', 'end_time_utc', 'end_datetime'] as $key) {
            $value = $this->normalizeUtcValue((string) ($class[$key] ?? ''));
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function normalizeUtcValue(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            try {
                return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            } catch (\Throwable $inner) {
                return null;
            }
        }
    }

    private function parseUtcTimestamp(?string $value): ?int
    {
        $normalized = $this->normalizeUtcValue((string) $value);
        if ($normalized === null) {
            return null;
        }

        return strtotime($normalized . ' UTC') ?: null;
    }

    /**
     * @param array<string, mixed> $class
     */
    private function isRecordingSyncEligible(array $class): bool
    {
        return (int) ($class['recording_enabled'] ?? 0) === 1;
    }

    /**
     * @param array<string, mixed> $class
     */
    private function hasRecordingSyncTimedOut(array $class, int $graceMinutes = 60): bool
    {
        $completedAt = $this->resolveRecordingCompletionReferenceUtc($class);
        $completedTs = $this->parseUtcTimestamp($completedAt);
        if ($completedTs === null) {
            return false;
        }

        return time() > ($completedTs + (max(1, $graceMinutes) * 60));
    }

    private function setClassRecordingSyncState(int $classId, string $status, ?string $error = null, ?string $recordingUrl = null): void
    {
        $pdo = Database::connection();
        $sql = 'UPDATE class_sessions
                SET recording_sync_status = :status,
                    recording_sync_error = :error';
        $params = [
            'status' => $status,
            'error' => $error,
            'id' => $classId,
        ];

        if ($recordingUrl !== null) {
            $sql .= ', recording_url = :recording_url';
            $params['recording_url'] = $recordingUrl;
        }

        if ($status === 'ready') {
            $sql .= ', recording_synced_at = UTC_TIMESTAMP()';
        }

        $sql .= ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logMeetingCompletionDebug(array $context): void
    {
        SyncLog::write('meeting_completion_debug.log', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logRecordingSyncDebug(array $context): void
    {
        SyncLog::write('recording_sync_debug.log', $context);
    }
}

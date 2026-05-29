<?php

declare(strict_types=1);

use Google\Service\Meet as GoogleMeet;
use Google\Service\PeopleService as GooglePeopleService;

require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/GoogleOAuthService.php';
require_once dirname(__DIR__) . '/lib/MeetingTrackingLog.php';
require_once dirname(__DIR__) . '/lib/SyncLog.php';
require_once dirname(__DIR__) . '/models/ClassAttendance.php';
require_once dirname(__DIR__) . '/models/MeetingActivityLog.php';
require_once dirname(__DIR__) . '/models/TeacherGoogleAccount.php';
require_once dirname(__DIR__) . '/models/TeacherPayout.php';

class GoogleMeetLiveTrackingService
{
    /**
     * @return array<string, mixed>|null
     */
    public function describeSpaceForMeetingLink(int $teacherId, string $meetingLink): ?array
    {
        $meetingCode = $this->extractMeetingCodeFromLink($meetingLink);
        if ($meetingCode === null) {
            return null;
        }

        return $this->resolveSpace($teacherId, ['google_meet_space_name' => null], $meetingCode);
    }

    /**
     * @return array{checked:int,started:int,completed:int,unchanged:int,failed:int,skipped:int,skip_reasons:array<string,int>}
     */
    public function syncClassesForLiveWindow(int $lookbackHours = 12, int $lookaheadHours = 6): array
    {
        $classes = $this->findClassesForLiveSync($lookbackHours, $lookaheadHours);
        $result = [
            'checked' => 0,
            'started' => 0,
            'completed' => 0,
            'unchanged' => 0,
            'failed' => 0,
            'skipped' => 0,
            'skip_reasons' => [],
        ];

        foreach ($classes as $class) {
            $result['checked']++;
            $classId = (int) ($class['id'] ?? 0);
            if ($classId <= 0) {
                $this->incrementSkipReason($result, 'invalid_class_id');
                continue;
            }

            try {
                $sync = $this->syncClass($classId, 'meet_poll');
                $status = (string) ($sync['status'] ?? 'skipped');
                if ($status === 'started') {
                    $result['started']++;
                } elseif ($status === 'completed') {
                    $result['completed']++;
                } elseif ($status === 'unchanged') {
                    $result['unchanged']++;
                } elseif ($status === 'failed') {
                    $result['failed']++;
                } else {
                    $this->incrementSkipReason($result, (string) ($sync['reason'] ?? 'skipped'));
                }
            } catch (\Throwable $e) {
                $result['failed']++;
                $this->logLiveTracking([
                    'message' => 'Live sync crashed for class',
                    'class_id' => $classId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function syncClass(int $classId, string $trigger = 'meet_poll'): array
    {
        $class = $this->getClassById($classId);
        if ($class === null) {
            return ['status' => 'skipped', 'reason' => 'class_not_found'];
        }

        $teacherId = (int) ($class['teacher_id'] ?? 0);
        if ($teacherId <= 0) {
            return ['status' => 'skipped', 'reason' => 'missing_teacher_id'];
        }

        $meetingLink = trim((string) ($class['meeting_link'] ?? ''));
        $meetingCode = $this->extractMeetingCodeFromClass($class);
        if ($meetingCode === null && $meetingLink === '') {
            return ['status' => 'skipped', 'reason' => 'missing_google_meet_link'];
        }
        if ($meetingCode === null) {
            return ['status' => 'skipped', 'reason' => 'unsupported_meeting_link'];
        }

        try {
            $teacherIdentity = $this->ensureTeacherIdentity($teacherId);
            $space = $this->resolveSpace($teacherId, $class, $meetingCode);
            if ($space === null) {
                return ['status' => 'skipped', 'reason' => 'space_not_found'];
            }

            $conference = $this->resolveConferenceRecord($teacherId, $class, $space);
            if ($conference === null) {
                $this->persistMeetMetadata($classId, $space, null, 'pending', null);
                return ['status' => 'skipped', 'reason' => 'conference_not_found'];
            }

            $participants = $this->listParticipantsWithSessions($teacherId, (string) $conference['name']);
            $teacherParticipant = $this->findTeacherParticipant($participants, $teacherIdentity);
            $teacherSessions = $teacherParticipant !== null ? (array) ($teacherParticipant['sessions'] ?? []) : [];
            $teacherSummary = $this->summarizeSessions($teacherSessions);

            $studentEarliestStart = $this->earliestNonTeacherJoinUtc($participants, $teacherParticipant['name'] ?? null);
            $participantCount = count($participants);
            $timingSource = 'host_participant_session';
            $actualStart = $teacherSummary['actual_start_time'];
            $actualEnd = $teacherSummary['actual_end_time'];
            $hasActiveTeacherSession = (bool) $teacherSummary['has_active_session'];

            if ($actualStart === null) {
                $actualStart = $this->normalizeUtcValue((string) ($conference['start_time'] ?? ''));
                if ($actualStart !== null) {
                    $timingSource = 'conference_record_inferred';
                }
            }
            if ($actualEnd === null && !$hasActiveTeacherSession) {
                $actualEnd = $this->normalizeUtcValue((string) ($conference['end_time'] ?? ''));
                if ($actualEnd !== null && $timingSource !== 'host_participant_session') {
                    $timingSource = 'conference_record_inferred';
                }
            }

            $liveStatus = 'pending';
            if ($hasActiveTeacherSession || ($teacherParticipant === null && empty($conference['end_time']) && !empty($conference['start_time']))) {
                $liveStatus = $teacherParticipant === null ? 'sync_error' : 'active';
            } elseif ($actualEnd !== null) {
                $liveStatus = 'ended';
            }

            $persisted = $this->applyLiveSnapshot(
                $class,
                $space,
                $conference,
                $actualStart,
                $actualEnd,
                $teacherSummary['authoritative_start_time'],
                $teacherSummary['authoritative_end_time'],
                $studentEarliestStart,
                $liveStatus,
                $participantCount,
                $trigger,
                $timingSource
            );

            $this->syncParticipantLogs($class, $participants, $teacherIdentity);

            if ($teacherId > 0 && $teacherSummary['authoritative_start_time'] !== null) {
                ClassAttendance::syncActivity(
                    $classId,
                    $teacherId,
                    'teacher',
                    $teacherSummary['authoritative_start_time'],
                    $teacherSummary['authoritative_end_time']
                );
            }

            $status = $persisted['status'] ?? 'unchanged';
            return [
                'status' => $status,
                'class_id' => $classId,
                'meeting_live_status' => $persisted['meeting_live_status'] ?? $liveStatus,
                'actual_start_time' => $persisted['actual_start_time'] ?? $actualStart,
                'actual_end_time' => $persisted['actual_end_time'] ?? $actualEnd,
                'participant_count' => $participantCount,
                'conference_id' => $conference['name'] ?? null,
                'timing_source' => $timingSource,
            ];
        } catch (\Throwable $e) {
            $message = $this->humanizeGoogleApiError($e);
            $this->markSyncError($class, $message);
            $this->logLiveTracking([
                'message' => 'Meet live sync failed',
                'class_id' => $classId,
                'teacher_id' => $teacherId,
                'google_event_id' => $class['google_event_id'] ?? null,
                'error' => $e->getMessage(),
                'display_error' => $message,
                'trigger' => $trigger,
            ]);

            return ['status' => 'failed', 'reason' => 'google_meet_api_error', 'error' => $message];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function syncClassByConferenceRecord(string $conferenceRecordName, string $trigger = 'workspace_event'): ?array
    {
        $conferenceRecordName = trim($conferenceRecordName);
        if ($conferenceRecordName === '') {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id
             FROM class_sessions
             WHERE google_conference_id = :conference_id
             ORDER BY updated_at DESC
             LIMIT 1'
        );
        $stmt->execute(['conference_id' => $conferenceRecordName]);
        $classId = (int) ($stmt->fetchColumn() ?: 0);
        if ($classId <= 0) {
            return null;
        }

        return $this->syncClass($classId, $trigger);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function syncClassBySpaceName(string $spaceName, string $trigger = 'workspace_event'): ?array
    {
        $spaceName = trim($spaceName);
        if ($spaceName === '') {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT *
             FROM class_sessions
             WHERE google_meet_space_name = :space_name
             ORDER BY
                CASE
                    WHEN status = "ongoing" THEN 0
                    WHEN status IN ("scheduled", "rescheduled") THEN 1
                    ELSE 2
                END,
                ABS(TIMESTAMPDIFF(SECOND, start_datetime, UTC_TIMESTAMP())) ASC,
                id DESC
             LIMIT 1'
        );
        $stmt->execute(['space_name' => $spaceName]);
        $class = $stmt->fetch();
        if ($class === false || !isset($class['id'])) {
            return null;
        }

        return $this->syncClass((int) $class['id'], $trigger);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findClassesForLiveSync(int $lookbackHours, int $lookaheadHours): array
    {
        $lookbackHours = max(1, $lookbackHours);
        $lookaheadHours = max(1, $lookaheadHours);
        $windowStart = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('-' . $lookbackHours . ' hours')
            ->format('Y-m-d H:i:s');
        $windowEnd = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('+' . $lookaheadHours . ' hours')
            ->format('Y-m-d H:i:s');

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT *
             FROM class_sessions
             WHERE start_datetime BETWEEN :window_start AND :window_end
               AND status IN ("scheduled", "rescheduled", "ongoing")
               AND meeting_link IS NOT NULL
               AND TRIM(meeting_link) <> ""
             ORDER BY start_datetime ASC, id ASC'
        );
        $stmt->execute([
            'window_start' => $windowStart,
            'window_end' => $windowEnd,
        ]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getClassById(int $classId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM class_sessions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $classId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array{google_person_resource_name:?string,google_person_id:?string,google_email:?string}
     */
    private function ensureTeacherIdentity(int $teacherId): array
    {
        $account = TeacherGoogleAccount::getCredentialsForTeacher($teacherId);
        if ($account === null) {
            throw new RuntimeException('Teacher Google account is not connected.');
        }

        $existingPersonId = trim((string) ($account['google_person_id'] ?? ''));
        $existingResource = trim((string) ($account['google_person_resource_name'] ?? ''));
        if ($existingPersonId !== '') {
            return [
                'google_person_resource_name' => $existingResource !== '' ? $existingResource : ('people/' . $existingPersonId),
                'google_person_id' => $existingPersonId,
                'google_email' => $account['google_email'] ?? null,
            ];
        }

        $oauth = new GoogleOAuthService();
        $client = $oauth->client();
        $client->setAccessToken($oauth->getActiveAccessTokenForTeacher($teacherId));
        $people = new GooglePeopleService($client);
        $person = $people->people->get('people/me', [
            'personFields' => 'emailAddresses',
        ]);

        $resourceName = trim((string) $person->getResourceName());
        $personId = $this->personIdFromResourceName($resourceName);
        if ($personId === null) {
            throw new RuntimeException('Unable to resolve the teacher Google identity for Meet tracking.');
        }

        TeacherGoogleAccount::updateIdentity($teacherId, $resourceName, $personId);

        return [
            'google_person_resource_name' => $resourceName,
            'google_person_id' => $personId,
            'google_email' => $account['google_email'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $class
     * @return array<string, mixed>|null
     */
    private function resolveSpace(int $teacherId, array $class, string $meetingCode): ?array
    {
        $spaceRef = trim((string) ($class['google_meet_space_name'] ?? ''));
        $meet = $this->meetServiceForTeacher($teacherId);

        $space = null;
        try {
            if ($spaceRef !== '') {
                $space = $meet->spaces->get($spaceRef);
            } else {
                $space = $meet->spaces->get('spaces/' . $meetingCode);
            }
        } catch (\Throwable $e) {
            if ($spaceRef !== '') {
                $space = $meet->spaces->get('spaces/' . $meetingCode);
            } else {
                throw $e;
            }
        }

        if ($space === null) {
            return null;
        }

        $activeConference = $space->getActiveConference();
        return [
            'name' => trim((string) $space->getName()),
            'meeting_code' => trim((string) $space->getMeetingCode()),
            'meeting_uri' => trim((string) $space->getMeetingUri()),
            'active_conference_record' => $activeConference !== null ? trim((string) $activeConference->getConferenceRecord()) : null,
        ];
    }

    /**
     * @param array<string, mixed> $class
     * @param array<string, mixed> $space
     * @return array<string, mixed>|null
     */
    private function resolveConferenceRecord(int $teacherId, array $class, array $space): ?array
    {
        $meet = $this->meetServiceForTeacher($teacherId);
        $storedConferenceId = trim((string) ($class['google_conference_id'] ?? ''));
        if ($storedConferenceId !== '') {
            try {
                $record = $meet->conferenceRecords->get($storedConferenceId);
                return $this->conferenceRecordToArray($record);
            } catch (\Throwable $ignored) {
                // Fall through to live lookup.
            }
        }

        $activeConferenceRecord = trim((string) ($space['active_conference_record'] ?? ''));
        if ($activeConferenceRecord !== '') {
            try {
                $record = $meet->conferenceRecords->get($activeConferenceRecord);
                return $this->conferenceRecordToArray($record);
            } catch (\Throwable $ignored) {
                // Fall through to list lookup.
            }
        }

        $spaceName = trim((string) ($space['name'] ?? ''));
        if ($spaceName === '') {
            return null;
        }

        $scheduledStartUtc = $this->normalizeUtcValue((string) ($class['start_time_utc'] ?? $class['start_datetime'] ?? ''));
        $scheduledEndUtc = $this->normalizeUtcValue((string) ($class['end_time_utc'] ?? $class['end_datetime'] ?? ''));
        $scheduledStartTs = $this->parseUtcTimestamp($scheduledStartUtc);

        $windowStartIso = $this->iso8601UtcFromTimestamp(($scheduledStartTs ?? time()) - (12 * 3600));
        $windowEndIso = $this->iso8601UtcFromTimestamp(
            ($this->parseUtcTimestamp($scheduledEndUtc) ?? ($scheduledStartTs ?? time())) + (12 * 3600)
        );

        $response = $meet->conferenceRecords->listConferenceRecords([
            'filter' => sprintf(
                'space.name = "%s" AND start_time>="%s" AND start_time<="%s"',
                addcslashes($spaceName, '"\\'),
                $windowStartIso,
                $windowEndIso
            ),
            'pageSize' => 25,
        ]);

        $records = [];
        foreach ($response->getConferenceRecords() ?? [] as $record) {
            $records[] = $this->conferenceRecordToArray($record);
        }

        if ($records === []) {
            return null;
        }

        usort($records, function (array $a, array $b) use ($scheduledStartTs): int {
            $aEnd = empty($a['end_time']) ? 0 : 1;
            $bEnd = empty($b['end_time']) ? 0 : 1;
            if ($aEnd !== $bEnd) {
                return $aEnd <=> $bEnd;
            }

            $aTs = $this->parseUtcTimestamp((string) ($a['start_time'] ?? '')) ?? 0;
            $bTs = $this->parseUtcTimestamp((string) ($b['start_time'] ?? '')) ?? 0;
            if ($scheduledStartTs === null) {
                return $bTs <=> $aTs;
            }

            $aDelta = abs($aTs - $scheduledStartTs);
            $bDelta = abs($bTs - $scheduledStartTs);
            if ($aDelta === $bDelta) {
                return $bTs <=> $aTs;
            }

            return $aDelta <=> $bDelta;
        });

        return $records[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listParticipantsWithSessions(int $teacherId, string $conferenceRecordName): array
    {
        $meet = $this->meetServiceForTeacher($teacherId);
        $participants = [];
        $pageToken = null;

        do {
            $optParams = ['pageSize' => 250];
            if ($pageToken !== null && $pageToken !== '') {
                $optParams['pageToken'] = $pageToken;
            }
            $response = $meet->conferenceRecords_participants->listConferenceRecordsParticipants($conferenceRecordName, $optParams);
            foreach ($response->getParticipants() ?? [] as $participant) {
                $participantArray = $this->participantToArray($participant);
                $participantArray['sessions'] = $this->listParticipantSessions($teacherId, (string) $participantArray['name']);
                $participants[] = $participantArray;
            }
            $pageToken = $response->getNextPageToken();
        } while (is_string($pageToken) && $pageToken !== '');

        return $participants;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listParticipantSessions(int $teacherId, string $participantName): array
    {
        $meet = $this->meetServiceForTeacher($teacherId);
        $sessions = [];
        $pageToken = null;

        do {
            $optParams = ['pageSize' => 250];
            if ($pageToken !== null && $pageToken !== '') {
                $optParams['pageToken'] = $pageToken;
            }
            $response = $meet->conferenceRecords_participants_participantSessions
                ->listConferenceRecordsParticipantsParticipantSessions($participantName, $optParams);
            foreach ($response->getParticipantSessions() ?? [] as $session) {
                $sessions[] = $this->participantSessionToArray($session);
            }
            $pageToken = $response->getNextPageToken();
        } while (is_string($pageToken) && $pageToken !== '');

        usort($sessions, fn (array $a, array $b): int => strcmp((string) ($a['start_time'] ?? ''), (string) ($b['start_time'] ?? '')));

        return $sessions;
    }

    /**
     * @param list<array<string, mixed>> $participants
     * @param array{google_person_resource_name:?string,google_person_id:?string,google_email:?string} $teacherIdentity
     * @return array<string, mixed>|null
     */
    private function findTeacherParticipant(array $participants, array $teacherIdentity): ?array
    {
        $teacherPersonId = trim((string) ($teacherIdentity['google_person_id'] ?? ''));
        if ($teacherPersonId === '') {
            return null;
        }

        $teacherUserResource = 'users/' . $teacherPersonId;
        foreach ($participants as $participant) {
            $signedInUser = trim((string) ($participant['signed_in_user'] ?? ''));
            $participantId = $this->participantIdFromResourceName((string) ($participant['name'] ?? ''));
            if ($signedInUser === $teacherUserResource || $participantId === $teacherPersonId) {
                return $participant;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $sessions
     * @return array{actual_start_time:?string,actual_end_time:?string,authoritative_start_time:?string,authoritative_end_time:?string,has_active_session:bool}
     */
    private function summarizeSessions(array $sessions): array
    {
        $actualStart = null;
        $actualEnd = null;
        $hasActiveSession = false;

        foreach ($sessions as $session) {
            $start = $this->normalizeUtcValue((string) ($session['start_time'] ?? ''));
            $end = $this->normalizeUtcValue((string) ($session['end_time'] ?? ''));
            if ($start !== null && ($actualStart === null || strcmp($start, $actualStart) < 0)) {
                $actualStart = $start;
            }
            if ($end === null) {
                $hasActiveSession = true;
            } elseif ($actualEnd === null || strcmp($end, $actualEnd) > 0) {
                $actualEnd = $end;
            }
        }

        return [
            'actual_start_time' => $actualStart,
            'actual_end_time' => $hasActiveSession ? null : $actualEnd,
            'authoritative_start_time' => $actualStart,
            'authoritative_end_time' => $hasActiveSession ? null : $actualEnd,
            'has_active_session' => $hasActiveSession,
        ];
    }

    /**
     * @param list<array<string, mixed>> $participants
     */
    private function earliestNonTeacherJoinUtc(array $participants, ?string $teacherParticipantName): ?string
    {
        $earliest = null;
        foreach ($participants as $participant) {
            if ($teacherParticipantName !== null && (string) ($participant['name'] ?? '') === $teacherParticipantName) {
                continue;
            }
            foreach ((array) ($participant['sessions'] ?? []) as $session) {
                $start = $this->normalizeUtcValue((string) ($session['start_time'] ?? ''));
                if ($start !== null && ($earliest === null || strcmp($start, $earliest) < 0)) {
                    $earliest = $start;
                }
            }
        }

        return $earliest;
    }

    /**
     * @param array<string, mixed> $class
     * @param array<string, mixed> $space
     * @param array<string, mixed> $conference
     * @return array<string, mixed>
     */
    private function applyLiveSnapshot(
        array $class,
        array $space,
        array $conference,
        ?string $actualStart,
        ?string $actualEnd,
        ?string $teacherActualStart,
        ?string $teacherActualEnd,
        ?string $studentEarliestStart,
        string $liveStatus,
        int $participantCount,
        string $trigger,
        string $timingSource
    ): array {
        $classId = (int) ($class['id'] ?? 0);
        $existingStart = $this->normalizeUtcValue((string) ($class['actual_start_time'] ?? ''));
        $existingTeacherJoin = $this->normalizeUtcValue((string) ($class['teacher_joined_at'] ?? ''));
        $existingEnd = $this->normalizeUtcValue((string) ($class['actual_end_time'] ?? ''));
        $existingCompletedAt = $this->normalizeUtcValue((string) ($class['completed_at'] ?? ''));
        $existingStudentJoin = $this->normalizeUtcValue((string) ($class['student_joined_at'] ?? ''));

        $mergedStart = $this->earliestUtc($existingStart, $actualStart);
        $mergedTeacherJoin = $this->earliestUtc($existingTeacherJoin, $teacherActualStart);
        $mergedStudentJoin = $this->earliestUtc($existingStudentJoin, $studentEarliestStart);
        $mergedEnd = $this->latestUtc($existingEnd, $actualEnd);
        $mergedCompletedAt = $mergedEnd !== null ? $this->latestUtc($existingCompletedAt, $mergedEnd) : $existingCompletedAt;
        $durationMinutes = $this->durationMinutesBetween($mergedStart, $mergedEnd);

        $previousStatus = (string) ($class['status'] ?? 'scheduled');
        $status = $previousStatus;
        if ($mergedEnd !== null && $liveStatus === 'ended') {
            $status = 'completed';
        } elseif ($mergedStart !== null && in_array($liveStatus, ['active', 'sync_error'], true)) {
            $status = 'ongoing';
        }

        $recordingStatus = (string) ($class['recording_sync_status'] ?? 'pending');
        $recordingError = $class['recording_sync_error'] ?? null;
        if ($status === 'completed') {
            $recordingStatus = $this->resolveRecordingStatusForCompletedClass($class);
            if ($recordingStatus !== 'failed') {
                $recordingError = null;
            }
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE class_sessions
             SET google_meet_space_name = :google_meet_space_name,
                 google_meeting_code = :google_meeting_code,
                 google_conference_id = :google_conference_id,
                 meeting_live_status = :meeting_live_status,
                 meeting_participant_count = :meeting_participant_count,
                 teacher_joined_at = :teacher_joined_at,
                 student_joined_at = :student_joined_at,
                 actual_start_time = :actual_start_time,
                 actual_end_time = :actual_end_time,
                 actual_duration = :actual_duration,
                 actual_duration_minutes = :actual_duration_minutes,
                 completed_at = :completed_at,
                 status = :status,
                 recording_sync_status = :recording_sync_status,
                 recording_sync_error = :recording_sync_error
             WHERE id = :id'
        );
        $stmt->execute([
            'google_meet_space_name' => $space['name'] ?? null,
            'google_meeting_code' => $space['meeting_code'] ?? null,
            'google_conference_id' => $conference['name'] ?? null,
            'meeting_live_status' => in_array($liveStatus, ['pending', 'active', 'ended', 'sync_error'], true) ? $liveStatus : 'pending',
            'meeting_participant_count' => $participantCount > 0 ? $participantCount : null,
            'teacher_joined_at' => $mergedTeacherJoin,
            'student_joined_at' => $mergedStudentJoin,
            'actual_start_time' => $mergedStart,
            'actual_end_time' => $mergedEnd,
            'actual_duration' => $durationMinutes,
            'actual_duration_minutes' => $durationMinutes,
            'completed_at' => $mergedCompletedAt,
            'status' => $status,
            'recording_sync_status' => $recordingStatus,
            'recording_sync_error' => $recordingError,
            'id' => $classId,
        ]);

        if ($status === 'completed') {
            TeacherPayout::ensureForCompletedClass($classId);
        }

        $resultStatus = 'unchanged';
        if ($previousStatus !== 'ongoing' && $status === 'ongoing') {
            $resultStatus = 'started';
        } elseif ($previousStatus !== 'completed' && $status === 'completed') {
            $resultStatus = 'completed';
        }

        $this->logLiveTracking([
            'message' => 'Meet live snapshot applied',
            'class_id' => $classId,
            'teacher_id' => (int) ($class['teacher_id'] ?? 0),
            'google_event_id' => $class['google_event_id'] ?? null,
            'google_conference_id' => $conference['name'] ?? null,
            'google_meet_space_name' => $space['name'] ?? null,
            'meeting_live_status' => $liveStatus,
            'participant_count' => $participantCount,
            'actual_start_time' => $mergedStart,
            'actual_end_time' => $mergedEnd,
            'actual_duration_minutes' => $durationMinutes,
            'status' => $status,
            'trigger' => $trigger,
            'timing_source' => $timingSource,
        ]);
        MeetingTrackingLog::write('google_meet_live_sync', [
            'class_id' => $classId,
            'trigger' => $trigger,
            'status' => $status,
            'meeting_live_status' => $liveStatus,
            'timing_source' => $timingSource,
            'google_conference_id' => $conference['name'] ?? null,
            'actual_start_time' => $mergedStart,
            'actual_end_time' => $mergedEnd,
            'actual_duration_minutes' => $durationMinutes,
        ]);

        return [
            'status' => $resultStatus,
            'meeting_live_status' => $liveStatus,
            'actual_start_time' => $mergedStart,
            'actual_end_time' => $mergedEnd,
            'actual_duration_minutes' => $durationMinutes,
        ];
    }

    /**
     * @param array<string, mixed> $class
     * @param list<array<string, mixed>> $participants
     * @param array{google_person_resource_name:?string,google_person_id:?string,google_email:?string} $teacherIdentity
     */
    private function syncParticipantLogs(array $class, array $participants, array $teacherIdentity): void
    {
        $classId = (int) ($class['id'] ?? 0);
        $teacherUserId = (int) ($class['teacher_id'] ?? 0);
        $teacherPersonId = trim((string) ($teacherIdentity['google_person_id'] ?? ''));
        $teacherUserResource = $teacherPersonId !== '' ? 'users/' . $teacherPersonId : '';

        foreach ($participants as $participant) {
            $participantName = (string) ($participant['name'] ?? '');
            $participantId = $this->participantIdFromResourceName($participantName);
            $signedInUser = trim((string) ($participant['signed_in_user'] ?? ''));
            $role = 'student';
            $userId = null;

            if (($teacherUserResource !== '' && $signedInUser === $teacherUserResource) || ($teacherPersonId !== '' && $participantId === $teacherPersonId)) {
                $role = 'teacher';
                $userId = $teacherUserId > 0 ? $teacherUserId : null;
            } elseif (empty($participant['signed_in_user']) && empty($participant['display_name'])) {
                $role = 'unknown';
            }

            foreach ((array) ($participant['sessions'] ?? []) as $session) {
                $joinedAt = $this->normalizeUtcValue((string) ($session['start_time'] ?? ''));
                if ($joinedAt === null) {
                    continue;
                }
                $leftAt = $this->normalizeUtcValue((string) ($session['end_time'] ?? ''));
                MeetingActivityLog::upsertSession(
                    $classId,
                    $userId,
                    $role,
                    $joinedAt,
                    $leftAt,
                    $participantName,
                    (string) ($session['name'] ?? ''),
                    'google_meet_api'
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $class
     */
    private function markSyncError(array $class, string $message): void
    {
        $classId = (int) ($class['id'] ?? 0);
        if ($classId <= 0) {
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE class_sessions
             SET meeting_live_status = "sync_error"
             WHERE id = :id'
        );
        $stmt->execute(['id' => $classId]);

        MeetingTrackingLog::write('google_meet_live_sync_error', [
            'class_id' => $classId,
            'message' => $message,
        ]);
    }

    /**
     * @param array<string, mixed> $class
     * @param array<string, mixed> $space
     */
    private function persistMeetMetadata(
        int $classId,
        array $space,
        ?string $conferenceRecordName,
        string $liveStatus,
        ?int $participantCount
    ): void {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE class_sessions
             SET google_meet_space_name = :google_meet_space_name,
                 google_meeting_code = :google_meeting_code,
                 google_conference_id = :google_conference_id,
                 meeting_live_status = :meeting_live_status,
                 meeting_participant_count = :meeting_participant_count
             WHERE id = :id'
        );
        $stmt->execute([
            'google_meet_space_name' => $space['name'] ?? null,
            'google_meeting_code' => $space['meeting_code'] ?? null,
            'google_conference_id' => $conferenceRecordName,
            'meeting_live_status' => $liveStatus,
            'meeting_participant_count' => $participantCount,
            'id' => $classId,
        ]);
    }

    private function resolveRecordingStatusForCompletedClass(array $class): string
    {
        if ((int) ($class['recording_enabled'] ?? 0) !== 1) {
            return 'pending';
        }

        $teacherId = (int) ($class['teacher_id'] ?? 0);
        if ($teacherId <= 0) {
            return 'pending';
        }

        $teacherRow = TeacherGoogleAccount::findByTeacherId($teacherId);
        if (!TeacherGoogleAccount::recordingSupportedFromAccountRow($teacherRow)) {
            return 'pending';
        }

        return trim((string) ($class['recording_url'] ?? '')) === '' ? 'processing' : 'ready';
    }

    private function meetServiceForTeacher(int $teacherId): GoogleMeet
    {
        $oauth = new GoogleOAuthService();
        $client = $oauth->client();
        $client->setAccessToken($oauth->getActiveAccessTokenForTeacher($teacherId));

        return new GoogleMeet($client);
    }

    /**
     * @return array<string, mixed>
     */
    private function conferenceRecordToArray($record): array
    {
        return [
            'name' => trim((string) $record->getName()),
            'start_time' => $this->normalizeUtcValue((string) $record->getStartTime()),
            'end_time' => $this->normalizeUtcValue((string) $record->getEndTime()),
            'space' => trim((string) $record->getSpace()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function participantToArray($participant): array
    {
        $signedInUser = $participant->getSignedinUser();
        return [
            'name' => trim((string) $participant->getName()),
            'display_name' => $signedInUser !== null ? trim((string) $signedInUser->getDisplayName()) : '',
            'signed_in_user' => $signedInUser !== null ? trim((string) $signedInUser->getUser()) : '',
            'earliest_start_time' => $this->normalizeUtcValue((string) $participant->getEarliestStartTime()),
            'latest_end_time' => $this->normalizeUtcValue((string) $participant->getLatestEndTime()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function participantSessionToArray($session): array
    {
        return [
            'name' => trim((string) $session->getName()),
            'start_time' => $this->normalizeUtcValue((string) $session->getStartTime()),
            'end_time' => $this->normalizeUtcValue((string) $session->getEndTime()),
        ];
    }

    /**
     * @param array<string, mixed> $class
     */
    private function extractMeetingCodeFromClass(array $class): ?string
    {
        $stored = trim((string) ($class['google_meeting_code'] ?? ''));
        if ($stored !== '') {
            return strtolower($stored);
        }

        return $this->extractMeetingCodeFromLink((string) ($class['meeting_link'] ?? ''));
    }

    private function extractMeetingCodeFromLink(string $meetingLink): ?string
    {
        $meetingLink = trim($meetingLink);
        if ($meetingLink === '') {
            return null;
        }

        if (preg_match('~meet\.google\.com/([a-z]{3}-[a-z]{4}-[a-z]{3})~i', $meetingLink, $matches) === 1) {
            return strtolower($matches[1]);
        }

        return null;
    }

    private function participantIdFromResourceName(string $participantName): ?string
    {
        $participantName = trim($participantName);
        if ($participantName === '') {
            return null;
        }

        $parts = explode('/', $participantName);
        $tail = end($parts);

        return is_string($tail) && $tail !== '' ? $tail : null;
    }

    private function personIdFromResourceName(string $resourceName): ?string
    {
        $resourceName = trim($resourceName);
        if ($resourceName === '' || !str_starts_with($resourceName, 'people/')) {
            return null;
        }

        return substr($resourceName, strlen('people/')) ?: null;
    }

    private function earliestUtc(?string $first, ?string $second): ?string
    {
        if ($first === null || $first === '') {
            return $second;
        }
        if ($second === null || $second === '') {
            return $first;
        }

        return strcmp($first, $second) <= 0 ? $first : $second;
    }

    private function latestUtc(?string $first, ?string $second): ?string
    {
        if ($first === null || $first === '') {
            return $second;
        }
        if ($second === null || $second === '') {
            return $first;
        }

        return strcmp($first, $second) >= 0 ? $first : $second;
    }

    private function durationMinutesBetween(?string $startUtc, ?string $endUtc): ?int
    {
        $startTs = $this->parseUtcTimestamp($startUtc);
        $endTs = $this->parseUtcTimestamp($endUtc);
        if ($startTs === null || $endTs === null || $endTs < $startTs) {
            return null;
        }

        return max(0, (int) floor(($endTs - $startTs) / 60));
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

        $timestamp = strtotime($normalized . ' UTC');

        return $timestamp === false ? null : $timestamp;
    }

    private function iso8601UtcFromTimestamp(int $timestamp): string
    {
        return gmdate('Y-m-d\TH:i:s.000\Z', $timestamp);
    }

    private function humanizeGoogleApiError(\Throwable $e): string
    {
        $message = trim($e->getMessage());
        $lower = strtolower($message);

        if (
            str_contains($lower, 'insufficient authentication scopes')
            || str_contains($lower, 'insufficientpermission')
            || str_contains($lower, 'insufficient permission')
            || str_contains($lower, 'access_token_scope_insufficient')
        ) {
            return 'Reconnect the teacher Google account to grant Google Meet live tracking permissions.';
        }
        if (str_contains($lower, 'service_disabled') || str_contains($lower, 'accessnotconfigured')) {
            return 'Enable the required Google Meet or People API services in the Google Cloud project for live tracking.';
        }
        if (str_contains($lower, 'forbidden')) {
            return 'Google denied access to Meet live activity for this teacher account.';
        }

        return $message !== '' ? $message : 'Google Meet live tracking failed.';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logLiveTracking(array $context): void
    {
        SyncLog::write('google_meet_live_tracking.log', $context);
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
}

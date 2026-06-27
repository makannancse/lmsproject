<?php

declare(strict_types=1);

use Google\Service\Meet as GoogleMeet;
use Google\Service\Oauth2 as GoogleOauth2;
use Google\Service\PeopleService as GooglePeopleService;

require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/GoogleOAuthService.php';
require_once dirname(__DIR__) . '/lib/MeetingTrackingLog.php';
require_once dirname(__DIR__) . '/lib/SyncLog.php';
require_once dirname(__DIR__) . '/models/ClassAttendance.php';
require_once dirname(__DIR__) . '/models/MeetingActivityLog.php';
require_once dirname(__DIR__) . '/models/TeacherGoogleAccount.php';
require_once dirname(__DIR__) . '/models/TeacherPayout.php';
require_once dirname(__DIR__) . '/lib/MeetingSyncDebugService.php';
require_once dirname(__DIR__) . '/lib/helpers.php';

class GoogleMeetLiveTrackingService
{
    private ?MeetingSyncDebugService $debugService = null;

    private function debug(): MeetingSyncDebugService
    {
        if ($this->debugService === null) {
            $this->debugService = new MeetingSyncDebugService();
        }

        return $this->debugService;
    }

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

                if ($status !== 'completed') {
                    $status = $this->autoCompleteIfHostLeft($classId, $sync, 'meet_poll') ?: $status;
                }

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
     * Poll Google Meet after the host leaves so conference end times are available.
     *
     * @return array<string, mixed>
     */
    public function syncClassAfterHostLeave(int $classId, ?string $trigger = 'teacher_leave_request'): array
    {
        $attempts = max(1, (int) env('GOOGLE_MEET_HOST_LEAVE_SYNC_ATTEMPTS', 4));
        $delaySeconds = max(1, (int) env('GOOGLE_MEET_HOST_LEAVE_SYNC_DELAY_SECONDS', 2));
        $last = ['status' => 'skipped', 'class_id' => $classId];

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $last = $this->syncClass($classId, $trigger ?? 'teacher_leave_request');
            $this->logMeetStatus([
                'event' => 'host_leave_sync_attempt',
                'class_id' => $classId,
                'attempt' => $attempt,
                'max_attempts' => $attempts,
                'sync_status' => $last['status'] ?? 'unknown',
                'meeting_live_status' => $last['meeting_live_status'] ?? null,
                'actual_end_time' => $last['actual_end_time'] ?? null,
            ]);

            if (($last['status'] ?? '') === 'completed') {
                return $last;
            }

            if ($attempt < $attempts) {
                sleep($delaySeconds);
            }
        }

        return $last;
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
            $teacherSummary = $this->enrichTeacherSummaryFromParticipant($teacherParticipant, $teacherSummary);

            $conferenceEnd = $this->normalizeUtcValue((string) ($conference['end_time'] ?? ''));
            $storedTeacherJoin = $this->normalizeUtcValue((string) ($class['teacher_joined_at'] ?? ''));
            $storedActualStart = $this->normalizeUtcValue((string) ($class['actual_start_time'] ?? ''));

            if ($teacherSummary['actual_start_time'] === null && $storedTeacherJoin !== null) {
                $teacherSummary['actual_start_time'] = $storedTeacherJoin;
                $teacherSummary['authoritative_start_time'] = $storedTeacherJoin;
            }
            if ($teacherSummary['actual_start_time'] === null && $storedActualStart !== null) {
                $teacherSummary['actual_start_time'] = $storedActualStart;
                $teacherSummary['authoritative_start_time'] = $storedActualStart;
            }

            if (
                !$teacherSummary['has_active_session']
                && $teacherSummary['actual_end_time'] === null
                && $conferenceEnd !== null
                && !$this->conferenceEndedBeforeScheduledStart($conferenceEnd, $class)
            ) {
                $teacherSummary['actual_end_time'] = $conferenceEnd;
                $teacherSummary['authoritative_end_time'] = $conferenceEnd;
            }

            $studentEarliestStart = $this->earliestNonTeacherJoinUtc($participants, $teacherParticipant['name'] ?? null);
            $participantCount = count($participants);
            $timingSource = 'host_participant_session';
            $actualStart = $teacherSummary['actual_start_time'];
            $actualEnd = $teacherSummary['actual_end_time'];
            $hasActiveTeacherSession = (bool) $teacherSummary['has_active_session'];
            $hostJoined = $teacherSummary['authoritative_start_time'] !== null || $storedTeacherJoin !== null;

            $this->logMeetStatus([
                'event' => $hostJoined ? 'host_join_detected' : 'host_not_in_meet',
                'class_id' => $classId,
                'teacher_id' => $teacherId,
                'teacher_email' => $teacherIdentity['google_email'] ?? ($class['teacher_google_email'] ?? null),
                'conference_id' => $conference['name'] ?? null,
                'host_joined' => $hostJoined,
                'host_left' => !$hasActiveTeacherSession && $actualEnd !== null,
                'host_active_session' => $hasActiveTeacherSession,
                'actual_start_time' => $actualStart,
                'actual_end_time' => $actualEnd,
                'conference_end_time' => $conferenceEnd,
                'participant_count' => $participantCount,
                'trigger' => $trigger,
            ]);

            $previousDbStatus = (string) ($class['status'] ?? 'scheduled');
            $liveStatus = 'pending';
            if ($hasActiveTeacherSession) {
                $liveStatus = 'active';
            } elseif (
                $actualEnd !== null
                && (
                    $hostJoined
                    || $storedTeacherJoin !== null
                    || $previousDbStatus === 'ongoing'
                    || ($conferenceEnd !== null && !$hasActiveTeacherSession)
                )
            ) {
                $liveStatus = 'ended';
            } elseif ($hostJoined || $storedTeacherJoin !== null) {
                $liveStatus = 'active';
            } elseif ($teacherParticipant === null && $conferenceEnd === null) {
                $liveStatus = 'sync_error';
            }

            $wouldComplete = $actualEnd !== null
                && $liveStatus === 'ended'
                && (
                    $teacherSummary['authoritative_start_time'] !== null
                    || $storedTeacherJoin !== null
                    || $previousDbStatus === 'ongoing'
                );

            $googlePayload = [
                'space' => $space,
                'conference' => $conference,
                'participants' => $participants,
                'teacher_participant' => $teacherParticipant,
                'teacher_identity' => $teacherIdentity,
            ];
            $classContext = $this->debug()->buildClassContext($class);
            $syncState = [
                'host_joined' => $hostJoined || $storedTeacherJoin !== null,
                'host_left' => !$hasActiveTeacherSession && $actualEnd !== null,
                'host_active_session' => $hasActiveTeacherSession,
                'actual_start_time' => $actualStart,
                'actual_end_time' => $actualEnd,
                'conference_end_time' => $conferenceEnd,
                'meeting_live_status' => $liveStatus,
                'participant_count' => $participantCount,
                'teacher_participant_matched' => $teacherParticipant !== null,
                'would_complete' => $wouldComplete,
                'would_complete_reason' => $wouldComplete
                    ? 'Host end + meeting_live_status ended + host join evidence'
                    : 'Blocked: liveStatus=' . $liveStatus
                        . ' host_joined=' . ($hostJoined || $storedTeacherJoin !== null ? 'yes' : 'no')
                        . ' actual_end=' . ($actualEnd ?? 'null'),
                'resulting_status' => $wouldComplete ? 'completed' : $previousDbStatus,
            ];

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
            $syncState['resulting_status'] = (string) ($persisted['class_status'] ?? $previousDbStatus);
            $syncState['meeting_live_status'] = (string) ($persisted['meeting_live_status'] ?? $liveStatus);
            $decision = $this->debug()->evaluateCompletionDecision($classContext, $syncState, $googlePayload);
            $decision['timezone_verification'] = $this->debug()->verifyTimezoneStorage($class);
            $this->debug()->logClassSync($classContext, $decision, $googlePayload, $trigger);

            $this->logMeetStatus([
                'event' => 'status_update_result',
                'class_id' => $classId,
                'teacher_email' => $teacherIdentity['google_email'] ?? ($class['teacher_google_email'] ?? null),
                'conference_id' => $conference['name'] ?? null,
                'previous_status' => $previousDbStatus,
                'new_status' => (string) ($persisted['class_status'] ?? $previousDbStatus),
                'sync_result' => $status,
                'meeting_live_status' => $persisted['meeting_live_status'] ?? $liveStatus,
                'actual_end_time' => $persisted['actual_end_time'] ?? $actualEnd,
                'actual_duration_minutes' => $persisted['actual_duration_minutes'] ?? null,
                'completion_reason' => $decision['reason'] ?? '',
                'trigger' => $trigger,
            ]);

            return [
                'status' => $status,
                'class_id' => $classId,
                'meeting_live_status' => $persisted['meeting_live_status'] ?? $liveStatus,
                'actual_start_time' => $persisted['actual_start_time'] ?? $actualStart,
                'actual_end_time' => $persisted['actual_end_time'] ?? $actualEnd,
                'actual_duration_minutes' => $persisted['actual_duration_minutes'] ?? null,
                'participant_count' => $participantCount,
                'conference_id' => $conference['name'] ?? null,
                'timing_source' => $timingSource,
                'debug' => $decision,
            ];
        } catch (\Throwable $e) {
            $message = $this->humanizeGoogleApiError($e);
            $this->markSyncError($class, $message);
            $this->logMeetStatus([
                'event' => 'sync_failed',
                'class_id' => $classId,
                'teacher_id' => $teacherId,
                'teacher_email' => $class['teacher_google_email'] ?? null,
                'error' => $e->getMessage(),
                'display_error' => $message,
                'trigger' => $trigger,
            ]);
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
             WHERE meeting_link IS NOT NULL
               AND TRIM(meeting_link) <> ""
               AND status IN ("scheduled", "rescheduled", "ongoing")
               AND (
                    start_datetime BETWEEN :window_start AND :window_end
                    OR status = "ongoing"
                    OR meeting_live_status = "active"
               )
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
     * @return array{google_person_resource_name:?string,google_person_id:?string,google_user_id:?string,google_email:?string}
     */
    private function ensureTeacherIdentity(int $teacherId): array
    {
        $account = TeacherGoogleAccount::getCredentialsForTeacher($teacherId);
        if ($account === null) {
            throw new RuntimeException('Teacher Google account is not connected.');
        }

        $existingUserId = trim((string) ($account['google_user_id'] ?? ''));
        $existingPersonId = trim((string) ($account['google_person_id'] ?? ''));
        $existingResource = trim((string) ($account['google_person_resource_name'] ?? ''));
        if ($existingUserId !== '') {
            return [
                'google_person_resource_name' => $existingResource !== '' ? $existingResource : ($existingPersonId !== '' ? 'people/' . $existingPersonId : null),
                'google_person_id' => $existingPersonId !== '' ? $existingPersonId : null,
                'google_user_id' => $existingUserId,
                'google_email' => $account['google_email'] ?? null,
            ];
        }

        $oauth = new GoogleOAuthService();
        $client = $oauth->client();
        $client->setAccessToken($oauth->getActiveAccessTokenForTeacher($teacherId));

        $googleUserId = null;
        try {
            $token = $client->getAccessToken();
            if (is_array($token) && !empty($token['id_token'])) {
                $payload = $client->verifyIdToken((string) $token['id_token']);
                if (is_array($payload) && !empty($payload['sub'])) {
                    $googleUserId = trim((string) $payload['sub']);
                }
            }
        } catch (\Throwable $ignored) {
            // Fall through to userinfo / People API lookup.
        }

        if ($googleUserId === null || $googleUserId === '') {
            try {
                $oauth2 = new GoogleOauth2($client);
                $profile = $oauth2->userinfo->get();
                $googleUserId = trim((string) ($profile->getId() ?? ''));
            } catch (\Throwable $ignored) {
                // Fall through to People API lookup.
            }
        }

        $resourceName = null;
        $personId = null;
        try {
            $people = new GooglePeopleService($client);
            $person = $people->people->get('people/me', [
                'personFields' => 'emailAddresses',
            ]);
            $resourceName = trim((string) $person->getResourceName());
            $personId = $this->personIdFromResourceName($resourceName);
        } catch (\Throwable $e) {
            if ($googleUserId === null || $googleUserId === '') {
                throw new RuntimeException('Unable to resolve the teacher Google identity for Meet tracking.');
            }
        }

        if ($googleUserId === null || $googleUserId === '') {
            throw new RuntimeException('Unable to resolve the teacher Google user id for Meet tracking. Reconnect the Google account.');
        }

        TeacherGoogleAccount::updateIdentity($teacherId, $resourceName, $personId, $googleUserId);

        return [
            'google_person_resource_name' => $resourceName,
            'google_person_id' => $personId,
            'google_user_id' => $googleUserId,
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
                $array = $this->conferenceRecordToArray($record);
                if (!$this->isStaleGhostConference($array, $class)) {
                    return $array;
                }
                $this->logMeetStatus([
                    'event' => 'ignored_stale_conference_record',
                    'class_id' => (int) ($class['id'] ?? 0),
                    'google_conference_id' => $storedConferenceId,
                    'conference_end_time' => $array['end_time'] ?? null,
                    'scheduled_start_utc' => $class['start_time_utc'] ?? $class['start_datetime'] ?? null,
                ]);
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

        return $this->pickBestConferenceRecord($records, $class);
    }

    /**
     * Prefer the conference that overlaps the scheduled class window (avoids empty "preview" meets).
     *
     * @param list<array<string, mixed>> $records
     * @return array<string, mixed>|null
     */
    private function pickBestConferenceRecord(array $records, array $class): ?array
    {
        if ($records === []) {
            return null;
        }

        $scheduledStartTs = $this->parseUtcTimestamp(
            $this->normalizeUtcValue((string) ($class['start_time_utc'] ?? $class['start_datetime'] ?? ''))
        );
        $scheduledEndTs = $this->parseUtcTimestamp(
            $this->normalizeUtcValue((string) ($class['end_time_utc'] ?? $class['end_datetime'] ?? ''))
        );

        $best = null;
        $bestScore = PHP_INT_MIN;
        foreach ($records as $record) {
            $startTs = $this->parseUtcTimestamp((string) ($record['start_time'] ?? ''));
            $endTs = $this->parseUtcTimestamp((string) ($record['end_time'] ?? ''));
            $score = 0;

            if ($scheduledStartTs !== null && $endTs !== null && $endTs < $scheduledStartTs) {
                $score -= 1000;
            }
            if ($startTs !== null && $endTs !== null && ($endTs - $startTs) < 120) {
                $score -= 800;
            }
            if ($scheduledStartTs !== null && $startTs !== null) {
                $windowEnd = $scheduledEndTs ?? ($scheduledStartTs + 3600);
                if ($startTs <= ($windowEnd + 900) && ($endTs ?? time()) >= ($scheduledStartTs - 900)) {
                    $score += 500;
                }
                $score -= (int) min(400, abs($startTs - $scheduledStartTs) / 60);
            }
            if ($startTs !== null && $endTs !== null && $endTs > $startTs) {
                $score += (int) min(200, ($endTs - $startTs) / 60);
            }
            if (empty($record['end_time'])) {
                $score += 150;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $record;
            }
        }

        return $best;
    }

    /**
     * @param array<string, mixed> $conference
     * @param array<string, mixed> $class
     */
    private function isStaleGhostConference(array $conference, array $class): bool
    {
        $scheduledStartTs = $this->parseUtcTimestamp(
            $this->normalizeUtcValue((string) ($class['start_time_utc'] ?? $class['start_datetime'] ?? ''))
        );
        $startTs = $this->parseUtcTimestamp((string) ($conference['start_time'] ?? ''));
        $endTs = $this->parseUtcTimestamp((string) ($conference['end_time'] ?? ''));
        $teacherJoined = trim((string) ($class['teacher_joined_at'] ?? '')) !== '';

        if ($scheduledStartTs === null || $endTs === null) {
            return false;
        }

        if ($endTs < $scheduledStartTs) {
            return true;
        }

        if ($teacherJoined) {
            return false;
        }

        if ($startTs !== null && ($endTs - $startTs) < 120) {
            return true;
        }

        return false;
    }

    private function conferenceEndedBeforeScheduledStart(?string $conferenceEndUtc, array $class): bool
    {
        $endTs = $this->parseUtcTimestamp($this->normalizeUtcValue((string) $conferenceEndUtc));
        $scheduledStartTs = $this->parseUtcTimestamp(
            $this->normalizeUtcValue((string) ($class['start_time_utc'] ?? $class['start_datetime'] ?? ''))
        );

        if ($endTs === null || $scheduledStartTs === null) {
            return false;
        }

        return $endTs < $scheduledStartTs;
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
     * @param array{google_person_resource_name:?string,google_person_id:?string,google_user_id:?string,google_email:?string} $teacherIdentity
     * @return array<string, mixed>|null
     */
    private function findTeacherParticipant(array $participants, array $teacherIdentity): ?array
    {
        $teacherUserId = trim((string) ($teacherIdentity['google_user_id'] ?? ''));
        $teacherPersonId = trim((string) ($teacherIdentity['google_person_id'] ?? ''));
        $teacherUserResource = $teacherUserId !== '' ? 'users/' . $teacherUserId : '';
        $legacyUserResource = $teacherPersonId !== '' ? 'users/' . $teacherPersonId : '';

        foreach ($participants as $participant) {
            $signedInUser = trim((string) ($participant['signed_in_user'] ?? ''));
            if ($teacherUserResource !== '' && $signedInUser === $teacherUserResource) {
                return $participant;
            }
            if ($legacyUserResource !== '' && $signedInUser === $legacyUserResource) {
                return $participant;
            }
            $participantId = $this->participantIdFromResourceName((string) ($participant['name'] ?? ''));
            if ($teacherUserId !== '' && $participantId === $teacherUserId) {
                return $participant;
            }
            if ($teacherPersonId !== '' && $participantId === $teacherPersonId) {
                return $participant;
            }
        }

        $signedInHosts = array_values(array_filter(
            $participants,
            static fn (array $participant): bool => trim((string) ($participant['signed_in_user'] ?? '')) !== ''
        ));
        if (count($signedInHosts) === 1) {
            return $signedInHosts[0];
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

        $teacherJoinDelayMinutes = null;
        if ($mergedTeacherJoin !== null) {
            $delayRow = $class;
            $delayRow['teacher_joined_at'] = $mergedTeacherJoin;
            if (function_exists('teacherJoinDelayMinutes')) {
                $teacherJoinDelayMinutes = teacherJoinDelayMinutes($delayRow);
            }
        }

        $previousStatus = (string) ($class['status'] ?? 'scheduled');
        $status = $previousStatus;
        $hostJoinEvidence = $mergedTeacherJoin !== null || $existingTeacherJoin !== null;
        if ($mergedEnd !== null && $liveStatus === 'ended' && $hostJoinEvidence) {
            $status = 'completed';
        } elseif ($mergedTeacherJoin !== null && $liveStatus === 'active') {
            $status = 'ongoing';
        } elseif (
            $mergedEnd !== null
            && $liveStatus === 'ended'
            && $previousStatus === 'ongoing'
            && $mergedStart !== null
        ) {
            $status = 'completed';
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
                 teacher_join_delay_minutes = COALESCE(:teacher_join_delay_minutes, teacher_join_delay_minutes),
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
            'teacher_join_delay_minutes' => $teacherJoinDelayMinutes,
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
            if (!empty($class['recurring_occurrence_id']) || !empty($class['recurring_series_id'])) {
                require_once dirname(__DIR__) . '/lib/RecurringSeriesService.php';
                $freshStmt = $pdo->prepare('SELECT * FROM class_sessions WHERE id = :id LIMIT 1');
                $freshStmt->execute(['id' => $classId]);
                $freshClass = $freshStmt->fetch();
                if ($freshClass) {
                    RecurringSeriesService::syncOccurrenceFromClassSession($classId, $freshClass);
                }
            }
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

        $this->logMeetStatus([
            'event' => $status === 'completed' ? 'host_left_class_completed' : ($resultStatus === 'started' ? 'host_joined_class_ongoing' : 'snapshot_applied'),
            'class_id' => $classId,
            'teacher_email' => $class['teacher_google_email'] ?? null,
            'conference_id' => $conference['name'] ?? null,
            'host_joined' => $mergedTeacherJoin !== null,
            'host_left' => $mergedEnd !== null,
            'status_update_result' => $status,
            'previous_status' => $previousStatus,
            'actual_start_time' => $mergedStart,
            'actual_end_time' => $mergedEnd,
            'actual_duration_minutes' => $durationMinutes,
            'trigger' => $trigger,
        ]);

        return [
            'status' => $resultStatus,
            'class_status' => $status,
            'meeting_live_status' => $liveStatus,
            'actual_start_time' => $mergedStart,
            'actual_end_time' => $mergedEnd,
            'actual_duration_minutes' => $durationMinutes,
        ];
    }

    /**
     * @param array<string, mixed>|null $teacherParticipant
     * @param array{actual_start_time:?string,actual_end_time:?string,authoritative_start_time:?string,authoritative_end_time:?string,has_active_session:bool} $summary
     * @return array{actual_start_time:?string,actual_end_time:?string,authoritative_start_time:?string,authoritative_end_time:?string,has_active_session:bool}
     */
    private function enrichTeacherSummaryFromParticipant(?array $teacherParticipant, array $summary): array
    {
        if ($teacherParticipant === null) {
            return $summary;
        }

        $participantStart = $this->normalizeUtcValue((string) ($teacherParticipant['earliest_start_time'] ?? ''));
        $participantEnd = $this->normalizeUtcValue((string) ($teacherParticipant['latest_end_time'] ?? ''));

        if ($summary['actual_start_time'] === null && $participantStart !== null) {
            $summary['actual_start_time'] = $participantStart;
            $summary['authoritative_start_time'] = $participantStart;
        }

        if (!$summary['has_active_session'] && $summary['actual_end_time'] === null && $participantEnd !== null) {
            $summary['actual_end_time'] = $participantEnd;
            $summary['authoritative_end_time'] = $participantEnd;
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $class
     * @param list<array<string, mixed>> $participants
     * @param array{google_person_resource_name:?string,google_person_id:?string,google_user_id:?string,google_email:?string} $teacherIdentity
     */
    private function syncParticipantLogs(array $class, array $participants, array $teacherIdentity): void
    {
        $classId = (int) ($class['id'] ?? 0);
        $teacherUserId = (int) ($class['teacher_id'] ?? 0);
        $teacherMeetUserId = trim((string) ($teacherIdentity['google_user_id'] ?? ''));
        $teacherPersonId = trim((string) ($teacherIdentity['google_person_id'] ?? ''));
        $teacherUserResource = $teacherMeetUserId !== '' ? 'users/' . $teacherMeetUserId : ($teacherPersonId !== '' ? 'users/' . $teacherPersonId : '');

        foreach ($participants as $participant) {
            $participantName = (string) ($participant['name'] ?? '');
            $participantId = $this->participantIdFromResourceName($participantName);
            $signedInUser = trim((string) ($participant['signed_in_user'] ?? ''));
            $role = 'student';
            $userId = null;

            if (
                ($teacherUserResource !== '' && $signedInUser === $teacherUserResource)
                || ($teacherMeetUserId !== '' && $participantId === $teacherMeetUserId)
                || ($teacherPersonId !== '' && $participantId === $teacherPersonId)
            ) {
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
     * When Meet reports the host has left, finalize completion without a manual "End Class" click.
     *
     * @param array<string, mixed> $sync
     */
    private function autoCompleteIfHostLeft(int $classId, array $sync, string $trigger): string
    {
        $class = $this->getClassById($classId);
        if ($class === null || (string) ($class['status'] ?? '') === 'completed') {
            return ($sync['status'] ?? '') === 'completed' ? 'completed' : (string) ($sync['status'] ?? 'unchanged');
        }

        if ((string) ($class['status'] ?? '') !== 'ongoing') {
            return (string) ($sync['status'] ?? 'unchanged');
        }

        $liveStatus = (string) ($sync['meeting_live_status'] ?? ($class['meeting_live_status'] ?? ''));
        $hasHostEnd = trim((string) ($sync['actual_end_time'] ?? ($class['actual_end_time'] ?? ''))) !== '';

        if ($liveStatus !== 'ended' && !$hasHostEnd) {
            return (string) ($sync['status'] ?? 'unchanged');
        }

        $leaveSync = $this->syncClassAfterHostLeave($classId, $trigger . '_auto');
        if (($leaveSync['status'] ?? '') === 'completed') {
            return 'completed';
        }

        $refreshed = $this->getClassById($classId);
        if ($refreshed !== null && (string) ($refreshed['status'] ?? '') === 'completed') {
            return 'completed';
        }

        try {
            require_once dirname(__DIR__) . '/lib/MeetingTrackingService.php';
            (new MeetingTrackingService())->completeClass($classId, null, $trigger . '_auto');
        } catch (\Throwable $ignored) {
            return (string) ($sync['status'] ?? 'unchanged');
        }

        $refreshed = $this->getClassById($classId);

        return ($refreshed !== null && (string) ($refreshed['status'] ?? '') === 'completed')
            ? 'completed'
            : (string) ($sync['status'] ?? 'unchanged');
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logMeetStatus(array $context): void
    {
        SyncLog::write('google_meet_status.log', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logLiveTracking(array $context): void
    {
        SyncLog::write('google_meet_live_tracking.log', $context);
        $this->logMeetStatus($context);
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

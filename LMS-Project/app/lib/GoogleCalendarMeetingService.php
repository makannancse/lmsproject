<?php

declare(strict_types=1);

use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event as GoogleCalendarEvent;
use Google\Service\Calendar\EventAttendee as GoogleEventAttendee;
use Google\Service\Calendar\EventDateTime as GoogleEventDateTime;
use Google\Service\Calendar\ConferenceData as GoogleConferenceData;
use Google\Service\Calendar\CreateConferenceRequest as GoogleCreateConferenceRequest;
use Google\Service\Calendar\ConferenceSolutionKey as GoogleConferenceSolutionKey;

require_once dirname(__DIR__) . '/lib/GoogleOAuthService.php';
require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/models/SystemConfig.php';

class GoogleCalendarMeetingService
{
    /**
     * @return array{id:string,status:string,summary:string,start:?string,end:?string,updated:?string}|null
     */
    public function getMeetingEvent(int $teacherId, string $eventId): ?array
    {
        $eventId = trim($eventId);
        if ($eventId === '') {
            return null;
        }

        $oauth = new GoogleOAuthService();
        $account = $oauth->getTeacherAccount($teacherId);
        $client = $oauth->client();
        $client->setAccessToken($oauth->getActiveAccessTokenForTeacher($teacherId));
        $calendar = new GoogleCalendar($client);
        $calendarId = $this->calendarIdForTeacher($account);

        try {
            // events.get() does not accept conferenceDataVersion (only insert/patch/import do).
            $event = $calendar->events->get($calendarId, $eventId);
        } catch (\Throwable $e) {
            $this->logFailure([
                'message' => 'Failed to fetch Google Calendar event',
                'teacher_id' => $teacherId,
                'teacher_google_email' => $account['google_email'] ?? null,
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        $start = $event->getStart();
        $end = $event->getEnd();

        return [
            'id' => (string) ($event->getId() ?? $eventId),
            'status' => (string) ($event->getStatus() ?? 'confirmed'),
            'summary' => (string) ($event->getSummary() ?? ''),
            'start' => $start ? (string) ($start->getDateTime() ?? $start->getDate() ?? '') : null,
            'end' => $end ? (string) ($end->getDateTime() ?? $end->getDate() ?? '') : null,
            'updated' => (string) ($event->getUpdated() ?? ''),
        ];
    }

    /**
     * @return array{meet_link:string,event_id:string}
     */
    public function createMeeting(
        int $teacherId,
        string $startTime,
        string $endTime,
        string $timezone = 'Asia/Kolkata',
        string $summary = 'LMS Class',
        array $attendeeEmails = []
    ): array
    {
        $this->assertTeacherAvailability($teacherId, $startTime, $endTime);
        $oauth = new GoogleOAuthService();
        $account = $oauth->getTeacherAccount($teacherId);
        $client = $oauth->client();
        $token = $oauth->getActiveAccessTokenForTeacher($teacherId);
        $client->setAccessToken($token);
        $calendarId = $this->calendarIdForTeacher($account);
        $sanitizedAttendees = $this->sanitizeAttendeeEmails($attendeeEmails, (string) ($account['google_email'] ?? ''));
        $this->logInfo([
            'message' => 'Creating Google Meet event',
            'teacher_id' => $teacherId,
            'teacher_google_email' => $account['google_email'],
            'calendar_id' => $calendarId,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'timezone' => $timezone,
            'attendee_count' => count($sanitizedAttendees),
            'redirect_uri' => $oauth->configuredRedirectUri(),
            'access_token_preview' => $this->maskToken((string) ($token['access_token'] ?? '')),
        ]);

        $calendar = new GoogleCalendar($client);

        $requestId = 'learnwise_' . bin2hex(random_bytes(8));
        $event = new GoogleCalendarEvent([
            'summary' => $summary,
            'start' => new GoogleEventDateTime(['dateTime' => $startTime, 'timeZone' => $timezone]),
            'end' => new GoogleEventDateTime(['dateTime' => $endTime, 'timeZone' => $timezone]),
            'attendees' => array_map(
                static fn (string $email): GoogleEventAttendee => new GoogleEventAttendee(['email' => $email]),
                $sanitizedAttendees
            ),
            'guestsCanModify' => false,
            'guestsCanInviteOthers' => false,
            'conferenceData' => new GoogleConferenceData([
                'createRequest' => new GoogleCreateConferenceRequest([
                    'requestId' => $requestId,
                    'conferenceSolutionKey' => new GoogleConferenceSolutionKey(['type' => 'hangoutsMeet']),
                ]),
            ]),
        ]);

        try {
            $created = $calendar->events->insert($calendarId, $event, [
                'conferenceDataVersion' => 1,
                'sendUpdates' => $sanitizedAttendees !== [] ? 'all' : 'none',
            ]);
        } catch (\Throwable $e) {
            // One retry after explicit refresh
            try {
                $client->setAccessToken($oauth->refreshAccessTokenForTeacher($teacherId, $account));
                $created = $calendar->events->insert($calendarId, $event, [
                    'conferenceDataVersion' => 1,
                    'sendUpdates' => $sanitizedAttendees !== [] ? 'all' : 'none',
                ]);
            } catch (\Throwable $retryError) {
                $this->logFailure([
                    'message' => 'Google Calendar insert failed',
                    'teacher_id' => $teacherId,
                    'teacher_google_email' => $account['google_email'],
                    'request_id' => $requestId,
                    'error' => $retryError->getMessage(),
                ]);
                throw new RuntimeException(
                    GoogleOAuthService::isScopeInsufficientError($retryError)
                        ? GoogleOAuthService::scopeInsufficientMessage()
                        : $retryError->getMessage(),
                    0,
                    $retryError
                );
            }
        }

        $meet = $this->extractMeetLink($created);
        $id = (string) ($created->getId() ?? '');
        if (($meet === '' || $id === '') && $id !== '') {
            try {
                $created = $calendar->events->get($calendarId, $id);
                $meet = $this->extractMeetLink($created);
            } catch (\Throwable $e) {
                $this->logFailure([
                    'message' => 'Failed to re-fetch Google event for Meet link extraction',
                    'teacher_id' => $teacherId,
                    'event_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $organizerEmail = $this->extractOrganizerEmail($created, (string) ($account['google_email'] ?? ''));
        $this->logInfo([
            'message' => 'Google Calendar API response',
            'teacher_id' => $teacherId,
            'teacher_google_email' => $account['google_email'],
            'request_id' => $requestId,
            'organizer_email' => $organizerEmail,
            'event_id' => $id,
            'meeting_link' => $meet,
            'response' => print_r($created, true),
        ]);
        logMeetingHost([
            'event' => 'meeting_created',
            'teacher_id' => $teacherId,
            'teacher_google_email' => $account['google_email'] ?? null,
            'organizer_email' => $organizerEmail,
            'calendar_id' => $calendarId,
            'event_id' => $id,
            'meeting_link' => $meet,
            'attendee_count' => count($sanitizedAttendees),
        ]);

        $expectedOrganizer = strtolower(trim((string) ($account['google_email'] ?? '')));
        if ($expectedOrganizer !== '' && strtolower($organizerEmail) !== $expectedOrganizer) {
            logMeetingHost([
                'event' => 'meeting_organizer_mismatch',
                'teacher_id' => $teacherId,
                'teacher_google_email' => $account['google_email'] ?? null,
                'organizer_email' => $organizerEmail,
                'calendar_id' => $calendarId,
                'event_id' => $id,
            ]);
            if ($id !== '') {
                $this->deleteMeeting($teacherId, $id);
            }
            throw new RuntimeException('Google Meet organizer mismatch. Teacher would not be host; reconnect the correct Workspace account.');
        }

        if ($meet === '' || $id === '') {
            $this->logFailure([
                'message' => 'Google event created without Meet link',
                'teacher_id' => $teacherId,
                'teacher_google_email' => $account['google_email'],
                'request_id' => $requestId,
                'event_id' => $id,
                'response' => print_r($created, true),
            ]);
            throw new RuntimeException('Google Calendar created event without Meet link.');
        }

        return [
            'meet_link' => $meet,
            'event_id' => $id,
            'organizer_email' => $organizerEmail,
            'calendar_id' => $calendarId,
        ];
    }

    public function deleteMeeting(int $teacherId, string $eventId): void
    {
        $eventId = trim($eventId);
        if ($eventId === '') {
            return;
        }

        $oauth = new GoogleOAuthService();
        $account = $oauth->getTeacherAccount($teacherId);
        $client = $oauth->client();
        $client->setAccessToken($oauth->getActiveAccessTokenForTeacher($teacherId));
        $calendar = new GoogleCalendar($client);
        $calendarId = $this->calendarIdForTeacher($account);

        try {
            $calendar->events->delete($calendarId, $eventId);
            $this->logInfo([
                'message' => 'Deleted orphaned Google Calendar event',
                'teacher_id' => $teacherId,
                'teacher_google_email' => $account['google_email'],
                'event_id' => $eventId,
            ]);
        } catch (\Throwable $e) {
            $this->logFailure([
                'message' => 'Failed to delete orphaned Google Calendar event',
                'teacher_id' => $teacherId,
                'teacher_google_email' => $account['google_email'],
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updateMeeting(
        int $teacherId,
        string $eventId,
        string $startTime,
        string $endTime,
        string $timezone = 'UTC',
        ?string $summary = null
    ): void {
        $eventId = trim($eventId);
        if ($eventId === '') {
            return;
        }

        $oauth = new GoogleOAuthService();
        $account = $oauth->getTeacherAccount($teacherId);
        $client = $oauth->client();
        $token = $oauth->getActiveAccessTokenForTeacher($teacherId);
        $client->setAccessToken($token);
        $calendar = new GoogleCalendar($client);
        $calendarId = $this->calendarIdForTeacher($account);

        $patchEvent = function () use ($calendar, $calendarId, $eventId, $startTime, $endTime, $timezone, $summary): void {
            $event = new GoogleCalendarEvent([
                'start' => new GoogleEventDateTime(['dateTime' => $startTime, 'timeZone' => $timezone]),
                'end' => new GoogleEventDateTime(['dateTime' => $endTime, 'timeZone' => $timezone]),
            ]);
            if ($summary !== null && trim($summary) !== '') {
                $event->setSummary($summary);
            }

            $calendar->events->patch($calendarId, $eventId, $event, [
                'sendUpdates' => 'all',
            ]);
        };

        try {
            $patchEvent();
        } catch (\Throwable $e) {
            try {
                $client->setAccessToken($oauth->refreshAccessTokenForTeacher($teacherId, $account));
                $patchEvent();
            } catch (\Throwable $retryError) {
                $this->logFailure([
                    'message' => 'Google Calendar patch failed',
                    'teacher_id' => $teacherId,
                    'teacher_google_email' => $account['google_email'] ?? null,
                    'event_id' => $eventId,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'timezone' => $timezone,
                    'error' => $retryError->getMessage(),
                ]);
                throw new RuntimeException(
                    GoogleOAuthService::isScopeInsufficientError($retryError)
                        ? GoogleOAuthService::scopeInsufficientMessage()
                        : $retryError->getMessage(),
                    0,
                    $retryError
                );
            }
        }

        $this->logInfo([
            'message' => 'Google Calendar event updated',
            'teacher_id' => $teacherId,
            'teacher_google_email' => $account['google_email'] ?? null,
            'event_id' => $eventId,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'timezone' => $timezone,
            'summary' => $summary,
        ]);
    }

    private function assertTeacherAvailability(int $teacherId, string $startTime, string $endTime): void
    {
        $start = new DateTimeImmutable($startTime);
        $end = new DateTimeImmutable($endTime);
        if ($end <= $start) {
            throw new RuntimeException('End time must be after start time.');
        }

        $startUtc = $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $endUtc = $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM class_sessions
             WHERE teacher_id = :teacher_id
               AND status IN ("scheduled", "rescheduled", "ongoing")
               AND start_datetime < :end_utc
               AND end_datetime > :start_utc'
        );
        $stmt->execute([
            'teacher_id' => $teacherId,
            'start_utc' => $startUtc,
            'end_utc' => $endUtc,
        ]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException('Teacher already has a class in this time range.');
        }
    }

    private function extractMeetLink(GoogleCalendarEvent $event): string
    {
        $hangoutLink = trim((string) ($event->getHangoutLink() ?? ''));
        if ($hangoutLink !== '') {
            return $hangoutLink;
        }

        $conferenceData = $event->getConferenceData();
        if ($conferenceData === null) {
            return '';
        }

        $entryPoints = $conferenceData->getEntryPoints() ?? [];
        foreach ($entryPoints as $entryPoint) {
            $type = strtolower((string) ($entryPoint->getEntryPointType() ?? ''));
            $uri = trim((string) ($entryPoint->getUri() ?? ''));
            if (($type === 'video' || $type === '') && $uri !== '') {
                return $uri;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $account
     */
    private function calendarIdForTeacher(array $account): string
    {
        $configured = trim((string) SystemConfig::get('google_calendar_id', env('GOOGLE_CALENDAR_ID', 'primary')));
        if ($configured !== '' && strtolower($configured) !== 'primary') {
            $this->logInfo([
                'message' => 'Ignoring shared calendar ID to preserve teacher host ownership',
                'teacher_id' => (int) ($account['teacher_id'] ?? 0),
                'teacher_google_email' => $account['google_email'] ?? null,
                'configured_calendar_id' => $configured,
            ]);
        }

        return 'primary';
    }

    /**
     * @param list<string> $attendeeEmails
     * @return list<string>
     */
    private function sanitizeAttendeeEmails(array $attendeeEmails, string $teacherEmail = ''): array
    {
        $seen = [];
        $normalizedTeacher = strtolower(trim($teacherEmail));
        foreach ($attendeeEmails as $email) {
            $candidate = strtolower(trim((string) $email));
            if ($candidate === '' || !filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if ($normalizedTeacher !== '' && $candidate === $normalizedTeacher) {
                continue;
            }
            $seen[$candidate] = $candidate;
        }

        return array_values($seen);
    }

    private function extractOrganizerEmail(GoogleCalendarEvent $event, string $fallback = ''): string
    {
        $organizer = $event->getOrganizer();
        if ($organizer !== null) {
            $email = trim((string) ($organizer->getEmail() ?? ''));
            if ($email !== '') {
                return $email;
            }
        }

        return trim($fallback);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logInfo(array $context): void
    {
        $this->writeLog('INFO', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logFailure(array $context): void
    {
        $this->writeLog('ERROR', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function writeLog(string $level, array $context): void
    {
        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $context['timestamp'] = date('Y-m-d H:i:s');
        $context['level'] = $level;
        $line = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        @file_put_contents($dir . DIRECTORY_SEPARATOR . 'google_meet.log', $line, FILE_APPEND);
        @file_put_contents($dir . DIRECTORY_SEPARATOR . 'meeting_host.log', $line, FILE_APPEND);
    }

    private function maskToken(string $token): string
    {
        $len = strlen($token);
        if ($len <= 12) {
            return str_repeat('*', $len);
        }

        return substr($token, 0, 6) . '...' . substr($token, -4);
    }
}

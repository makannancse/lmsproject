<?php

declare(strict_types=1);

use Google\Service\Drive as GoogleDrive;
use Google\Service\Drive\DriveFile as GoogleDriveFile;

require_once dirname(__DIR__) . '/lib/GoogleOAuthService.php';

class GoogleDriveRecordingService
{
    /**
     * @param array<string, mixed> $classRow
     * @return array<string, mixed>|null
     */
    public function findRecordingForClass(array $classRow): ?array
    {
        $result = $this->findRecordingForClassDetailed($classRow);
        return $result['recording'] ?? null;
    }

    /**
     * @param array<string, mixed> $classRow
     * @return array{recording:?array,debug:array<string,mixed>}
     */
    public function findRecordingForClassDetailed(array $classRow): array
    {
        $teacherId = (int) ($classRow['teacher_id'] ?? 0);
        if ($teacherId <= 0) {
            return [
                'recording' => null,
                'debug' => [
                    'error' => 'Missing teacher_id on class row.',
                ],
            ];
        }

        $teacherEmail = strtolower(trim((string) ($classRow['teacher_google_email'] ?? '')));
        $meetingStartUtc = $this->resolveClassUtcValue($classRow, [
            'actual_start_time',
            'start_time_utc',
            'scheduled_time_utc',
            'start_datetime',
        ]);
        $meetingEndUtc = $this->resolveClassUtcValue($classRow, [
            'actual_end_time',
            'completed_at',
            'end_time_utc',
            'end_datetime',
        ]);

        $windowStart = $this->offsetRfc3339($meetingStartUtc ?? $meetingEndUtc ?? 'now', '-2 hours');
        $windowEnd = $this->offsetRfc3339($meetingEndUtc ?? $meetingStartUtc ?? 'now', '+2 hours');
        $meetingStartTs = $this->parseTimestamp($meetingStartUtc);
        $meetingEndTs = $this->parseTimestamp($meetingEndUtc);
        $windowStartTs = $this->parseTimestamp($windowStart);
        $windowEndTs = $this->parseTimestamp($windowEnd);

        $oauth = new GoogleOAuthService();
        $client = $oauth->client();
        $client->setAccessToken($oauth->getActiveAccessTokenForAdmin());

        if (!class_exists(GoogleDrive::class)) {
            throw new RuntimeException('Google Drive API client is not installed.');
        }

        $drive = new GoogleDrive($client);
        $folders = $this->findMeetRecordingsFolders($drive);
        $queries = $this->buildSearchQueries($folders['folders'], $teacherEmail, $windowStart, $windowEnd);

        $queryDebug = [];
        $candidateMap = [];
        $queryErrors = [];

        foreach ($queries as $queryMeta) {
            $search = $this->searchDriveForCandidates(
                $drive,
                (string) $queryMeta['query'],
                $teacherEmail,
                $classRow,
                $meetingStartTs,
                $meetingEndTs,
                $windowStartTs,
                $windowEndTs
            );

            $entry = [
                'scope' => $queryMeta['scope'] ?? 'unknown',
                'query' => $queryMeta['query'] ?? '',
                'returned_files' => $search['returned_files'] ?? 0,
                'candidate_count' => count($search['candidates'] ?? []),
                'samples' => $search['samples'] ?? [],
            ];

            if (!empty($search['error'])) {
                $entry['error'] = $search['error'];
                $queryErrors[] = (string) $search['error'];
            }

            $queryDebug[] = $entry;

            foreach ($search['candidates'] ?? [] as $candidate) {
                $fileId = (string) ($candidate['recording']['recording_file_id'] ?? '');
                if ($fileId === '') {
                    continue;
                }

                if (!isset($candidateMap[$fileId]) || (int) $candidate['score'] > (int) $candidateMap[$fileId]['score']) {
                    $candidateMap[$fileId] = $candidate;
                }
            }
        }

        $candidates = array_values($candidateMap);
        usort($candidates, static function (array $left, array $right): int {
            $scoreComparison = (int) ($right['score'] ?? 0) <=> (int) ($left['score'] ?? 0);
            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            return (int) ($left['time_delta_seconds'] ?? PHP_INT_MAX) <=> (int) ($right['time_delta_seconds'] ?? PHP_INT_MAX);
        });

        if ($candidates === [] && $queryErrors !== [] && count($queryErrors) === count($queries)) {
            throw new RuntimeException('Google Drive search failed: ' . $queryErrors[0]);
        }

        $matched = $candidates[0] ?? null;

        return [
            'recording' => $matched['recording'] ?? null,
            'debug' => [
                'teacher_email' => $teacherEmail,
                'meeting_start_utc' => $meetingStartUtc,
                'meeting_end_utc' => $meetingEndUtc,
                'window_start_utc' => $windowStart,
                'window_end_utc' => $windowEnd,
                'meet_recordings_folders' => $folders['folders'],
                'folder_lookup_error' => $folders['error'],
                'queries' => $queryDebug,
                'candidate_count' => count($candidates),
                'candidates' => array_map(
                    static fn (array $candidate): array => $candidate['debug'],
                    array_slice($candidates, 0, 10)
                ),
                'matched_recording' => $matched['debug'] ?? null,
            ],
        ];
    }

    /**
     * @return array{folders:list<array{id:string,name:string}>,error:?string}
     */
    private function findMeetRecordingsFolders(GoogleDrive $drive): array
    {
        $queries = [
            "mimeType = 'application/vnd.google-apps.folder' and trashed = false and name = 'Meet Recordings'",
            "mimeType = 'application/vnd.google-apps.folder' and trashed = false and name contains 'Meet'",
        ];

        $folders = [];
        $error = null;

        foreach ($queries as $query) {
            try {
                $response = $drive->files->listFiles([
                    'q' => $query,
                    'spaces' => 'drive',
                    'pageSize' => 20,
                    'fields' => 'files(id,name)',
                ]);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                continue;
            }

            foreach (($response->getFiles() ?: []) as $folder) {
                $id = trim((string) ($folder->getId() ?? ''));
                $name = trim((string) ($folder->getName() ?? ''));
                if ($id === '') {
                    continue;
                }

                $folders[$id] = [
                    'id' => $id,
                    'name' => $name !== '' ? $name : 'Meet Recordings',
                ];
            }

            if ($folders !== []) {
                break;
            }
        }

        return [
            'folders' => array_values($folders),
            'error' => $error,
        ];
    }

    /**
     * @param list<array{id:string,name:string}> $folders
     * @return list<array{scope:string,query:string}>
     */
    private function buildSearchQueries(array $folders, string $teacherEmail, string $windowStart, string $windowEnd): array
    {
        $queries = [];
        foreach ($folders as $folder) {
            $folderId = trim((string) ($folder['id'] ?? ''));
            if ($folderId === '') {
                continue;
            }

            $queries[] = [
                'scope' => 'meet_recordings_folder',
                'query' => sprintf(
                    "trashed = false and createdTime >= '%s' and createdTime <= '%s' and '%s' in parents and (mimeType contains 'video/' or mimeType = 'application/vnd.google-apps.video')",
                    $windowStart,
                    $windowEnd,
                    $this->escapeDriveQueryValue($folderId)
                ),
            ];
        }

        if ($teacherEmail !== '') {
            $queries[] = [
                'scope' => 'teacher_owned_window',
                'query' => sprintf(
                    "trashed = false and createdTime >= '%s' and createdTime <= '%s' and '%s' in owners and (mimeType contains 'video/' or mimeType = 'application/vnd.google-apps.video')",
                    $windowStart,
                    $windowEnd,
                    $this->escapeDriveQueryValue($teacherEmail)
                ),
            ];
        }

        $queries[] = [
            'scope' => 'drive_window_fallback',
            'query' => sprintf(
                "trashed = false and createdTime >= '%s' and createdTime <= '%s' and (mimeType contains 'video/' or mimeType = 'application/vnd.google-apps.video')",
                $windowStart,
                $windowEnd
            ),
        ];

        return $queries;
    }

    /**
     * Creates a 'reader' permission for 'anyone' on the specified Google Drive file.
     * Required so students can view the recording when it is shared via the LMS.
     */
    public function shareFileWithAnyone(int $teacherId, string $fileId): bool
    {
        if ($teacherId <= 0 || $fileId === '') {
            return false;
        }

        try {
            $oauth = new GoogleOAuthService();
            $client = $oauth->client();
            $client->setAccessToken($oauth->getActiveAccessTokenForAdmin());

            if (!class_exists(GoogleDrive::class)) {
                return false;
            }

            $drive = new GoogleDrive($client);
            $permission = new \Google\Service\Drive\Permission([
                'type' => 'anyone',
                'role' => 'reader',
            ]);

            $drive->permissions->create($fileId, $permission);
            return true;
        } catch (\Throwable $e) {
            error_log('Failed to share Google Drive file: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param array<string, mixed> $classRow
     * @return array{returned_files:int,candidates:list<array{score:int,time_delta_seconds:int,recording:array<string,mixed>,debug:array<string,mixed>}>,samples:list<array<string,mixed>>,error:?string}
     */
    private function searchDriveForCandidates(
        GoogleDrive $drive,
        string $query,
        string $teacherEmail,
        array $classRow,
        ?int $meetingStartTs,
        ?int $meetingEndTs,
        ?int $windowStartTs,
        ?int $windowEndTs
    ): array {
        try {
            $response = $drive->files->listFiles([
                'q' => $query,
                'spaces' => 'drive',
                'pageSize' => 100,
                'orderBy' => 'modifiedTime desc',
                'fields' => 'files(id,name,mimeType,webViewLink,webContentLink,createdTime,modifiedTime,owners(emailAddress),lastModifyingUser(emailAddress),parents,videoMediaMetadata/durationMillis)',
            ]);
        } catch (\Throwable $e) {
            return [
                'candidates' => [],
                'returned_files' => 0,
                'samples' => [],
                'error' => $e->getMessage(),
            ];
        }

        $files = $response->getFiles() ?: [];
        $candidates = [];
        $samples = [];

        foreach ($files as $file) {
            $sample = [
                'id' => (string) ($file->getId() ?? ''),
                'name' => (string) ($file->getName() ?? ''),
                'mime_type' => (string) ($file->getMimeType() ?? ''),
                'created_time' => (string) ($file->getCreatedTime() ?? ''),
            ];
            if (count($samples) < 10) {
                $samples[] = $sample;
            }

            $candidate = $this->buildCandidate(
                $file,
                $teacherEmail,
                $classRow,
                $meetingStartTs,
                $meetingEndTs,
                $windowStartTs,
                $windowEndTs
            );
            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        return [
            'candidates' => $candidates,
            'returned_files' => count($files),
            'samples' => $samples,
            'error' => null,
        ];
    }

    /**
     * @param array<string, mixed> $classRow
     * @return array<string, mixed>|null
     */
    private function buildCandidate(
        GoogleDriveFile $file,
        string $teacherEmail,
        array $classRow,
        ?int $meetingStartTs,
        ?int $meetingEndTs,
        ?int $windowStartTs,
        ?int $windowEndTs
    ): ?array {
        $mimeType = strtolower(trim((string) ($file->getMimeType() ?? '')));
        $isVideo = $mimeType !== '' && (str_contains($mimeType, 'video/') || $mimeType === 'application/vnd.google-apps.video');
        if (!$isVideo) {
            return null;
        }

        $fileId = trim((string) ($file->getId() ?? ''));
        if ($fileId === '') {
            return null;
        }

        $name = trim((string) ($file->getName() ?? ''));
        $createdTime = trim((string) ($file->getCreatedTime() ?? ''));
        $modifiedTime = trim((string) ($file->getModifiedTime() ?? ''));
        $referenceTs = $this->parseTimestamp($createdTime !== '' ? $createdTime : $modifiedTime);
        if ($referenceTs !== null) {
            if ($windowStartTs !== null && $referenceTs < $windowStartTs) {
                return null;
            }
            if ($windowEndTs !== null && $referenceTs > $windowEndTs) {
                return null;
            }
        }

        $ownerEmails = [];
        foreach ((array) ($file->getOwners() ?? []) as $owner) {
            if (method_exists($owner, 'getEmailAddress')) {
                $email = strtolower(trim((string) $owner->getEmailAddress()));
                if ($email !== '') {
                    $ownerEmails[] = $email;
                }
            }
        }

        $lastModifyingEmail = '';
        $lastModifyingUser = $file->getLastModifyingUser();
        if ($lastModifyingUser !== null && method_exists($lastModifyingUser, 'getEmailAddress')) {
            $lastModifyingEmail = strtolower(trim((string) $lastModifyingUser->getEmailAddress()));
        }

        $score = 1;
        $signals = [];

        if ($teacherEmail !== '' && in_array($teacherEmail, $ownerEmails, true)) {
            $score += 40;
            $signals[] = 'teacher_owner';
        }
        if ($teacherEmail !== '' && $lastModifyingEmail === $teacherEmail) {
            $score += 20;
            $signals[] = 'teacher_last_modifier';
        }

        $nameLower = strtolower($name);
        if (str_contains($nameLower, 'meet')) {
            $score += 15;
            $signals[] = 'name_contains_meet';
        }
        if (str_contains($nameLower, 'record')) {
            $score += 10;
            $signals[] = 'name_contains_record';
        }

        $titleHits = $this->countTitleWordHits((string) ($classRow['title'] ?? ''), $nameLower);
        if ($titleHits > 0) {
            $score += min(20, $titleHits * 5);
            $signals[] = 'title_hits:' . $titleHits;
        }

        $timeDeltaSeconds = null;
        if ($referenceTs !== null) {
            $targetTs = $meetingEndTs ?? $meetingStartTs;
            if ($targetTs !== null) {
                $timeDeltaSeconds = abs($referenceTs - $targetTs);
                if ($timeDeltaSeconds <= 15 * 60) {
                    $score += 25;
                    $signals[] = 'created_within_15_minutes';
                } elseif ($timeDeltaSeconds <= 60 * 60) {
                    $score += 15;
                    $signals[] = 'created_within_60_minutes';
                } elseif ($timeDeltaSeconds <= 2 * 60 * 60) {
                    $score += 8;
                    $signals[] = 'created_within_120_minutes';
                }
            }
        }

        $durationMs = null;
        $meta = $file->getVideoMediaMetadata();
        if ($meta !== null && method_exists($meta, 'getDurationMillis')) {
            $rawDuration = $meta->getDurationMillis();
            if ($rawDuration !== null) {
                $durationMs = (int) $rawDuration;
            }
        }

        $url = trim((string) ($file->getWebViewLink() ?? $file->getWebContentLink() ?? ''));
        if ($url === '') {
            $url = 'https://drive.google.com/file/d/' . rawurlencode($fileId) . '/view';
        }

        $recording = [
            'recording_url' => $url,
            'recording_file_id' => $fileId,
            'recording_title' => $name !== '' ? $name : ((string) ($classRow['title'] ?? 'Class Recording')),
            'recording_duration' => $durationMs !== null ? (int) round($durationMs / 1000 / 60) : null,
            'sync_status' => 'ready',
            'source' => 'google_drive',
        ];

        return [
            'score' => $score,
            'time_delta_seconds' => $timeDeltaSeconds ?? PHP_INT_MAX,
            'recording' => $recording,
            'debug' => [
                'file_id' => $fileId,
                'name' => $recording['recording_title'],
                'mime_type' => $mimeType,
                'created_time' => $createdTime,
                'modified_time' => $modifiedTime,
                'owner_emails' => $ownerEmails,
                'last_modifying_email' => $lastModifyingEmail,
                'score' => $score,
                'time_delta_seconds' => $timeDeltaSeconds,
                'signals' => $signals,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $classRow
     * @param list<string> $keys
     */
    private function resolveClassUtcValue(array $classRow, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($classRow[$key] ?? ''));
            if ($value === '') {
                continue;
            }

            return $this->toUtcString($value);
        }

        return null;
    }

    private function offsetRfc3339(string $value, string $modify): string
    {
        $utcString = $this->toUtcString($value) ?? 'now';

        try {
            $dt = new DateTimeImmutable($utcString, new DateTimeZone('UTC'));
        } catch (\Throwable $e) {
            $dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }

        return $dt->modify($modify)->format(DATE_RFC3339);
    }

    private function toUtcString(string $value): ?string
    {
        try {
            $dt = new DateTimeImmutable($value);
        } catch (\Throwable $e) {
            try {
                $dt = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            } catch (\Throwable $inner) {
                return null;
            }
        }

        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function parseTimestamp(?string $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->getTimestamp();
        } catch (\Throwable $e) {
            try {
                return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->getTimestamp();
            } catch (\Throwable $inner) {
                return null;
            }
        }
    }

    private function countTitleWordHits(string $title, string $haystackLower): int
    {
        $title = strtolower(trim($title));
        if ($title === '' || $haystackLower === '') {
            return 0;
        }

        if (str_contains($haystackLower, $title)) {
            return max(1, count(preg_split('/\s+/', $title, -1, PREG_SPLIT_NO_EMPTY) ?: []));
        }

        $hits = 0;
        $words = preg_split('/\s+/', $title, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($words as $word) {
            if (strlen($word) < 3) {
                continue;
            }
            if (str_contains($haystackLower, $word)) {
                $hits++;
            }
        }

        return $hits;
    }

    private function escapeDriveQueryValue(string $value): string
    {
        return str_replace(["\\", "'"], ["\\\\", "\\'"], $value);
    }
}

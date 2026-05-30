<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/SyncLog.php';
require_once dirname(__DIR__) . '/lib/helpers.php';

class MeetingSyncDebugService
{
    /**
     * @param array<string, mixed> $class
     * @return array<string, mixed>
     */
    public function buildClassContext(array $class): array
    {
        $scheduledTz = normalizeTimezone(
            (string) ($class['scheduled_timezone'] ?? $class['timezone'] ?? 'UTC'),
            'UTC'
        );
        $startUtc = $this->normalizeUtc((string) ($class['start_time_utc'] ?? $class['start_datetime'] ?? ''));
        $endUtc = $this->normalizeUtc((string) ($class['end_time_utc'] ?? $class['end_datetime'] ?? ''));

        return [
            'class_id' => (int) ($class['id'] ?? 0),
            'status' => (string) ($class['status'] ?? ''),
            'meeting_live_status' => (string) ($class['meeting_live_status'] ?? ''),
            'scheduled_timezone' => $scheduledTz,
            'timezone_column' => (string) ($class['timezone'] ?? ''),
            'start_time_utc' => $startUtc,
            'end_time_utc' => $endUtc,
            'start_datetime_column' => (string) ($class['start_datetime'] ?? ''),
            'end_datetime_column' => (string) ($class['end_datetime'] ?? ''),
            'scheduled_local_start' => $startUtc !== null ? formatUtcForTimezone($startUtc, $scheduledTz, 'Y-m-d H:i:s T') : null,
            'scheduled_local_end' => $endUtc !== null ? formatUtcForTimezone($endUtc, $scheduledTz, 'Y-m-d H:i:s T') : null,
            'actual_start_time' => $this->normalizeUtc((string) ($class['actual_start_time'] ?? '')),
            'actual_end_time' => $this->normalizeUtc((string) ($class['actual_end_time'] ?? '')),
            'teacher_joined_at' => $this->normalizeUtc((string) ($class['teacher_joined_at'] ?? '')),
            'teacher_email' => (string) ($class['teacher_google_email'] ?? ''),
            'teacher_id' => (int) ($class['teacher_id'] ?? 0),
            'google_conference_id' => (string) ($class['google_conference_id'] ?? ''),
            'google_meet_space_name' => (string) ($class['google_meet_space_name'] ?? ''),
            'google_meeting_code' => (string) ($class['google_meeting_code'] ?? ''),
            'current_utc' => gmdate('Y-m-d H:i:s'),
            'current_local' => formatUtcForTimezone(gmdate('Y-m-d H:i:s'), $scheduledTz, 'Y-m-d H:i:s T'),
        ];
    }

    /**
     * @param array<string, mixed> $classContext
     * @param array<string, mixed> $syncState
     * @param array<string, mixed> $googlePayload
     * @return array<string, mixed>
     */
    public function evaluateCompletionDecision(array $classContext, array $syncState, array $googlePayload = []): array
    {
        $nowUtc = (string) ($classContext['current_utc'] ?? gmdate('Y-m-d H:i:s'));
        $endUtc = (string) ($classContext['end_time_utc'] ?? '');
        $nowTs = strtotime($nowUtc . ' UTC') ?: time();
        $endTs = $endUtc !== '' ? (strtotime($endUtc . ' UTC') ?: null) : null;

        $conditions = [
            'current_utc_after_scheduled_end' => [
                'label' => 'Current UTC > scheduled end_time_utc',
                'result' => $endTs !== null ? $nowTs > $endTs : false,
                'detail' => $endTs !== null
                    ? ('now=' . $nowUtc . ' end=' . $endUtc)
                    : 'missing end_time_utc',
                'used_for_completion' => false,
            ],
            'host_joined_detected' => [
                'label' => 'Host joined (Meet API or teacher_joined_at)',
                'result' => (bool) ($syncState['host_joined'] ?? false),
                'detail' => 'teacher_joined_at=' . (string) ($classContext['teacher_joined_at'] ?? 'null'),
                'used_for_completion' => true,
            ],
            'host_left_meeting' => [
                'label' => 'Host left meeting (no active session + end time)',
                'result' => (bool) ($syncState['host_left'] ?? false),
                'detail' => 'actual_end=' . (string) ($syncState['actual_end_time'] ?? 'null')
                    . ' conference_end=' . (string) ($syncState['conference_end_time'] ?? 'null'),
                'used_for_completion' => true,
            ],
            'conference_status_ended' => [
                'label' => 'Conference record has end_time',
                'result' => !empty($syncState['conference_end_time']),
                'detail' => (string) ($syncState['conference_end_time'] ?? ''),
                'used_for_completion' => true,
            ],
            'meeting_live_status_ended' => [
                'label' => 'meeting_live_status = ended',
                'result' => (string) ($syncState['meeting_live_status'] ?? '') === 'ended',
                'detail' => (string) ($syncState['meeting_live_status'] ?? ''),
                'used_for_completion' => true,
            ],
            'teacher_participant_matched' => [
                'label' => 'Teacher matched in Meet participants list',
                'result' => (bool) ($syncState['teacher_participant_matched'] ?? false),
                'detail' => 'participant_count=' . (string) ($syncState['participant_count'] ?? 0),
                'used_for_completion' => true,
            ],
            'recording_available' => [
                'label' => 'Recording available',
                'result' => trim((string) ($classContext['recording_url'] ?? '')) !== '',
                'detail' => 'not required for status=completed',
                'used_for_completion' => false,
            ],
            'apply_snapshot_would_complete' => [
                'label' => 'applyLiveSnapshot would set status=completed',
                'result' => (bool) ($syncState['would_complete'] ?? false),
                'detail' => (string) ($syncState['would_complete_reason'] ?? ''),
                'used_for_completion' => true,
            ],
        ];

        $failed = [];
        foreach ($conditions as $key => $row) {
            if (!empty($row['used_for_completion']) && empty($row['result'])) {
                $failed[] = $key;
            }
        }

        $cronResult = 'unchanged';
        $previous = (string) ($classContext['status'] ?? '');
        $next = (string) ($syncState['resulting_status'] ?? $previous);
        if ($previous !== 'completed' && $next === 'completed') {
            $cronResult = 'completed';
        } elseif ($previous !== 'ongoing' && $next === 'ongoing') {
            $cronResult = 'started';
        } elseif ($previous === $next) {
            $cronResult = 'unchanged';
        }

        return [
            'conditions' => $conditions,
            'failed_conditions' => $failed,
            'completion_decision' => $cronResult,
            'reason' => $failed === []
                ? 'All required conditions passed for completion.'
                : 'Blocked by: ' . implode(', ', $failed),
            'api_source' => 'Google Meet API v2 (spaces, conferenceRecords, participants, participantSessions). Calendar API is not used for live status.',
        ];
    }

    /**
     * @param array<string, mixed> $classContext
     * @param array<string, mixed> $decision
     * @param array<string, mixed> $googlePayload
     */
    public function logClassSync(array $classContext, array $decision, array $googlePayload = [], string $trigger = 'meet_poll'): void
    {
        SyncLog::write('meeting_status_debug.log', [
            'event' => 'class_sync_debug',
            'trigger' => $trigger,
            'class' => $classContext,
            'completion' => $decision,
            'google_api' => [
                'source' => $decision['api_source'] ?? 'Google Meet API v2',
                'space' => $googlePayload['space'] ?? null,
                'conference' => $googlePayload['conference'] ?? null,
                'participants' => $googlePayload['participants'] ?? null,
                'teacher_participant' => $googlePayload['teacher_participant'] ?? null,
            ],
        ]);

        if ($googlePayload !== []) {
            SyncLog::write('google_meet_response.log', [
                'class_id' => $classContext['class_id'] ?? null,
                'trigger' => $trigger,
                'timestamp' => gmdate('Y-m-d H:i:s'),
                'payload' => $googlePayload,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $class
     * @return array<string, mixed>
     */
    public function verifyTimezoneStorage(array $class): array
    {
        $tz = normalizeTimezone((string) ($class['scheduled_timezone'] ?? $class['timezone'] ?? 'UTC'), 'UTC');
        $startUtc = (string) ($class['start_time_utc'] ?? $class['start_datetime'] ?? '');
        $endUtc = (string) ($class['end_time_utc'] ?? $class['end_datetime'] ?? '');

        $exampleIstStart = null;
        $exampleIstEnd = null;
        if ($startUtc !== '' && $endUtc !== '') {
            try {
                $exampleIstStart = (new DateTimeImmutable($startUtc, new DateTimeZone('UTC')))
                    ->setTimezone(new DateTimeZone('Asia/Kolkata'))
                    ->format('Y-m-d H:i:s T');
                $exampleIstEnd = (new DateTimeImmutable($endUtc, new DateTimeZone('UTC')))
                    ->setTimezone(new DateTimeZone('Asia/Kolkata'))
                    ->format('Y-m-d H:i:s T');
            } catch (\Throwable $ignored) {
            }
        }

        $columnsMatch = ($class['start_datetime'] ?? '') === ($class['start_time_utc'] ?? '')
            && ($class['end_datetime'] ?? '') === ($class['end_time_utc'] ?? '');

        return [
            'scheduled_timezone' => $tz,
            'stored_start_time_utc' => $startUtc,
            'stored_end_time_utc' => $endUtc,
            'displayed_local_start' => formatUtcForTimezone($startUtc, $tz, 'Y-m-d H:i:s T'),
            'displayed_local_end' => formatUtcForTimezone($endUtc, $tz, 'Y-m-d H:i:s T'),
            'example_ist_from_stored_utc' => [
                'start' => $exampleIstStart,
                'end' => $exampleIstEnd,
            ],
            'start_datetime_equals_start_time_utc' => $columnsMatch,
            'note' => $columnsMatch
                ? 'start_datetime and start_time_utc store the same UTC instant (conversion at schedule time uses DateTimeImmutable with selected timezone).'
                : 'start_datetime and start_time_utc differ — investigate legacy rows.',
        ];
    }

    private function normalizeUtc(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

}

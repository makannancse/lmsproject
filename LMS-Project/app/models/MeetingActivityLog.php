<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';

class MeetingActivityLog
{
    public static function upsertSession(
        int $classId,
        ?int $userId,
        string $role,
        string $joinedAt,
        ?string $leftAt,
        ?string $googleParticipantName,
        ?string $googleParticipantSessionName,
        string $source = 'google_meet_api'
    ): void {
        $joinedAt = trim($joinedAt);
        if ($classId <= 0 || $joinedAt === '') {
            return;
        }

        $durationMinutes = null;
        if ($leftAt !== null && trim($leftAt) !== '') {
            $joinedTs = strtotime($joinedAt . ' UTC');
            $leftTs = strtotime(trim($leftAt) . ' UTC');
            if ($joinedTs !== false && $leftTs !== false && $leftTs >= $joinedTs) {
                $durationMinutes = max(0, (int) floor(($leftTs - $joinedTs) / 60));
            }
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO meeting_activity_logs
                (class_id, user_id, google_participant_name, google_participant_session_name, role, joined_at, left_at, duration_minutes, source)
             VALUES
                (:class_id, :user_id, :google_participant_name, :google_participant_session_name, :role, :joined_at, :left_at, :duration_minutes, :source)
             ON DUPLICATE KEY UPDATE
                user_id = COALESCE(VALUES(user_id), meeting_activity_logs.user_id),
                google_participant_name = COALESCE(VALUES(google_participant_name), meeting_activity_logs.google_participant_name),
                role = VALUES(role),
                joined_at = LEAST(meeting_activity_logs.joined_at, VALUES(joined_at)),
                left_at = CASE
                    WHEN VALUES(left_at) IS NULL THEN meeting_activity_logs.left_at
                    WHEN meeting_activity_logs.left_at IS NULL THEN VALUES(left_at)
                    ELSE GREATEST(meeting_activity_logs.left_at, VALUES(left_at))
                END,
                duration_minutes = CASE
                    WHEN VALUES(duration_minutes) IS NULL THEN meeting_activity_logs.duration_minutes
                    ELSE VALUES(duration_minutes)
                END,
                source = VALUES(source),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'class_id' => $classId,
            'user_id' => $userId,
            'google_participant_name' => $googleParticipantName,
            'google_participant_session_name' => $googleParticipantSessionName,
            'role' => in_array($role, ['teacher', 'student', 'unknown'], true) ? $role : 'unknown',
            'joined_at' => $joinedAt,
            'left_at' => $leftAt,
            'duration_minutes' => $durationMinutes,
            'source' => in_array($source, ['google_meet_api', 'workspace_events', 'manual'], true) ? $source : 'google_meet_api',
        ]);
    }
}

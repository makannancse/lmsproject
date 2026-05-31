<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/lib/GoogleCalendarMeetingService.php';

/**
 * Create and return a unique Google Meet meeting for a class.
 *
 * @return array{meet_link:string,event_id:string}
 */
function createGoogleMeetMeeting(
    int $teacherId,
    string $startTime,
    string $endTime,
    string $timezone = 'Asia/Kolkata',
    string $summary = 'LearnWise Class'
): array {
    $service = new GoogleCalendarMeetingService();
    return $service->createMeeting($teacherId, $startTime, $endTime, $timezone, $summary);
}

function deleteGoogleMeetMeeting(int $teacherId, string $eventId): void
{
    $service = new GoogleCalendarMeetingService();
    $service->deleteMeeting($teacherId, $eventId);
}

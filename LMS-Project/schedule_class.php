<?php

declare(strict_types=1);

// Delegates to ClassController::store(), which saves class_sessions, creates a Google Meet
// using the assigned teacher's Google account, stores google_event_id / meeting_link, and sends mail.
// Only admins may schedule classes. Admins can also schedule from /admin/calendar
// (FullCalendar modal -> same POST with calendar_ajax=1).

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/lib/Auth.php';
require_once __DIR__ . '/app/controllers/ClassController.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

Auth::startSession();
ClassController::store();

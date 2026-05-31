<?php

declare(strict_types=1);

/**
 * JSON feed of class_sessions for FullCalendar (same as GET /calendar/events via the app router).
 * Use when the front controller is not mapped to this path.
 *
 * Query: ?start=ISO8601&end=ISO8601
 * Optional (admin only): teacher_id, student_id
 * Requires logged-in session cookie (same-origin fetch).
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/lib/Auth.php';
require_once __DIR__ . '/app/controllers/CalendarController.php';

Auth::startSession();
CalendarController::serveEventsJson();

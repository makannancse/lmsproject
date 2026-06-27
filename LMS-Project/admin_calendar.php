<?php

declare(strict_types=1);

/**
 * Shortcut to the in-app admin calendar (FullCalendar). Prefer /admin/calendar when using public/index.php routing.
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/lib/Auth.php';

Auth::startSession();

redirectTo('admin/calendar');

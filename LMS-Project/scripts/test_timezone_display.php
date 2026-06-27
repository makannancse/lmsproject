<?php

declare(strict_types=1);

require __DIR__ . '/../app/config/config.php';
require __DIR__ . '/../app/lib/helpers.php';

$class = [
    'start_time_utc' => '2026-06-18 07:30:00',
    'end_time_utc' => '2026-06-18 08:30:00',
    'scheduled_timezone' => 'Asia/Kolkata',
];

echo formatClassTimeRange($class) . PHP_EOL;
echo formatClassScheduledAt($class, 'l M j, Y g:i A') . ' – ';
echo formatClassScheduledEndAt($class, 'g:i A') . ' ';
echo formatClassScheduledTimezoneLabel($class) . PHP_EOL;

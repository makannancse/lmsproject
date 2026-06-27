<?php

declare(strict_types=1);

require __DIR__ . '/../app/config/config.php';
require __DIR__ . '/../app/lib/helpers.php';
require __DIR__ . '/../app/lib/ClassRecurrenceHelper.php';

$tz = new DateTimeZone('Asia/Kolkata');
$start = new DateTimeImmutable('2026-06-18 13:00', $tz);
$end = new DateTimeImmutable('2026-06-28 14:00', $tz);
$norm = ClassRecurrenceHelper::normalizeSlotForRecurrence($start, $end, 'daily');
$rec = ClassRecurrenceHelper::parseFromPost([
    'recurrence_rule' => 'daily',
    'recurrence_end_mode' => 'until',
    'recurrence_until' => '',
], $start, $end, 'Asia/Kolkata');
$slots = ClassRecurrenceHelper::buildOccurrences($norm['start'], $norm['end'], 'daily', $rec['end_date'], $rec['count']);

echo 'Occurrences: ' . count($slots) . PHP_EOL;
echo 'End date: ' . ($rec['end_date'] ?? 'null') . PHP_EOL;
foreach ($slots as $slot) {
    echo $slot['start']->format('Y-m-d H:i') . PHP_EOL;
}

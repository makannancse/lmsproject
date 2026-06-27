<?php

declare(strict_types=1);

/**
 * Google Calendar-style recurrence engine for class scheduling.
 */
class ClassRecurrenceHelper
{
    public const MAX_OCCURRENCES = 52;

    /** @var list<string> */
    private const RULES = ['none', 'daily', 'weekdays', 'weekly', 'monthly', 'custom'];

    /**
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable, inferred_until: ?string}
     */
    public static function normalizeSlotForRecurrence(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        string $rule
    ): array {
        if ($rule === '' || $rule === 'none') {
            return ['start' => $start, 'end' => $end, 'inferred_until' => null];
        }

        $startDay = $start->format('Y-m-d');
        $endDay = $end->format('Y-m-d');
        if ($endDay <= $startDay) {
            return ['start' => $start, 'end' => $end, 'inferred_until' => null];
        }

        $endSameDay = $start->setTime(
            (int) $end->format('H'),
            (int) $end->format('i'),
            (int) $end->format('s')
        );
        if ($endSameDay <= $start) {
            $endSameDay = $endSameDay->modify('+1 day');
        }

        return ['start' => $start, 'end' => $endSameDay, 'inferred_until' => $endDay];
    }

    /**
     * @return array{
     *   rule: string,
     *   end_date: ?string,
     *   count: ?int,
     *   end_mode: string,
     *   weekly_interval: int,
     *   weekly_days: list<int>,
     *   monthly_pattern: string,
     *   frequency_db: string,
     *   label: string
     * }
     */
    public static function parseFromPost(
        array $post,
        ?DateTimeImmutable $firstStart = null,
        ?DateTimeImmutable $firstEnd = null,
        ?string $schedulingTimezone = null
    ): array {
        $rule = strtolower(trim((string) ($post['recurrence_rule'] ?? 'none')));
        if (!in_array($rule, self::RULES, true)) {
            $rule = 'none';
        }

        $endMode = strtolower(trim((string) ($post['recurrence_end_mode'] ?? 'until')));
        if (!in_array($endMode, ['never', 'until', 'count'], true)) {
            $endMode = 'until';
        }

        $until = trim((string) ($post['recurrence_until'] ?? ''));
        $countRaw = (int) ($post['recurrence_count'] ?? 0);
        $weeklyInterval = max(1, min(12, (int) ($post['recurrence_weekly_interval'] ?? 1)));
        $monthlyPattern = strtolower(trim((string) ($post['recurrence_monthly_pattern'] ?? 'day')));
        $weeklyDaysRaw = $post['recurrence_weekly_days'] ?? [];
        if (!is_array($weeklyDaysRaw)) {
            $weeklyDaysRaw = [$weeklyDaysRaw];
        }
        $weeklyDays = array_values(array_unique(array_filter(array_map('intval', $weeklyDaysRaw), static fn(int $d): bool => $d >= 1 && $d <= 7)));

        if ($rule === 'weekdays') {
            $weeklyDays = [1, 2, 3, 4, 5];
        } elseif ($rule === 'weekly' || $rule === 'custom') {
            if ($weeklyDays === [] && $firstStart !== null) {
                $weeklyDays = [(int) $firstStart->format('N')];
            }
        }

        $inferredUntil = null;
        if ($rule !== 'none' && $firstStart !== null && $firstEnd !== null) {
            $inferredUntil = self::normalizeSlotForRecurrence($firstStart, $firstEnd, $rule)['inferred_until'];
        }

        $endDate = null;
        $count = null;
        if ($rule !== 'none') {
            if ($endMode === 'count' && $countRaw >= 2) {
                $count = min($countRaw, self::MAX_OCCURRENCES);
            } elseif ($endMode === 'until' && $until !== '') {
                try {
                    $tz = new DateTimeZone(normalizeTimezone($schedulingTimezone ?? APP_TIMEZONE));
                    $endDate = (new DateTimeImmutable($until, $tz))->format('Y-m-d');
                } catch (\Throwable) {
                    $endDate = null;
                }
            } elseif ($inferredUntil !== null) {
                $endDate = $inferredUntil;
            } elseif ($endMode !== 'never' && $count === null && $endDate === null) {
                $count = 4;
            }
        }

        $frequencyDb = match ($rule) {
            'daily', 'weekdays' => 'daily',
            'weekly', 'custom' => 'weekly',
            'monthly' => 'monthly',
            default => 'daily',
        };

        return [
            'rule' => $rule,
            'end_date' => $endDate,
            'count' => $count,
            'end_mode' => $endMode,
            'weekly_interval' => $weeklyInterval,
            'weekly_days' => $weeklyDays,
            'monthly_pattern' => $monthlyPattern,
            'frequency_db' => $frequencyDb,
            'label' => self::describeRule($rule, $weeklyInterval, $weeklyDays, $monthlyPattern),
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return list<array{start: DateTimeImmutable, end: DateTimeImmutable}>
     */
    public static function buildOccurrencesFromConfig(
        DateTimeImmutable $firstStart,
        DateTimeImmutable $firstEnd,
        array $config
    ): array {
        $rule = (string) ($config['rule'] ?? 'none');
        if ($rule === '' || $rule === 'none') {
            return [['start' => $firstStart, 'end' => $firstEnd]];
        }

        return self::buildOccurrences(
            $firstStart,
            $firstEnd,
            $rule,
            $config['end_date'] ?? null,
            $config['count'] ?? null,
            (int) ($config['weekly_interval'] ?? 1),
            (array) ($config['weekly_days'] ?? []),
            (string) ($config['monthly_pattern'] ?? 'day')
        );
    }

    /**
     * @param list<int> $weeklyDays ISO-8601 weekday numbers 1=Mon … 7=Sun
     * @return list<array{start: DateTimeImmutable, end: DateTimeImmutable}>
     */
    public static function buildOccurrences(
        DateTimeImmutable $firstStart,
        DateTimeImmutable $firstEnd,
        string $rule,
        ?string $endDateYmd,
        ?int $occurrenceCount,
        int $weeklyInterval = 1,
        array $weeklyDays = [],
        string $monthlyPattern = 'day'
    ): array {
        $durationSec = max(60, $firstEnd->getTimestamp() - $firstStart->getTimestamp());
        $until = null;
        if ($endDateYmd !== null && $endDateYmd !== '') {
            try {
                $until = new DateTimeImmutable($endDateYmd . ' 23:59:59', $firstStart->getTimezone());
            } catch (\Throwable) {
                $until = null;
            }
        }

        $slots = [['start' => $firstStart, 'end' => $firstEnd]];
        if ($rule === 'daily') {
            return self::expandDaily($firstStart, $firstEnd, $durationSec, $until, $occurrenceCount, $slots);
        }
        if ($rule === 'weekdays') {
            return self::expandWeekdays($firstStart, $firstEnd, $durationSec, $until, $occurrenceCount, $slots);
        }
        if ($rule === 'weekly' || $rule === 'custom') {
            return self::expandWeekly($firstStart, $firstEnd, $durationSec, $until, $occurrenceCount, $weeklyInterval, $weeklyDays, $slots);
        }
        if ($rule === 'monthly') {
            return self::expandMonthly($firstStart, $firstEnd, $durationSec, $until, $occurrenceCount, $monthlyPattern, $slots);
        }

        return $slots;
    }

    /**
     * @param list<array{start: DateTimeImmutable, end: DateTimeImmutable}> $slots
     * @return list<array{start: DateTimeImmutable, end: DateTimeImmutable}>
     */
    private static function expandDaily(
        DateTimeImmutable $firstStart,
        DateTimeImmutable $firstEnd,
        int $durationSec,
        ?DateTimeImmutable $until,
        ?int $occurrenceCount,
        array $slots
    ): array {
        $currentStart = $firstStart;
        $total = 1;
        while ($total < self::MAX_OCCURRENCES) {
            if ($occurrenceCount !== null && $total >= $occurrenceCount) {
                break;
            }
            $currentStart = $currentStart->modify('+1 day');
            if ($until !== null && $currentStart > $until) {
                break;
            }
            $slots[] = ['start' => $currentStart, 'end' => $currentStart->modify('+' . $durationSec . ' seconds')];
            $total++;
        }

        return $slots;
    }

    /**
     * @param list<array{start: DateTimeImmutable, end: DateTimeImmutable}> $slots
     * @return list<array{start: DateTimeImmutable, end: DateTimeImmutable}>
     */
    private static function expandWeekdays(
        DateTimeImmutable $firstStart,
        DateTimeImmutable $firstEnd,
        int $durationSec,
        ?DateTimeImmutable $until,
        ?int $occurrenceCount,
        array $slots
    ): array {
        return self::expandWeekly($firstStart, $firstEnd, $durationSec, $until, $occurrenceCount, 1, [1, 2, 3, 4, 5], $slots);
    }

    /**
     * @param list<int> $weeklyDays
     * @param list<array{start: DateTimeImmutable, end: DateTimeImmutable}> $slots
     * @return list<array{start: DateTimeImmutable, end: DateTimeImmutable}>
     */
    private static function expandWeekly(
        DateTimeImmutable $firstStart,
        DateTimeImmutable $firstEnd,
        int $durationSec,
        ?DateTimeImmutable $until,
        ?int $occurrenceCount,
        int $weeklyInterval,
        array $weeklyDays,
        array $slots
    ): array {
        if ($weeklyDays === []) {
            $weeklyDays = [(int) $firstStart->format('N')];
        }

        $current = $firstStart;
        $total = count($slots);
        $maxDays = self::MAX_OCCURRENCES * 7 * max(1, $weeklyInterval) + 7;

        for ($i = 0; $i < $maxDays && $total < self::MAX_OCCURRENCES; $i++) {
            if ($occurrenceCount !== null && $total >= $occurrenceCount) {
                break;
            }
            $current = $current->modify('+1 day');
            if ($until !== null && $current > $until) {
                break;
            }

            $daysSinceStart = (int) floor(($current->getTimestamp() - $firstStart->getTimestamp()) / 86400);
            $weekIndex = intdiv(max(0, $daysSinceStart), 7);
            if ($weekIndex % max(1, $weeklyInterval) !== 0) {
                continue;
            }
            if (!in_array((int) $current->format('N'), $weeklyDays, true)) {
                continue;
            }

            $slotStart = $current->setTime(
                (int) $firstStart->format('H'),
                (int) $firstStart->format('i'),
                (int) $firstStart->format('s')
            );
            $slots[] = [
                'start' => $slotStart,
                'end' => $slotStart->modify('+' . $durationSec . ' seconds'),
            ];
            $total++;
        }

        usort($slots, static fn(array $a, array $b): int => $a['start'] <=> $b['start']);

        return $slots;
    }

    /**
     * @param list<array{start: DateTimeImmutable, end: DateTimeImmutable}> $slots
     * @return list<array{start: DateTimeImmutable, end: DateTimeImmutable}>
     */
    private static function expandMonthly(
        DateTimeImmutable $firstStart,
        DateTimeImmutable $firstEnd,
        int $durationSec,
        ?DateTimeImmutable $until,
        ?int $occurrenceCount,
        string $monthlyPattern,
        array $slots
    ): array {
        $total = 1;
        $cursorMonth = $firstStart;

        while ($total < self::MAX_OCCURRENCES) {
            if ($occurrenceCount !== null && $total >= $occurrenceCount) {
                break;
            }

            $cursorMonth = $cursorMonth->modify('first day of next month');
            $nextStart = self::resolveMonthlyOccurrence($cursorMonth, $firstStart, $monthlyPattern);
            if ($nextStart === null) {
                break;
            }
            $nextStart = $nextStart->setTime(
                (int) $firstStart->format('H'),
                (int) $firstStart->format('i'),
                (int) $firstStart->format('s')
            );
            if ($until !== null && $nextStart > $until) {
                break;
            }
            if ($nextStart <= $firstStart) {
                continue;
            }

            $slots[] = [
                'start' => $nextStart,
                'end' => $nextStart->modify('+' . $durationSec . ' seconds'),
            ];
            $total++;
        }

        return $slots;
    }

    private static function resolveMonthlyOccurrence(
        DateTimeImmutable $month,
        DateTimeImmutable $anchor,
        string $pattern
    ): ?DateTimeImmutable {
        $pattern = strtolower(trim($pattern));
        if ($pattern === '' || $pattern === 'day') {
            $day = (int) $anchor->format('j');
            $daysInMonth = (int) $month->format('t');

            return $month->setDate((int) $month->format('Y'), (int) $month->format('n'), min($day, $daysInMonth));
        }

        if (preg_match('/^(first|second|third|fourth|last)_?(monday|tuesday|wednesday|thursday|friday|saturday|sunday)$/i', $pattern, $m)) {
            $ordinal = strtolower($m[1]);
            $weekday = strtolower($m[2]);
            $weekdayMap = [
                'monday' => 'monday', 'tuesday' => 'tuesday', 'wednesday' => 'wednesday',
                'thursday' => 'thursday', 'friday' => 'friday', 'saturday' => 'saturday', 'sunday' => 'sunday',
            ];
            $target = $weekdayMap[$weekday] ?? 'monday';
            $firstOfMonth = $month->modify('first day of this month');
            if ($ordinal === 'last') {
                $candidate = $firstOfMonth->modify('last ' . $target . ' of this month');
            } else {
                $modifier = match ($ordinal) {
                    'second' => 'second',
                    'third' => 'third',
                    'fourth' => 'fourth',
                    default => 'first',
                };
                $candidate = $firstOfMonth->modify($modifier . ' ' . $target . ' of this month');
            }

            return $candidate;
        }

        return null;
    }

    /**
     * @param list<int> $weeklyDays
     */
    public static function describeRule(string $rule, int $weeklyInterval, array $weeklyDays, string $monthlyPattern): string
    {
        return match ($rule) {
            'daily' => 'Daily',
            'weekdays' => 'Every Weekday (Mon–Fri)',
            'weekly' => 'Every ' . ($weeklyInterval > 1 ? $weeklyInterval . ' Weeks' : 'Week'),
            'custom' => 'Custom weekly schedule',
            'monthly' => 'Monthly' . ($monthlyPattern !== 'day' ? ' (' . str_replace('_', ' ', $monthlyPattern) . ')' : ''),
            default => 'Does not repeat',
        };
    }

    /**
     * @param array<string, mixed> $classRow
     */
    public static function seriesParentId(array $classRow): int
    {
        $parent = (int) ($classRow['recurrence_parent_id'] ?? 0);
        if ($parent > 0) {
            return $parent;
        }

        return (int) ($classRow['id'] ?? 0);
    }

    /**
     * @return list<int>
     */
    public static function futureSeriesClassIds(int $classId, \PDO $pdo): array
    {
        $stmt = $pdo->prepare('SELECT * FROM class_sessions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $classId]);
        $class = $stmt->fetch();
        if (!$class) {
            return [];
        }

        $parentId = self::seriesParentId($class);
        $startUtc = (string) classStartUtcValue($class);
        $listStmt = $pdo->prepare(
            'SELECT id FROM class_sessions
             WHERE (id = :parent OR recurrence_parent_id = :parent2)
               AND start_time_utc >= :start_utc
               AND status IN ("scheduled", "rescheduled")
             ORDER BY start_time_utc ASC'
        );
        $listStmt->execute([
            'parent' => $parentId,
            'parent2' => $parentId,
            'start_utc' => $startUtc,
        ]);

        return array_map(static fn(array $r): int => (int) $r['id'], $listStmt->fetchAll() ?: []);
    }

    /**
     * @return list<int>
     */
    public static function allSeriesClassIds(int $classId, \PDO $pdo): array
    {
        $stmt = $pdo->prepare('SELECT * FROM class_sessions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $classId]);
        $class = $stmt->fetch();
        if (!$class) {
            return [];
        }

        $parentId = self::seriesParentId($class);
        $listStmt = $pdo->prepare(
            'SELECT id FROM class_sessions
             WHERE (id = :parent OR recurrence_parent_id = :parent2)
               AND status IN ("scheduled", "rescheduled", "ongoing", "completed", "cancelled")
             ORDER BY start_time_utc ASC'
        );
        $listStmt->execute([
            'parent' => $parentId,
            'parent2' => $parentId,
        ]);

        return array_map(static fn(array $r): int => (int) $r['id'], $listStmt->fetchAll() ?: []);
    }
}

<?php

declare(strict_types=1);

if (!function_exists('parseInrAmount')) {
    /**
     * Parse a user-entered INR amount without currency conversion.
     * Strips grouping separators and currency symbols; stores exact decimal value.
     */
    function parseInrAmount(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_string($value)) {
            $normalized = str_replace(["\xC2\xA0", ' '], '', trim($value));
            $normalized = str_replace([',', '₹', 'INR', 'inr'], '', $normalized);
            $value = $normalized;
        }

        if (!is_numeric($value)) {
            return 0.0;
        }

        return round((float) $value, 2);
    }
}

if (!function_exists('formatCurrency')) {
    function formatCurrency($amount): string
    {
        return '₹' . number_format(parseInrAmount($amount), 2);
    }
}

if (!function_exists('classStatusBadgeClass')) {
    function classStatusBadgeClass(string $status): string
    {
        $map = [
            'scheduled' => 'text-bg-primary',
            'rescheduled' => 'text-bg-info',
            'ongoing' => 'text-bg-warning',
            'completed' => 'text-bg-success',
            'cancelled' => 'text-bg-danger',
        ];

        return $map[$status] ?? 'text-bg-secondary';
    }
}

if (!function_exists('normalizeUrlPath')) {
    /**
     * Normalize a route path segment (no leading slash in return for joining).
     */
    function normalizeUrlPath(string $path): string
    {
        return trim(str_replace('\\', '/', $path), '/');
    }
}

if (!function_exists('appBasePath')) {
    /**
     * Web path prefix derived from APP_URL (e.g. /lmsproject/LMS-Project/public) or BASE_PATH fallback.
     */
    function appBasePath(): string
    {
        return appWebPath();
    }
}

if (!function_exists('appWebPath')) {
    function appWebPath(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $webPath = '';
        if (defined('APP_URL') && APP_URL !== '') {
            $parsed = parse_url((string) APP_URL);
            $webPath = is_array($parsed) ? rtrim((string) ($parsed['path'] ?? ''), '/') : '';
        }

        if ($webPath === '' && defined('BASE_PATH')) {
            $webPath = rtrim((string) BASE_PATH, '/');
        }

        $cached = $webPath;

        return $cached;
    }
}

if (!function_exists('url')) {
    /**
     * Absolute application URL from APP_URL + path.
     * url('login') → http://localhost/.../public/login or https://www.edulearnwise.com/login
     */
    function url(string $path = ''): string
    {
        $root = rtrim((string) (defined('APP_URL') ? APP_URL : ''), '/');
        if ($root === '' || !filter_var($root, FILTER_VALIDATE_URL)) {
            return path($path);
        }

        $parsed = parse_url($root);
        $origin = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? 'localhost');
        if (!empty($parsed['port'])) {
            $origin .= ':' . $parsed['port'];
        }

        $route = $path === '/' ? '' : $path;

        return $origin . path($route);
    }
}

if (!function_exists('path')) {
    /**
     * Same-origin path (no scheme/host) for forms, links, and assets.
     */
    function path(string $route = ''): string
    {
        $webPath = appWebPath();
        $segment = normalizeUrlPath($route);
        if ($segment === '') {
            return $webPath === '' ? '/' : ($webPath . '/');
        }

        return ($webPath === '' ? '' : $webPath) . '/' . $segment;
    }
}

if (!function_exists('ensureUploadDirectories')) {
    /**
     * Ensure writable upload directories exist (homework, reports, etc.).
     */
    function ensureUploadDirectories(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $root = dirname(__DIR__, 2);
        foreach (['uploads/homework', 'uploads/homework_submissions', 'uploads/reports'] as $subdir) {
            $dir = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subdir);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
    }
}

if (!function_exists('asset')) {
    function asset(string $file): string
    {
        return path('assets/' . ltrim(normalizeUrlPath($file), 'assets/'));
    }
}

if (!function_exists('appUrl')) {
    /** @deprecated Use url() */
    function appUrl(string $path = '/'): string
    {
        return url(normalizeUrlPath($path === '/' ? '' : ltrim($path, '/')));
    }
}

if (!function_exists('appRelativeUrl')) {
    /** @deprecated Use path() */
    function appRelativeUrl(string $path = '/'): string
    {
        return path($path === '/' ? '' : ltrim($path, '/'));
    }
}

if (!function_exists('googleOAuthRedirectUri')) {
    function googleOAuthRedirectUri(): string
    {
        $configured = trim((string) env('GOOGLE_REDIRECT_URI', ''));
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_URL)) {
            return $configured;
        }

        return url('auth/google/callback');
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirect to an absolute URL or application route path.
     *
     * @param array<string, mixed> $logContext
     */
    function redirect(string $target, int $statusCode = 302, array $logContext = []): void
    {
        if (str_starts_with($target, 'http://') || str_starts_with($target, 'https://')) {
            $location = $target;
        } else {
            $parts = explode('?', $target, 2);
            $location = path(ltrim($parts[0], '/'));
            if (isset($parts[1]) && $parts[1] !== '') {
                $location .= '?' . $parts[1];
            }
        }

        if ($logContext !== [] && function_exists('logAdminLogin')) {
            logAdminLogin(array_merge($logContext, [
                'redirect_url' => $location,
                'relative_path' => $location,
                'http_status' => $statusCode,
            ]));
        }

        header('Location: ' . $location, true, $statusCode);
        exit;
    }
}

if (!function_exists('logGoogleAuth')) {
    /**
     * @param array<string, mixed> $context
     */
    function logGoogleAuth(array $context): void
    {
        writeStructuredLog('google_auth_debug.log', $context);
    }
}

if (!function_exists('logClassScheduleLive')) {
    /**
     * @param array<string, mixed> $context
     */
    function logClassScheduleLive(array $context): void
    {
        writeStructuredLog('class_schedule_live.log', $context);
    }
}

if (!function_exists('logUserEdit')) {
    /**
     * @param array<string, mixed> $context
     */
    function logUserEdit(array $context): void
    {
        writeStructuredLog('user_edit_debug.log', $context);
    }
}

if (!function_exists('redirectTo')) {
    /**
     * @param array<string, mixed> $logContext
     */
    function redirectTo(string $route, int $statusCode = 302, array $logContext = []): void
    {
        redirect($route, $statusCode, $logContext);
    }
}

if (!function_exists('logAdminLogin')) {
    /**
     * @param array<string, mixed> $context
     */
    function logAdminLogin(array $context): void
    {
        $context['request_uri'] = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $context['script_name'] = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $context['base_path'] = appBasePath();
        $context['app_url'] = (string) env('APP_URL', '');
        $context['https'] = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $context['forwarded_proto'] = (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');

        writeStructuredLog('admin_login_debug.log', $context);
    }
}

if (!function_exists('writeStructuredLog')) {
    /**
     * @param array<string, mixed> $context
     */
    function writeStructuredLog(string $fileName, array $context): void
    {
        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $context['timestamp'] = gmdate('Y-m-d H:i:s');
        @file_put_contents(
            $dir . DIRECTORY_SEPARATOR . $fileName,
            json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND
        );
    }
}

if (!function_exists('logTimezoneFix')) {
    /**
     * @param array<string, mixed> $context
     */
    function logTimezoneFix(array $context): void
    {
        writeStructuredLog('timezone_fix.log', $context);
    }
}

if (!function_exists('logTimezoneConversion')) {
    /**
     * @param array<string, mixed> $context
     */
    function logTimezoneConversion(array $context): void
    {
        writeStructuredLog('timezone_conversion.log', $context);
    }
}

if (!function_exists('logMeetingHost')) {
    /**
     * @param array<string, mixed> $context
     */
    function logMeetingHost(array $context): void
    {
        writeStructuredLog('meeting_host.log', $context);
        writeStructuredLog('meeting_host_fix.log', $context);
    }
}

if (!function_exists('timezoneAliasMap')) {
    /**
     * @return array<string, string>
     */
    function timezoneAliasMap(): array
    {
        return [
            'UTC' => 'UTC',
            'GMT' => 'Etc/GMT',
            'IST' => 'Asia/Kolkata',
            'EST' => 'America/New_York',
            'PST' => 'America/Los_Angeles',
        ];
    }
}

if (!function_exists('normalizeTimezone')) {
    function normalizeTimezone(?string $timezone, ?string $fallback = 'UTC'): string
    {
        $fallback = $fallback !== null && trim($fallback) !== '' ? $fallback : 'UTC';
        $fallback = trim($fallback);
        $value = trim((string) $timezone);

        if ($value === '') {
            return $fallback;
        }

        $aliasMap = timezoneAliasMap();
        $candidate = $aliasMap[strtoupper($value)] ?? $value;

        try {
            new DateTimeZone($candidate);
            return $candidate;
        } catch (Throwable $e) {
            logTimezoneFix([
                'event' => 'invalid_timezone_fallback',
                'requested_timezone' => $value,
                'fallback_timezone' => $fallback,
                'error' => $e->getMessage(),
            ]);
            logTimezoneConversion([
                'event' => 'invalid_timezone_fallback',
                'requested_timezone' => $value,
                'fallback_timezone' => $fallback,
                'error' => $e->getMessage(),
            ]);
            return $fallback;
        }
    }
}

if (!function_exists('resolveUserTimezone')) {
    /**
     * @param array<string, mixed>|null $user
     */
    function resolveUserTimezone(?array $user = null, string $fallback = 'UTC'): string
    {
        $timezone = $user['timezone'] ?? ($_SESSION['user']['timezone'] ?? $fallback);
        return normalizeTimezone(is_string($timezone) ? $timezone : $fallback, $fallback);
    }
}

if (!function_exists('utcDateTimeImmutable')) {
    function utcDateTimeImmutable(?string $value): ?DateTimeImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            logTimezoneFix([
                'event' => 'invalid_utc_datetime',
                'value' => $value,
                'error' => $e->getMessage(),
            ]);
            logTimezoneConversion([
                'event' => 'invalid_utc_datetime',
                'value' => $value,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}

if (!function_exists('formatUtcForTimezone')) {
    function formatUtcForTimezone(?string $utcValue, ?string $timezone = null, string $format = 'd M Y h:i A T'): string
    {
        $dt = utcDateTimeImmutable($utcValue);
        if ($dt === null) {
            return '';
        }

        $targetTimezone = normalizeTimezone($timezone ?? 'UTC');
        return $dt->setTimezone(new DateTimeZone($targetTimezone))->format($format);
    }
}

if (!function_exists('utcToIso8601Z')) {
    function utcToIso8601Z(?string $utcValue): string
    {
        $dt = utcDateTimeImmutable($utcValue);
        if ($dt === null) {
            return '';
        }

        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}

if (!function_exists('utcToTimezoneIso8601')) {
    function utcToTimezoneIso8601(?string $utcValue, ?string $timezone = null): string
    {
        $dt = utcDateTimeImmutable($utcValue);
        if ($dt === null) {
            return '';
        }

        $targetTimezone = normalizeTimezone($timezone ?? 'UTC');
        return $dt->setTimezone(new DateTimeZone($targetTimezone))->format('Y-m-d\TH:i:sP');
    }
}

if (!function_exists('localToUtcString')) {
    function localToUtcString(string $localValue, string $timezone): string
    {
        $tz = new DateTimeZone(normalizeTimezone($timezone));
        $dt = new DateTimeImmutable($localValue, $tz);
        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}

if (!function_exists('supportedSchedulingTimezones')) {
    /**
     * @return list<array{value:string,label:string}>
     */
    function supportedSchedulingTimezones(): array
    {
        return [
            ['value' => 'UTC', 'label' => 'UTC (Coordinated Universal Time)'],
            ['value' => 'Asia/Kolkata', 'label' => 'Asia/Kolkata (IST — India)'],
            ['value' => 'America/Los_Angeles', 'label' => 'America/Los_Angeles (PST/PDT)'],
            ['value' => 'America/Denver', 'label' => 'America/Denver (MST/MDT)'],
            ['value' => 'America/Chicago', 'label' => 'America/Chicago (CST/CDT)'],
            ['value' => 'America/New_York', 'label' => 'America/New_York (EST/EDT)'],
            ['value' => 'Europe/London', 'label' => 'Europe/London (GMT/BST — UK)'],
            ['value' => 'Europe/Paris', 'label' => 'Europe/Paris (CET — Central Europe)'],
            ['value' => 'Europe/Berlin', 'label' => 'Europe/Berlin (CET — Germany)'],
            ['value' => 'Asia/Dubai', 'label' => 'Asia/Dubai (GST — UAE)'],
            ['value' => 'Asia/Singapore', 'label' => 'Asia/Singapore (SGT)'],
            ['value' => 'Australia/Sydney', 'label' => 'Australia/Sydney (AEST)'],
            ['value' => 'Australia/Melbourne', 'label' => 'Australia/Melbourne (AEST)'],
        ];
    }
}

if (!function_exists('calendarTimezoneOptions')) {
    /**
     * @return list<array{value:string,label:string}>
     */
    function calendarTimezoneOptions(?string $userTimezone = null): array
    {
        $options = supportedSchedulingTimezones();

        $normalizedUserTimezone = $userTimezone !== null && trim($userTimezone) !== ''
            ? normalizeTimezone($userTimezone)
            : null;

        if ($normalizedUserTimezone !== null) {
            $exists = false;
            foreach ($options as $option) {
                if ($option['value'] === $normalizedUserTimezone) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $options[] = [
                    'value' => $normalizedUserTimezone,
                    'label' => 'My Timezone (' . $normalizedUserTimezone . ')',
                ];
            }
        }

        return $options;
    }
}

if (!function_exists('classScheduledTimezone')) {
    /**
     * @param array<string, mixed>|null $classRow
     */
    function classScheduledTimezone(?array $classRow, string $fallback = 'UTC'): string
    {
        if ($classRow === null) {
            return normalizeTimezone($fallback, 'UTC');
        }

        return normalizeTimezone(
            (string) ($classRow['scheduled_timezone'] ?? $classRow['timezone'] ?? $fallback),
            $fallback
        );
    }
}

if (!function_exists('classStartUtcValue')) {
    /**
     * @param array<string, mixed>|null $classRow
     */
    function classStartUtcValue(?array $classRow): ?string
    {
        if ($classRow === null) {
            return null;
        }

        $value = $classRow['start_time_utc'] ?? $classRow['start_datetime'] ?? $classRow['scheduled_time_utc'] ?? null;
        $value = is_string($value) ? trim($value) : '';
        return $value !== '' ? $value : null;
    }
}

if (!function_exists('classEndUtcValue')) {
    /**
     * @param array<string, mixed>|null $classRow
     */
    function classEndUtcValue(?array $classRow): ?string
    {
        if ($classRow === null) {
            return null;
        }

        $value = $classRow['end_time_utc'] ?? $classRow['end_datetime'] ?? null;
        $value = is_string($value) ? trim($value) : '';
        return $value !== '' ? $value : null;
    }
}

if (!function_exists('formatClassScheduledAt')) {
    /**
     * @param array<string, mixed>|null $classRow
     */
    function formatClassScheduledAt(?array $classRow, string $format = 'd M Y h:i A T'): string
    {
        return formatUtcForTimezone(
            classStartUtcValue($classRow),
            classScheduledTimezone($classRow, APP_TIMEZONE),
            $format
        );
    }
}

if (!function_exists('formatClassScheduledTimezoneLabel')) {
    /**
     * @param array<string, mixed>|null $classRow
     */
    function formatClassScheduledTimezoneLabel(?array $classRow): string
    {
        return schedulingTimezoneAbbreviation(classScheduledTimezone($classRow, APP_TIMEZONE));
    }
}

if (!function_exists('schedulingTimezoneAbbreviation')) {
    function schedulingTimezoneAbbreviation(?string $timezone): string
    {
        $timezone = normalizeTimezone($timezone ?? APP_TIMEZONE, APP_TIMEZONE);
        static $map = [
            'UTC' => 'UTC',
            'Asia/Kolkata' => 'IST',
            'Asia/Dubai' => 'GST',
            'America/New_York' => 'EST',
            'America/Chicago' => 'CST',
            'America/Denver' => 'MST',
            'America/Los_Angeles' => 'PST',
            'Europe/London' => 'GMT',
            'Europe/Paris' => 'CET',
            'Asia/Singapore' => 'SGT',
            'Australia/Sydney' => 'AEST',
        ];

        if (isset($map[$timezone])) {
            return $map[$timezone];
        }

        $abbr = formatUtcForTimezone(gmdate('Y-m-d H:i:s'), $timezone, 'T');

        return $abbr !== '' ? $abbr : $timezone;
    }
}

if (!function_exists('formatClassScheduledEndAt')) {
    /**
     * @param array<string, mixed>|null $classRow
     */
    function formatClassScheduledEndAt(?array $classRow, string $format = 'd M Y h:i A'): string
    {
        return formatUtcForTimezone(
            classEndUtcValue($classRow),
            classScheduledTimezone($classRow, APP_TIMEZONE),
            $format
        );
    }
}

if (!function_exists('formatClassTimeRange')) {
    /**
     * Human-readable scheduled class window in the class timezone (never UTC unless scheduled as UTC).
     *
     * @param array<string, mixed>|null $classRow
     */
    function formatClassTimeRange(?array $classRow, string $dateFormat = 'l M j, Y', string $timeFormat = 'g:i A'): string
    {
        if ($classRow === null) {
            return '';
        }

        $timezone = classScheduledTimezone($classRow, APP_TIMEZONE);
        $abbr = schedulingTimezoneAbbreviation($timezone);
        $startDate = formatUtcForTimezone(classStartUtcValue($classRow), $timezone, $dateFormat);
        $startTime = formatUtcForTimezone(classStartUtcValue($classRow), $timezone, $timeFormat);
        $endTime = formatUtcForTimezone(classEndUtcValue($classRow), $timezone, $timeFormat);

        if ($startDate === '' || $startTime === '' || $endTime === '') {
            return '';
        }

        return $startDate . "\n" . $startTime . ' – ' . $endTime . ' ' . $abbr;
    }
}

if (!function_exists('classActualStartUtcValue')) {
    /**
     * @param array<string, mixed>|null $classRow
     */
    function classActualStartUtcValue(?array $classRow): ?string
    {
        if ($classRow === null) {
            return null;
        }

        $value = $classRow['actual_start_time'] ?? null;
        $value = is_string($value) ? trim($value) : '';
        return $value !== '' ? $value : null;
    }
}

if (!function_exists('classActualEndUtcValue')) {
    /**
     * @param array<string, mixed>|null $classRow
     */
    function classActualEndUtcValue(?array $classRow): ?string
    {
        if ($classRow === null) {
            return null;
        }

        $value = $classRow['actual_end_time'] ?? null;
        $value = is_string($value) ? trim($value) : '';
        return $value !== '' ? $value : null;
    }
}

if (!function_exists('classActualDurationMinutes')) {
    /**
     * @param array<string, mixed>|null $classRow
     */
    function classActualDurationMinutes(?array $classRow): ?int
    {
        if ($classRow === null) {
            return null;
        }

        $hasActualBounds = classActualStartUtcValue($classRow) !== null
            && classActualEndUtcValue($classRow) !== null;
        $minutes = $classRow['actual_duration_minutes'] ?? $classRow['actual_duration'] ?? null;
        if ($hasActualBounds && $minutes !== null && $minutes !== '' && is_numeric((string) $minutes)) {
            return max(0, (int) $minutes);
        }

        $start = utcDateTimeImmutable(classActualStartUtcValue($classRow));
        $end = utcDateTimeImmutable(classActualEndUtcValue($classRow));
        if ($start === null || $end === null || $end < $start) {
            return null;
        }

        return max(0, (int) round(($end->getTimestamp() - $start->getTimestamp()) / 60));
    }
}

if (!function_exists('formatDurationMinutes')) {
    function formatDurationMinutes(?int $minutes, string $fallback = '-'): string
    {
        if ($minutes === null) {
            return $fallback;
        }

        return $minutes . ' min';
    }
}

if (!function_exists('formatClassActualAt')) {
    /**
     * @param array<string, mixed>|null $classRow
     */
    function formatClassActualAt(
        ?array $classRow,
        string $which = 'start',
        ?string $timezone = null,
        string $format = 'd M Y h:i A T'
    ): string {
        $utcValue = $which === 'end'
            ? classActualEndUtcValue($classRow)
            : classActualStartUtcValue($classRow);

        return formatUtcForTimezone(
            $utcValue,
            $timezone ?? classScheduledTimezone($classRow, APP_TIMEZONE),
            $format
        );
    }
}

if (!function_exists('formatClassActualTimezoneLabel')) {
    /**
     * @param array<string, mixed>|null $classRow
     */
    function formatClassActualTimezoneLabel(?array $classRow, ?string $timezone = null): string
    {
        $referenceUtc = classActualStartUtcValue($classRow)
            ?? classActualEndUtcValue($classRow)
            ?? classStartUtcValue($classRow);
        $targetTimezone = normalizeTimezone($timezone ?? classScheduledTimezone($classRow, APP_TIMEZONE), APP_TIMEZONE);
        $abbr = formatUtcForTimezone($referenceUtc, $targetTimezone, 'T');

        return $abbr !== '' ? ($abbr . ' (' . $targetTimezone . ')') : $targetTimezone;
    }
}

if (!function_exists('homeworkDueTimezone')) {
    /**
     * @param array<string, mixed>|null $homeworkRow
     */
    function homeworkDueTimezone(?array $homeworkRow, string $fallback = 'UTC'): string
    {
        if ($homeworkRow === null) {
            return normalizeTimezone($fallback, 'UTC');
        }

        return normalizeTimezone(
            (string) ($homeworkRow['due_timezone'] ?? $homeworkRow['teacher_timezone'] ?? $fallback),
            $fallback
        );
    }
}

if (!function_exists('formatHomeworkDueAt')) {
    /**
     * @param array<string, mixed>|null $homeworkRow
     */
    function formatHomeworkDueAt(?array $homeworkRow, string $format = 'd M Y h:i A T'): string
    {
        if ($homeworkRow === null) {
            return '';
        }

        return formatUtcForTimezone(
            isset($homeworkRow['due_date']) ? (string) $homeworkRow['due_date'] : null,
            homeworkDueTimezone($homeworkRow, APP_TIMEZONE),
            $format
        );
    }
}

if (!function_exists('formatHomeworkDueTimezoneLabel')) {
    /**
     * @param array<string, mixed>|null $homeworkRow
     */
    function formatHomeworkDueTimezoneLabel(?array $homeworkRow): string
    {
        if ($homeworkRow === null) {
            return '';
        }

        $timezone = homeworkDueTimezone($homeworkRow, APP_TIMEZONE);
        $abbr = formatUtcForTimezone(
            isset($homeworkRow['due_date']) ? (string) $homeworkRow['due_date'] : gmdate('Y-m-d H:i:s'),
            $timezone,
            'T'
        );

        return $abbr !== '' ? ($abbr . ' (' . $timezone . ')') : $timezone;
    }
}

if (!function_exists('formatRescheduleLocalDateTime')) {
    function formatRescheduleLocalDateTime(
        ?string $dateValue,
        ?string $timeValue,
        ?string $timezone,
        string $format = 'd M Y h:i A'
    ): string {
        $dateValue = trim((string) $dateValue);
        $timeValue = trim((string) $timeValue);
        if ($dateValue === '' || $timeValue === '') {
            return '';
        }

        if (strlen($timeValue) === 5) {
            $timeValue .= ':00';
        }

        try {
            $dt = new DateTimeImmutable(
                $dateValue . ' ' . $timeValue,
                new DateTimeZone(normalizeTimezone($timezone ?? APP_TIMEZONE, APP_TIMEZONE))
            );

            return $dt->format($format);
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('isTeacherHostActiveForClass')) {
    /**
     * @param array<string, mixed> $class
     */
    function isTeacherHostActiveForClass(array $class): bool
    {
        $liveStatus = strtolower(trim((string) ($class['meeting_live_status'] ?? '')));
        if ($liveStatus === 'active') {
            return true;
        }

        $status = strtolower(trim((string) ($class['status'] ?? '')));
        if ($status === 'ongoing') {
            return true;
        }

        if (trim((string) ($class['teacher_joined_at'] ?? '')) !== '') {
            return true;
        }

        if (trim((string) ($class['recording_acknowledged_at'] ?? '')) !== '') {
            return true;
        }

        if (trim((string) ($class['actual_start_time'] ?? '')) !== '') {
            return true;
        }

        return false;
    }
}

if (!function_exists('studentMeetJoinUrl')) {
    /**
     * @param array<string, mixed> $user
     */
    function studentMeetJoinUrl(string $meetingLink, array $user): string
    {
        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '') {
            return $meetingLink;
        }

        return appendMeetAuthUser($meetingLink, $email);
    }
}

if (!function_exists('appendMeetAuthUser')) {
    function appendMeetAuthUser(string $url, string $email): string
    {
        $url = trim($url);
        $email = trim($email);
        if ($url === '' || $email === '') {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . 'authuser=' . rawurlencode($email);
    }
}

if (!function_exists('normalizeRecordingSyncStatus')) {
    function normalizeRecordingSyncStatus(?string $status): string
    {
        $status = strtolower(trim((string) $status));
        if ($status === 'ready') {
            return 'synced';
        }

        if (in_array($status, ['pending', 'processing', 'synced', 'failed', 'disabled'], true)) {
            return $status;
        }

        return 'pending';
    }
}

if (!function_exists('recordingSyncStatusForRow')) {
    /**
     * @param array<string, mixed>|null $row
     */
    function recordingSyncStatusForRow(?array $row): string
    {
        if ($row === null) {
            return 'pending';
        }

        $hasRecordingUrl = isset($row['recording_url']) && trim((string) $row['recording_url']) !== '';
        if ($hasRecordingUrl) {
            return 'synced';
        }

        if (array_key_exists('recording_enabled', $row) && (int) ($row['recording_enabled'] ?? 0) !== 1) {
            return 'disabled';
        }

        if (
            array_key_exists('teacher_recording_supported', $row)
            && $row['teacher_recording_supported'] !== null
            && (int) $row['teacher_recording_supported'] !== 1
        ) {
            return 'disabled';
        }

        $rawStatus = $row['sync_status']
            ?? $row['class_recording_sync_status']
            ?? $row['recording_sync_status']
            ?? 'pending';

        return normalizeRecordingSyncStatus(is_string($rawStatus) ? $rawStatus : 'pending');
    }
}

if (!function_exists('recordingSyncStatusLabel')) {
    function recordingSyncStatusLabel(string $status): string
    {
        return match (normalizeRecordingSyncStatus($status)) {
            'processing' => 'Processing',
            'synced' => 'Synced',
            'failed' => 'Failed',
            'disabled' => 'Disabled',
            default => 'Pending',
        };
    }
}

if (!function_exists('recordingSyncStatusBadgeClass')) {
    function recordingSyncStatusBadgeClass(string $status): string
    {
        return match (normalizeRecordingSyncStatus($status)) {
            'processing' => 'text-bg-warning',
            'synced' => 'text-bg-success',
            'failed' => 'text-bg-danger',
            'disabled' => 'text-bg-secondary',
            default => 'text-bg-info',
        };
    }
}

if (!function_exists('recordingSyncStatusText')) {
    /**
     * @param array<string, mixed>|null $row
     */
    function recordingSyncStatusText(?array $row): string
    {
        $status = recordingSyncStatusForRow($row);

        if ($status === 'processing') {
            return 'Recording processing in progress';
        }

        if ($status === 'synced') {
            return 'Recording synced';
        }

        if ($status === 'failed') {
            $error = trim((string) ($row['recording_sync_error'] ?? ''));
            return $error !== '' ? $error : 'Recording sync failed.';
        }

        if ($status === 'disabled') {
            if ($row !== null && array_key_exists('recording_enabled', $row) && (int) ($row['recording_enabled'] ?? 0) !== 1) {
                return 'Recording disabled for this class.';
            }
            if (
                $row !== null
                && array_key_exists('teacher_recording_supported', $row)
                && $row['teacher_recording_supported'] !== null
                && (int) $row['teacher_recording_supported'] !== 1
            ) {
                return 'Google Drive recording sync is unavailable for this teacher account.';
            }

            return 'Recording disabled.';
        }

        return 'Recording sync pending.';
    }
}

if (!function_exists('teacherJoinDelayMinutes')) {
    /**
     * @param array<string, mixed>|null $classRow
     */
    function teacherJoinDelayMinutes(?array $classRow): ?int
    {
        if ($classRow === null) {
            return null;
        }
        if (isset($classRow['teacher_join_delay_minutes']) && $classRow['teacher_join_delay_minutes'] !== null && $classRow['teacher_join_delay_minutes'] !== '') {
            return (int) $classRow['teacher_join_delay_minutes'];
        }
        $joinUtc = trim((string) ($classRow['teacher_joined_at'] ?? ''));
        $startUtc = classStartUtcValue($classRow);
        if ($joinUtc === '' || $startUtc === null || $startUtc === '') {
            return null;
        }
        try {
            $start = new DateTimeImmutable($startUtc, new DateTimeZone('UTC'));
            $join = new DateTimeImmutable($joinUtc, new DateTimeZone('UTC'));

            return max(0, (int) round(($join->getTimestamp() - $start->getTimestamp()) / 60));
        } catch (\Throwable) {
            return null;
        }
    }
}

if (!function_exists('isTeacherLateJoin')) {
    /**
     * @param array<string, mixed>|null $classRow
     */
    function isTeacherLateJoin(?array $classRow, int $thresholdMinutes = 1): bool
    {
        $delay = teacherJoinDelayMinutes($classRow);

        return $delay !== null && $delay >= $thresholdMinutes;
    }
}

if (!function_exists('teacherLateJoinNoticeText')) {
    /**
     * @param array<string, mixed>|null $classRow
     */
    function teacherLateJoinNoticeText(?array $classRow): ?string
    {
        if (!isTeacherLateJoin($classRow)) {
            return null;
        }
        $delay = teacherJoinDelayMinutes($classRow);
        if ($delay === null) {
            return 'Teacher joined late';
        }

        return 'Teacher joined ' . (int) $delay . ' minute' . ($delay === 1 ? '' : 's') . ' late';
    }
}

if (!function_exists('teacherLateJoinBadgeHtml')) {
    /**
     * @param array<string, mixed>|null $classRow
     */
    function teacherLateJoinBadgeHtml(?array $classRow): string
    {
        $text = teacherLateJoinNoticeText($classRow);
        if ($text === null) {
            return '';
        }

        return '<span class="badge text-bg-danger ms-1 teacher-late-join-badge" title="' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
            . '</span>';
    }
}

if (!function_exists('teacherLateJoinNoticeHtml')) {
    /**
     * @param array<string, mixed>|null $classRow
     */
    function teacherLateJoinNoticeHtml(?array $classRow, string $extraClass = ''): string
    {
        $text = teacherLateJoinNoticeText($classRow);
        if ($text === null) {
            return '';
        }
        $classAttr = trim('teacher-late-join-notice small d-block mt-1 ' . $extraClass);

        return '<span class="' . htmlspecialchars($classAttr, ENT_QUOTES, 'UTF-8') . '">'
            . '&#128308; ' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
            . '</span>';
    }
}

if (!function_exists('renderPagination')) {
    /**
     * @param array<string, mixed>|null $pagination
     * @param array<string, scalar|null> $queryParams
     */
    function renderPagination(?array $pagination, array $queryParams = []): void
    {
        if ($pagination === null || (int) ($pagination['total'] ?? 0) <= 0) {
            return;
        }

        require dirname(__DIR__) . '/views/partials/pagination.php';
    }
}

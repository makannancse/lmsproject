<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/models/SystemConfig.php';

class SettingsController
{
    private static function configValue(string $envKey, string $systemKey, string $default = ''): string
    {
        $envValue = trim((string) env($envKey, ''));
        if ($envValue !== '') {
            return $envValue;
        }

        return (string) SystemConfig::get($systemKey, $default);
    }

    public static function edit(): void
    {
        Auth::requireRole(['admin']);

        $rate = SystemConfig::get('payout_rate_per_hour', '20');
        $rateFt = SystemConfig::get('payout_rate_full_time', '30');
        $ratePt = SystemConfig::get('payout_rate_part_time', '20');

        // Google Meet / Calendar config (primary)
        $googleClientId = self::configValue('GOOGLE_CLIENT_ID', 'google_client_id', '');
        $googleClientSecret = self::configValue('GOOGLE_CLIENT_SECRET', 'google_client_secret', '');
        $googleCalendarId = self::configValue('GOOGLE_CALENDAR_ID', 'google_calendar_id', 'primary');
        $googleWorkspaceDomain = self::configValue('GOOGLE_WORKSPACE_DOMAIN', 'google_workspace_domain', '');
        $staticMeetingLink = self::configValue('STATIC_MEETING_LINK', 'static_meeting_link', '');

        $smtpHost = SystemConfig::get('smtp_host', '');
        $smtpPort = SystemConfig::get('smtp_port', '587');
        $smtpUsername = SystemConfig::get('smtp_username', '');
        $smtpEncryption = SystemConfig::get('smtp_encryption', 'tls');
        $mailFrom = SystemConfig::get('mail_from', '');
        $mailFromName = SystemConfig::get('mail_from_name', APP_NAME);

        View::render('admin/settings', [
            'pageTitle' => 'System Settings',
            'payoutRatePerHour' => $rate,
            'payoutRateFullTime' => $rateFt,
            'payoutRatePartTime' => $ratePt,
            'googleClientId' => $googleClientId,
            'googleClientSecretMasked' => $googleClientSecret !== '' ? substr($googleClientSecret, 0, 4) . '****' : '',
            'googleCalendarId' => $googleCalendarId,
            'googleWorkspaceDomain' => $googleWorkspaceDomain,
            'staticMeetingLink' => $staticMeetingLink,
            'smtpHost' => $smtpHost,
            'smtpPort' => $smtpPort,
            'smtpUsername' => $smtpUsername,
            'smtpEncryption' => $smtpEncryption,
            'mailFrom' => $mailFrom,
            'mailFromName' => $mailFromName,
        ]);
    }

    public static function update(): void
    {
        Auth::requireRole(['admin']);

        $rate = trim($_POST['payout_rate_per_hour'] ?? '');
        if ($rate !== '') {
            SystemConfig::set('payout_rate_per_hour', $rate);
        }

        $rateFt = trim($_POST['payout_rate_full_time'] ?? '');
        if ($rateFt !== '') {
            SystemConfig::set('payout_rate_full_time', $rateFt);
        }

        $ratePt = trim($_POST['payout_rate_part_time'] ?? '');
        if ($ratePt !== '') {
            SystemConfig::set('payout_rate_part_time', $ratePt);
        }

        $googleClientId = trim($_POST['google_client_id'] ?? '');
        if ($googleClientId !== '') {
            SystemConfig::set('google_client_id', $googleClientId);
        }

        $googleClientSecret = trim($_POST['google_client_secret'] ?? '');
        if ($googleClientSecret !== '') {
            SystemConfig::set('google_client_secret', $googleClientSecret);
        }

        $googleCalendarId = trim($_POST['google_calendar_id'] ?? '');
        if ($googleCalendarId !== '') {
            SystemConfig::set('google_calendar_id', $googleCalendarId);
        }

        $googleWorkspaceDomain = trim($_POST['google_workspace_domain'] ?? '');
        if ($googleWorkspaceDomain !== '') {
            SystemConfig::set('google_workspace_domain', strtolower($googleWorkspaceDomain));
        }

        $staticMeetingLink = trim($_POST['static_meeting_link'] ?? '');
        if ($staticMeetingLink !== '') {
            SystemConfig::set('static_meeting_link', $staticMeetingLink);
        }


        $smtpHost = trim($_POST['smtp_host'] ?? '');
        if ($smtpHost !== '') {
            SystemConfig::set('smtp_host', $smtpHost);
        }

        $smtpPort = trim($_POST['smtp_port'] ?? '');
        if ($smtpPort !== '') {
            SystemConfig::set('smtp_port', $smtpPort);
        }

        $smtpUsername = trim($_POST['smtp_username'] ?? '');
        if ($smtpUsername !== '') {
            SystemConfig::set('smtp_username', $smtpUsername);
        }

        $smtpPassword = trim($_POST['smtp_password'] ?? '');
        if ($smtpPassword !== '') {
            SystemConfig::set('smtp_password', $smtpPassword);
        }

        $smtpEncryption = trim($_POST['smtp_encryption'] ?? '');
        if ($smtpEncryption !== '') {
            SystemConfig::set('smtp_encryption', $smtpEncryption);
        }

        $mailFrom = trim($_POST['mail_from'] ?? '');
        if ($mailFrom !== '') {
            SystemConfig::set('mail_from', $mailFrom);
        }

        $mailFromName = trim($_POST['mail_from_name'] ?? '');
        if ($mailFromName !== '') {
            SystemConfig::set('mail_from_name', $mailFromName);
        }

        $base = defined('BASE_PATH') ? BASE_PATH : '';
        header('Location: ' . $base . '/settings');
    }
}

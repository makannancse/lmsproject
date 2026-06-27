<?php

declare(strict_types=1);

/**
 * Branded LearnWise HTML email layout for all transactional emails.
 */
class EmailTemplate
{
    public static function brandName(): string
    {
        return (string) (defined('APP_NAME') && APP_NAME !== 'LMS' ? APP_NAME : 'LearnWise');
    }

    public static function logoUrl(): string
    {
        return function_exists('url') ? url('assets/images/logo.png') : (defined('LOGO_PATH') ? LOGO_PATH : '');
    }

    public static function supportEmail(): string
    {
        if (function_exists('env')) {
            $configured = trim((string) env('SUPPORT_EMAIL', ''));
            if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
                return $configured;
            }
        }

        return 'support@edulearnwise.com';
    }

    public static function supportPhone(): string
    {
        return function_exists('env') ? trim((string) env('SUPPORT_PHONE', '+91 98765 43210')) : '+91 98765 43210';
    }

    public static function websiteUrl(): string
    {
        return defined('APP_URL') ? APP_URL : 'https://www.edulearnwise.com';
    }

    /**
     * @param array<string, string> $rows label => value (value may contain safe HTML)
     */
    public static function wrap(
        string $subjectLine,
        string $introHtml,
        array $rows = [],
        ?string $ctaLabel = null,
        ?string $ctaUrl = null,
        bool $includeThankYou = true
    ): string {
        $brand = htmlspecialchars(self::brandName(), ENT_QUOTES, 'UTF-8');
        $logo = htmlspecialchars(self::logoUrl(), ENT_QUOTES, 'UTF-8');
        $supportEmail = htmlspecialchars(self::supportEmail(), ENT_QUOTES, 'UTF-8');
        $supportPhone = htmlspecialchars(self::supportPhone(), ENT_QUOTES, 'UTF-8');
        $website = htmlspecialchars(self::websiteUrl(), ENT_QUOTES, 'UTF-8');
        $year = date('Y');

        $rowsHtml = '';
        foreach ($rows as $label => $value) {
            if ($value === '') {
                continue;
            }
            $rowsHtml .= '<tr><td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;color:#6b7280;width:38%;vertical-align:top;">'
                . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8')
                . '</td><td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;color:#111827;">'
                . $value
                . '</td></tr>';
        }

        $ctaHtml = '';
        if ($ctaLabel !== null && $ctaUrl !== null && $ctaUrl !== '') {
            $ctaHtml = '<p style="text-align:center;margin:28px 0 8px;">'
                . '<a href="' . htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') . '" '
                . 'style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;'
                . 'padding:14px 28px;border-radius:8px;font-weight:600;font-size:16px;">'
                . htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8')
                . '</a></p>';
        }

        $thankYou = '';
        if ($includeThankYou) {
            $thankYou = '
                <div style="margin-top:28px;padding:20px;background:#f0f9ff;border-radius:8px;text-align:center;">
                    <p style="margin:0 0 8px;font-size:18px;font-weight:700;color:#1e40af;">Thank You for Choosing ' . $brand . '</p>
                    <p style="margin:0;color:#475569;font-size:14px;line-height:1.6;">We are committed to delivering high-quality online education and helping students achieve academic success.</p>
                </div>';
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:24px 12px;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.07);">'
            . '<tr><td style="background:linear-gradient(135deg,#1e40af 0%,#2563eb 100%);padding:24px;text-align:center;">'
            . ($logo !== '' ? '<img src="' . $logo . '" alt="' . $brand . '" style="max-height:56px;margin-bottom:8px;">' : '')
            . '<p style="margin:0;color:#ffffff;font-size:22px;font-weight:700;">' . $brand . '</p>'
            . '<p style="margin:6px 0 0;color:#bfdbfe;font-size:14px;">' . htmlspecialchars($subjectLine, ENT_QUOTES, 'UTF-8') . '</p>'
            . '</td></tr>'
            . '<tr><td style="padding:28px 24px;color:#374151;font-size:15px;line-height:1.65;">'
            . $introHtml
            . ($rowsHtml !== '' ? '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:20px 0 0;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">' . $rowsHtml . '</table>' : '')
            . $ctaHtml
            . $thankYou
            . '</td></tr>'
            . '<tr><td style="background:#1f2937;padding:20px 24px;text-align:center;">'
            . ($logo !== '' ? '<img src="' . $logo . '" alt="' . $brand . '" style="max-height:36px;margin-bottom:10px;opacity:0.9;">' : '')
            . '<p style="margin:0 0 6px;color:#9ca3af;font-size:13px;">'
            . '<a href="mailto:' . $supportEmail . '" style="color:#93c5fd;text-decoration:none;">' . $supportEmail . '</a>'
            . ' &nbsp;|&nbsp; ' . $supportPhone
            . '</p>'
            . '<p style="margin:0;color:#6b7280;font-size:12px;">'
            . '<a href="' . $website . '" style="color:#93c5fd;text-decoration:none;">' . $website . '</a>'
            . ' &nbsp;&bull;&nbsp; &copy; ' . $year . ' ' . $brand . '. All rights reserved.'
            . '</p></td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    public static function subject(string $type, string $detail = ''): string
    {
        $brand = self::brandName();
        return match ($type) {
            'class_scheduled' => $brand . ' | Class Scheduled Successfully',
            'recurring_scheduled' => $brand . ' | Recurring Classes Scheduled Successfully',
            'class_rescheduled' => $brand . ' | Class Rescheduled',
            'welcome' => 'Welcome to ' . $brand,
            'password_reset' => $brand . ' | Password Reset',
            default => $detail !== '' ? ($brand . ' | ' . $detail) : $brand,
        };
    }

    public static function logCredential(string $recipient, bool $success, ?string $smtpResponse = null, ?string $error = null): void
    {
        if (!function_exists('writeStructuredLog')) {
            return;
        }
        writeStructuredLog('email_credentials.log', [
            'recipient' => $recipient,
            'success' => $success,
            'smtp_response' => $smtpResponse,
            'error' => $error,
        ]);
    }
}

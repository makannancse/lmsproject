<?php

declare(strict_types=1);

/**
 * Branded LearnWise HTML email layout for all transactional emails.
 *
 * IMPORTANT — localhost URLs in email bodies:
 * Any link pointing to http://localhost/... inside an email body is a hard spam
 * signal for Gmail's content filter. This class provides sanitizeUrlForEmail()
 * which MUST be called on every URL before embedding it in an outgoing email.
 * It replaces the local APP_URL origin with the PUBLIC_URL (real domain).
 */
class EmailTemplate
{
    public static function brandName(): string
    {
        $name = defined('APP_NAME') ? trim((string) APP_NAME) : '';
        return ($name !== '' && $name !== 'LMS') ? $name : 'LearnWise';
    }

    /**
     * Returns the publicly accessible base URL (used for email links and assets).
     * Uses PUBLIC_URL from .env when set; falls back to APP_URL only if it is
     * NOT a localhost address, otherwise falls back to a hardcoded domain.
     */
    public static function publicBaseUrl(): string
    {
        // 1. Explicit PUBLIC_URL in .env — always preferred.
        if (function_exists('env')) {
            $pub = trim((string) env('PUBLIC_URL', ''));
            if ($pub !== '' && !self::isLocalhost($pub)) {
                return rtrim($pub, '/');
            }
        }
        if (!empty($_ENV['PUBLIC_URL']) && !self::isLocalhost((string) $_ENV['PUBLIC_URL'])) {
            return rtrim((string) $_ENV['PUBLIC_URL'], '/');
        }

        // 2. APP_URL if it is a real (non-localhost) domain.
        if (defined('APP_URL') && APP_URL !== '' && !self::isLocalhost((string) APP_URL)) {
            return rtrim((string) APP_URL, '/');
        }

        // 3. Hardcoded fallback — update if the domain changes.
        return 'https://www.edulearnwise.com';
    }

    /**
     * Replaces the local development origin in any URL with the real public domain.
     * This MUST be called on every URL embedded in outgoing emails.
     *
     * Why: Gmail's spam filter flags emails that contain http://localhost/... links
     * because no real email client can access localhost — it is a textbook phishing signal.
     */
    public static function sanitizeUrlForEmail(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $publicBase = self::publicBaseUrl();

        // Replace APP_URL origin if it is a localhost address.
        if (defined('APP_URL') && APP_URL !== '' && self::isLocalhost((string) APP_URL)) {
            $localBase = rtrim((string) APP_URL, '/');
            if (str_starts_with($url, $localBase)) {
                return $publicBase . substr($url, strlen($localBase));
            }
        }

        // Replace any remaining localhost origin directly.
        $url = preg_replace(
            '#^https?://(localhost|127\.0\.0\.1)(:\d+)?#i',
            $publicBase,
            $url
        ) ?? $url;

        return $url;
    }

    /**
     * Returns the embedded CID image reference for use inside email <img> tags.
     * Embedded CID images are physically attached inside the email MIME body,
     * guaranteeing they display in Gmail, Outlook, Yahoo, and mobile clients
     * without relying on external web hosting or being blocked by image proxies.
     */
    public static function logoUrl(): string
    {
        return 'cid:learnwise_logo_cid';
    }

    public static function supportEmail(): string
    {
        if (function_exists('env')) {
            $configured = trim((string) env('SUPPORT_EMAIL', ''));
            if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
                return $configured;
            }
        }

        return 'admin@edulearnwise.com';
    }

    public static function supportPhone(): string
    {
        return function_exists('env') ? trim((string) env('SUPPORT_PHONE', '+91 98765 43210')) : '+91 98765 43210';
    }

    public static function websiteUrl(): string
    {
        return self::publicBaseUrl();
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
        $brand        = htmlspecialchars(self::brandName(), ENT_QUOTES, 'UTF-8');
        $logo         = htmlspecialchars(self::logoUrl(), ENT_QUOTES, 'UTF-8');
        $supportEmail = htmlspecialchars(self::supportEmail(), ENT_QUOTES, 'UTF-8');
        $supportPhone = htmlspecialchars(self::supportPhone(), ENT_QUOTES, 'UTF-8');
        $website      = htmlspecialchars(self::websiteUrl(), ENT_QUOTES, 'UTF-8');
        $year         = date('Y');

        // Sanitize CTA URL — never allow localhost in outgoing email links.
        $safeCta = ($ctaUrl !== null && $ctaUrl !== '') ? self::sanitizeUrlForEmail($ctaUrl) : '';

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
        if ($ctaLabel !== null && $safeCta !== '') {
            $ctaHtml = '<p style="text-align:center;margin:28px 0 8px;">'
                . '<a href="' . htmlspecialchars($safeCta, ENT_QUOTES, 'UTF-8') . '" '
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
            . '<tr><td style="background:linear-gradient(135deg,#111827 0%,#1e40af 100%);padding:24px;text-align:center;">'
            . '<img src="' . $logo . '" alt="' . $brand . '" width="160" height="54" style="display:block;margin:0 auto 8px;max-width:100%;height:auto;border:0;">'
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
            'class_scheduled'     => $brand . ' | Class Scheduled Successfully',
            'recurring_scheduled' => $brand . ' | Recurring Classes Scheduled Successfully',
            'class_rescheduled'   => $brand . ' | Class Rescheduled',
            'welcome'             => $brand . ' | Welcome',
            'password_reset'      => $brand . ' | Password Reset Request',
            default               => $detail !== '' ? ($brand . ' | ' . $detail) : $brand,
        };
    }

    public static function logCredential(string $recipient, bool $success, ?string $smtpResponse = null, ?string $error = null): void
    {
        if (!function_exists('writeStructuredLog')) {
            return;
        }
        writeStructuredLog('email_credentials.log', [
            'recipient'     => $recipient,
            'success'       => $success,
            'smtp_response' => $smtpResponse,
            'error'         => $error,
        ]);
    }

    // ---------------------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------------------

    private static function isLocalhost(string $url): bool
    {
        return (bool) preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?(/|$)#i', $url);
    }
}

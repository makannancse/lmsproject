<?php

declare(strict_types=1);

/**
 * SMTP mailer using PHPMailer.
 *
 * Root cause of prior failures: Core PHP does not load `.env` files. Variables must be loaded
 * explicitly (see `app/config/config.php` using vlucas/phpdotenv). If dotenv never runs or
 * `.env` is missing, `$_ENV['SMTP_*']` is empty and authentication fails or is skipped.
 *
 * Gmail: use an App Password (Google Account → Security → 2-Step Verification → App passwords),
 * NOT your normal login password. Typical values:
 *   SMTP_HOST=smtp.gmail.com
 *   SMTP_PORT=587
 *   SMTP_ENCRYPTION=tls
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/lib/EmailTemplate.php';

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
class Mailer
{
    /**
     * @return string|null null if SMTP_HOST, SMTP_USERNAME, and SMTP_PASSWORD are all non-empty in $_ENV
     */
    public static function getSmtpEnvError(): ?string
    {
        $host = trim((string) ($_ENV['SMTP_HOST'] ?? ''));
        $user = trim((string) ($_ENV['SMTP_USERNAME'] ?? ''));
        $pass = trim((string) ($_ENV['SMTP_PASSWORD'] ?? ''));
        if ($host === '' || $user === '' || $pass === '') {
            return 'SMTP config missing or not loaded';
        }

        return null;
    }

    /**
     * @throws \RuntimeException when required SMTP keys are missing from $_ENV
     */
    public static function assertSmtpEnvConfigured(): void
    {
        $err = self::getSmtpEnvError();
        if ($err !== null) {
            throw new \RuntimeException($err);
        }
    }

    public static function send(string $to, string $subject, string $body, bool $isHtml = false, array $attachments = []): array
    {
        if ($to === '') {
            return ['success' => false, 'error' => 'Recipient email is empty.'];
        }

        try {
            self::assertSmtpEnvConfigured();
        } catch (\RuntimeException $e) {
            self::logMailError($to, $subject, $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = (string) $_ENV['SMTP_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = (string) $_ENV['SMTP_USERNAME'];
            $mail->Password = (string) $_ENV['SMTP_PASSWORD'];

            $encryption = strtolower(trim((string) ($_ENV['SMTP_ENCRYPTION'] ?? 'tls')));
            $mail->SMTPSecure = $encryption === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;

            $mail->Port = (int) ($_ENV['SMTP_PORT'] ?? 587);

            $smtpOptions = self::smtpSslOptions();
            if ($smtpOptions !== []) {
                $mail->SMTPOptions = $smtpOptions;
            }

            // SMTP debug transcript is disabled in production to avoid header pollution.
            // Set SMTP_DEBUG=1 in .env to enable verbose logs temporarily.
            $debugLevel = (int) ($_ENV['SMTP_DEBUG'] ?? 0);
            $mail->SMTPDebug = $debugLevel;
            if ($debugLevel > 0) {
                $mail->Debugoutput = static function (string $str, int $level): void {
                    self::logMailDebug('[SMTP-' . $level . '] ' . $str);
                };
            }

            // From address must match the authenticated mailbox (or a configured alias) for Gmail.
            $fromEmail = trim((string) ($_ENV['SMTP_FROM'] ?? $_ENV['SMTP_USERNAME'] ?? ''));
            $fromName = trim((string) ($_ENV['SMTP_FROM_NAME'] ?? ''));
            $resolvedFromName = $fromName !== '' ? $fromName : EmailTemplate::brandName();
            $mail->setFrom($fromEmail, $resolvedFromName);

            // Reply-To: ensure replies go to the correct address (prevents spam scoring).
            $mail->addReplyTo($fromEmail, $resolvedFromName);

            // Suppress default "PHPMailer" X-Mailer header that spam filters flag.
            $mail->XMailer = ' ';

            // Anchor Message-ID to our own domain to avoid being flagged as a forged header.
            $domain = substr($fromEmail, (int) strpos($fromEmail, '@') + 1);
            $mail->MessageID = '<' . bin2hex(random_bytes(12)) . '.' . time() . '@' . $domain . '>';

            // Signal normal (non-bulk) priority so inbox filters treat it as transactional.
            $mail->addCustomHeader('X-Priority', '3');
            $mail->addCustomHeader('X-Mailer-Version', EmailTemplate::brandName() . '-Mailer-1.0');

            $mail->addAddress($to);
            $mail->CharSet = 'UTF-8';
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body = $body;
            if ($isHtml) {
                // Build a human-readable plain-text fallback instead of raw stripped HTML.
                $plainText = $body;
                $plainText = preg_replace('/<br\s*\/?>/i', "\n", $plainText) ?? $plainText;
                $plainText = preg_replace('/<\/p>/i', "\n\n", $plainText) ?? $plainText;
                $plainText = preg_replace('/<\/tr>/i', "\n", $plainText) ?? $plainText;
                $plainText = preg_replace('/<\/td>/i', " | ", $plainText) ?? $plainText;
                $plainText = preg_replace('/<\/th>/i', " | ", $plainText) ?? $plainText;
                $plainText = preg_replace('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', '$2 ($1)', $plainText) ?? $plainText;
                $plainText = strip_tags($plainText);
                $plainText = html_entity_decode($plainText, ENT_QUOTES, 'UTF-8');
                $plainText = preg_replace('/[ \t]+/', ' ', $plainText) ?? $plainText;
                $plainText = preg_replace('/\n{3,}/', "\n\n", $plainText) ?? $plainText;
                $mail->AltBody = trim($plainText);
            }
            foreach ($attachments as $attachment) {
                if (!is_array($attachment)) {
                    continue;
                }
                $path = (string) ($attachment['path'] ?? '');
                if ($path === '' || !is_file($path)) {
                    continue;
                }
                $name = (string) ($attachment['name'] ?? basename($path));
                $mail->addAttachment($path, $name);
            }

            $mail->send();
            self::logMailSuccess($to, $subject);
            return ['success' => true, 'error' => null];
        } catch (PHPMailerException $e) {
            $errorMessage = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
            self::logMailError($to, $subject, $errorMessage);
            return ['success' => false, 'error' => $errorMessage];
        } catch (\Throwable $e) {
            $errorMessage = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
            self::logMailError($to, $subject, $errorMessage);
            return ['success' => false, 'error' => $errorMessage];
        }
    }

    /** Log configuration or operational issues (also see logMailError for per-recipient errors). */
    public static function logSmtpIssue(string $message): void
    {
        self::ensureLogsDirectory();
        $timestamp = date('Y-m-d H:i:s');
        $line = sprintf("[%s] %s%s", $timestamp, $message, PHP_EOL);
        @file_put_contents(self::mailErrorLogPath(), $line, FILE_APPEND);
        error_log('Mailer: ' . $message);
    }

    private static function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function logsDirectory(): string
    {
        return self::projectRoot() . DIRECTORY_SEPARATOR . 'logs';
    }

    private static function ensureLogsDirectory(): void
    {
        $dir = self::logsDirectory();
        if (is_dir($dir)) {
            return;
        }
        @mkdir($dir, 0755, true);
    }

    private static function mailErrorLogPath(): string
    {
        return self::logsDirectory() . DIRECTORY_SEPARATOR . 'mail_error.log';
    }

    private static function mailDebugLogPath(): string
    {
        return self::logsDirectory() . DIRECTORY_SEPARATOR . 'mail_debug.log';
    }

    private static function mailSentLogPath(): string
    {
        return self::logsDirectory() . DIRECTORY_SEPARATOR . 'mail.log';
    }

    /**
     * @return array<string, mixed>
     */
    private static function smtpSslOptions(): array
    {
        $cafile = self::resolveCaBundlePath();
        if ($cafile === null) {
            return [];
        }

        return [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'cafile' => $cafile,
            ],
        ];
    }

    private static function resolveCaBundlePath(): ?string
    {
        $configured = trim((string) ($_ENV['SMTP_CAINFO'] ?? $_ENV['SSL_CAINFO'] ?? $_ENV['GOOGLE_CAINFO'] ?? ''));
        $candidates = [];
        if ($configured !== '') {
            $candidates[] = $configured;
        }

        $root = self::projectRoot();
        $candidates[] = $root . DIRECTORY_SEPARATOR . 'certs' . DIRECTORY_SEPARATOR . 'cacert.pem';

        foreach ($candidates as $candidate) {
            $path = $candidate;
            if (!preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) && !str_starts_with($path, DIRECTORY_SEPARATOR)) {
                $path = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            }
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /** Successful SMTP send (logs/mail.log). */
    private static function logMailSuccess(string $to, string $subject): void
    {
        self::ensureLogsDirectory();
        $timestamp = date('Y-m-d H:i:s');
        $line = sprintf("[%s] SENT OK | To: %s | Subject: %s%s", $timestamp, $to, $subject, PHP_EOL);
        @file_put_contents(self::mailSentLogPath(), $line, FILE_APPEND);
    }

    private static function logMailError(string $to, string $subject, string $error): void
    {
        self::ensureLogsDirectory();
        $logFile = self::mailErrorLogPath();
        $timestamp = date('Y-m-d H:i:s');
        $line = sprintf(
            "[%s] To: %s | Subject: %s | Error: %s%s",
            $timestamp,
            $to !== '' ? $to : '-',
            $subject !== '' ? $subject : '-',
            $error,
            PHP_EOL
        );
        @file_put_contents($logFile, $line, FILE_APPEND);
        error_log('Mailer send failed for ' . ($to !== '' ? $to : '(no recipient)') . ': ' . $error);
    }

    private static function logMailDebug(string $message): void
    {
        self::ensureLogsDirectory();
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents(
            self::mailDebugLogPath(),
            '[' . $timestamp . '] ' . $message . PHP_EOL,
            FILE_APPEND
        );
    }
}

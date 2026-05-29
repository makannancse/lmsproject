<?php

declare(strict_types=1);

/**
 * Isolated SMTP test — bypasses LMS controllers. Uses the same `.env` + Mailer as the app.
 *
 * Browser:  /test_mail.php?to=you@example.com
 * CLI:      php test_mail.php you@example.com
 *
 * Logs:     logs/mail_debug.log (SMTP transcript), logs/mail_error.log (failures + ErrorInfo)
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/lib/Mailer.php';

$to = '';
if (PHP_SAPI === 'cli') {
    global $argv;
    $to = isset($argv[1]) ? trim((string) $argv[1]) : '';
} else {
    header('Content-Type: text/plain; charset=UTF-8');
    $to = isset($_GET['to']) ? trim((string) $_GET['to']) : '';
}

if ($to === '') {
    echo "Usage:\n";
    echo "  Web:  test_mail.php?to=student@example.com\n";
    echo "  CLI:  php test_mail.php student@example.com\n";
    exit(1);
}

if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo "FAILED: Invalid email address.\n";
    exit(1);
}

try {
    Mailer::assertSmtpEnvConfigured();
} catch (RuntimeException $e) {
    echo "FAILED (configuration): " . $e->getMessage() . "\n";
    echo "Fix: ensure project root `.env` exists and sets SMTP_HOST, SMTP_USERNAME, SMTP_PASSWORD.\n";
    echo "See `.env.example`. Logs: logs/mail_error.log\n";
    exit(1);
}

$subject = 'SMTP Test Mail - ' . APP_NAME;
$body = '
    <div style="font-family: Arial, sans-serif;">
        <h2>SMTP Test Successful</h2>
        <p>This test email confirms your PHPMailer SMTP setup is working.</p>
        <p><strong>App:</strong> ' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . '</p>
        <p><strong>Time:</strong> ' . date('Y-m-d H:i:s') . '</p>
    </div>
';

$result = Mailer::send($to, $subject, $body, true);
if (!empty($result['success'])) {
    echo "SUCCESS: Test email sent to {$to}\n";
    echo "If it does not arrive, check spam and Gmail \"Sent\" (smtp.gmail.com).\n";
    exit(0);
}

echo "FAILED (SMTP / send): " . ($result['error'] ?? 'Unknown error') . "\n";
echo "Full detail is in logs/mail_error.log; SMTP conversation in logs/mail_debug.log.\n";
echo "Gmail: use an App Password, not your normal password (2-Step Verification required).\n";
exit(1);

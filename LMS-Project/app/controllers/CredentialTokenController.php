<?php

declare(strict_types=1);

/**
 * Handles the secure one-time credential view page.
 *
 * When a new user account is created, instead of putting the password
 * directly in the welcome email (which Gmail flags as phishing), we:
 *   1. Store a short-lived token + credentials in credential_tokens table.
 *   2. Email the user a clean link: /welcome/{token}
 *   3. This controller serves the branded credential page when the link is clicked.
 *
 * The token is valid for 48 hours and can be viewed multiple times within
 * that window (so the user can revisit if they forgot). After expiry the
 * page shows a friendly "link expired" message.
 */
class CredentialTokenController
{
    /**
     * Generate a secure token, store credentials, return the raw token string.
     */
    public static function create(string $email, string $name, string $plainPassword): string
    {
        $rawToken  = bin2hex(random_bytes(32));          // 64-char hex token
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+48 hours'));

        $pdo = db();
        $stmt = $pdo->prepare(
            'INSERT INTO credential_tokens
             (token_hash, user_email, user_name, temp_pass, expires_at)
             VALUES (:hash, :email, :name, :pass, :exp)'
        );
        $stmt->execute([
            ':hash'  => $tokenHash,
            ':email' => $email,
            ':name'  => $name,
            ':pass'  => $plainPassword,
            ':exp'   => $expiresAt,
        ]);

        return $rawToken;
    }

    /**
     * GET /welcome/{token}
     * Shows the branded credentials page or an expired/invalid message.
     */
    public static function show(): void
    {
        $rawToken = trim((string)($_GET['token'] ?? ''));

        if (empty($rawToken) || strlen($rawToken) !== 64) {
            self::renderError('Invalid or missing link token.');
            return;
        }

        $tokenHash = hash('sha256', $rawToken);
        $pdo       = db();

        $stmt = $pdo->prepare(
            'SELECT * FROM credential_tokens WHERE token_hash = :hash LIMIT 1'
        );
        $stmt->execute([':hash' => $tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            self::renderError('This link is invalid or does not exist.');
            return;
        }

        if (strtotime($row['expires_at']) < time()) {
            self::renderExpired();
            return;
        }

        // Mark as viewed the first time
        if ($row['viewed_at'] === null) {
            $pdo->prepare('UPDATE credential_tokens SET viewed_at = NOW() WHERE token_hash = :hash')
                ->execute([':hash' => $tokenHash]);
        }

        self::renderCredentials(
            (string) $row['user_name'],
            (string) $row['user_email'],
            (string) $row['temp_pass'],
            (string) $row['expires_at']
        );
    }

    // -----------------------------------------------------------------------

    private static function renderCredentials(string $name, string $email, string $password, string $expiresAt): void
    {
        $brand       = defined('APP_NAME') && APP_NAME !== 'LMS' ? APP_NAME : 'LearnWise';
        $loginUrl    = url('login');
        $logoUrl     = rtrim((string)(defined('APP_URL') ? APP_URL : ''), '/') . '/assets/images/logo.png';
        $firstName   = htmlspecialchars(explode(' ', trim($name))[0], ENT_QUOTES, 'UTF-8');
        $safeEmail   = htmlspecialchars($email,    ENT_QUOTES, 'UTF-8');
        $safePass    = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');
        $safeExpires = htmlspecialchars(date('d M Y, g:i A', strtotime($expiresAt)), ENT_QUOTES, 'UTF-8');
        $safeBrand   = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');
        $safeLogin   = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');

        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');
        // Prevent indexing / caching of credential pages
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Robots-Tag: noindex, nofollow');

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Your {$safeBrand} Account Details</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', Arial, sans-serif;
      background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
    }
    .card {
      background: #ffffff;
      border-radius: 16px;
      box-shadow: 0 25px 60px rgba(0,0,0,0.3);
      max-width: 480px;
      width: 100%;
      overflow: hidden;
    }
    .card-header {
      background: linear-gradient(135deg, #111827 0%, #1e40af 100%);
      padding: 32px 24px;
      text-align: center;
    }
    .card-header img { max-height: 60px; width: auto; margin-bottom: 12px; display: block; margin-left: auto; margin-right: auto; }
    .card-header h1 { color: #fff; font-size: 22px; font-weight: 700; margin-bottom: 4px; }
    .card-header p  { color: #bfdbfe; font-size: 14px; }
    .card-body { padding: 32px 28px; }
    .greeting { font-size: 16px; color: #374151; margin-bottom: 20px; line-height: 1.6; }
    .credential-box {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 20px 24px;
      margin-bottom: 20px;
    }
    .credential-box h2 { font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 16px; }
    .cred-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e2e8f0; gap: 12px; }
    .cred-row:last-child { border-bottom: none; }
    .cred-label { font-size: 13px; color: #64748b; font-weight: 500; flex-shrink: 0; }
    .cred-value { font-size: 14px; color: #111827; font-weight: 600; font-family: 'Courier New', monospace; word-break: break-all; text-align: right; }
    .copy-btn {
      background: none; border: 1px solid #d1d5db; border-radius: 6px;
      padding: 4px 10px; font-size: 12px; color: #374151; cursor: pointer;
      flex-shrink: 0; transition: all 0.2s;
    }
    .copy-btn:hover { background: #f3f4f6; border-color: #9ca3af; }
    .copy-btn.copied { background: #d1fae5; border-color: #34d399; color: #065f46; }
    .notice {
      background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px;
      padding: 12px 16px; font-size: 13px; color: #92400e; margin-bottom: 20px; line-height: 1.5;
    }
    .notice strong { color: #78350f; }
    .expires { font-size: 12px; color: #9ca3af; text-align: center; margin-bottom: 20px; }
    .btn-signin {
      display: block; width: 100%; padding: 14px;
      background: linear-gradient(135deg, #1e40af, #3b82f6);
      color: #fff; text-align: center; border-radius: 10px;
      font-size: 16px; font-weight: 600; text-decoration: none;
      transition: opacity 0.2s;
    }
    .btn-signin:hover { opacity: 0.9; }
    .card-footer { background: #f8fafc; padding: 16px 28px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #e2e8f0; }
  </style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <img src="{$logoUrl}" alt="{$safeBrand}">
    <h1>{$safeBrand}</h1>
    <p>Your Account Details</p>
  </div>
  <div class="card-body">
    <p class="greeting">Hi <strong>{$firstName}</strong>, your account is ready. Here are your sign-in details:</p>

    <div class="credential-box">
      <h2>Sign-In Credentials</h2>
      <div class="cred-row">
        <span class="cred-label">Email</span>
        <span class="cred-value" id="val-email">{$safeEmail}</span>
        <button class="copy-btn" onclick="copyVal('val-email', this)">Copy</button>
      </div>
      <div class="cred-row">
        <span class="cred-label">Password</span>
        <span class="cred-value" id="val-pass">{$safePass}</span>
        <button class="copy-btn" onclick="copyVal('val-pass', this)">Copy</button>
      </div>
    </div>

    <div class="notice">
      <strong>⚠ Please change your password</strong> after signing in for the first time.
    </div>

    <p class="expires">🔒 This page is valid until {$safeExpires}</p>

    <a href="{$safeLogin}" class="btn-signin">Sign In to {$safeBrand} →</a>
  </div>
  <div class="card-footer">
    &copy; {$safeBrand} &bull; This page is private — do not share this link.
  </div>
</div>
<script>
function copyVal(id, btn) {
  var text = document.getElementById(id).textContent.trim();
  navigator.clipboard.writeText(text).then(function() {
    btn.textContent = 'Copied!';
    btn.classList.add('copied');
    setTimeout(function() { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2000);
  });
}
</script>
</body>
</html>
HTML;
    }

    private static function renderExpired(): void
    {
        $brand = defined('APP_NAME') && APP_NAME !== 'LMS' ? APP_NAME : 'LearnWise';
        http_response_code(410);
        header('Content-Type: text/html; charset=UTF-8');
        echo <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Link Expired</title>
<style>body{font-family:Arial,sans-serif;background:#f3f4f6;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px}
.box{background:#fff;border-radius:12px;padding:40px;max-width:400px;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.1)}
h1{font-size:20px;color:#111827;margin-bottom:12px}p{color:#6b7280;font-size:14px;line-height:1.6}
</style></head><body>
<div class="box">
  <div style="font-size:48px;margin-bottom:16px">⏰</div>
  <h1>This link has expired</h1>
  <p>Your account details link is only valid for 48 hours. Please contact your {$brand} administrator to resend your credentials.</p>
</div></body></html>
HTML;
    }

    private static function renderError(string $msg): void
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        $safe = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
        echo <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Not Found</title>
<style>body{font-family:Arial,sans-serif;background:#f3f4f6;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px}
.box{background:#fff;border-radius:12px;padding:40px;max-width:400px;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.1)}
h1{font-size:20px;color:#111827;margin-bottom:12px}p{color:#6b7280;font-size:14px}</style></head><body>
<div class="box">
  <div style="font-size:48px;margin-bottom:16px">🔗</div>
  <h1>Invalid Link</h1>
  <p>{$safe}</p>
</div></body></html>
HTML;
    }
}

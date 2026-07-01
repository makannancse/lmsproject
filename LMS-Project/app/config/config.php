<?php

declare(strict_types=1);

// Project root (this file lives in app/config; Dotenv must load from root where `.env` lives).
$rootPath = dirname(__DIR__, 2);

// require vendor/autoload.php — Core PHP has no Composer autoload until we load it here.
$composerAutoload = $rootPath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}
require_once dirname(__DIR__) . '/lib/helpers.php';

// Load `.env` via vlucas/phpdotenv. Core PHP does NOT auto-load `.env`; without this step,
// `$_ENV['SMTP_*']` stays empty and PHPMailer runs with missing credentials.
$envFile = $rootPath . DIRECTORY_SEPARATOR . '.env';
if (class_exists(\Dotenv\Dotenv::class) && is_file($envFile)) {
    try {
        $dotenv = \Dotenv\Dotenv::createImmutable($rootPath);
        $dotenv->load();
    } catch (\Throwable $e) {
        error_log('Dotenv load failed: ' . $e->getMessage());
    }
}

function env(string $key, $default = null)
{
    return $_ENV[$key] ?? $default;
}

// Basic configuration constants
define('APP_ENV', env('APP_ENV', 'local'));

$rawAppUrl = trim((string) env('APP_URL', 'http://localhost'));
if ($rawAppUrl !== '' && !preg_match('#^https?://#i', $rawAppUrl)) {
    $rawAppUrl = 'http://' . $rawAppUrl;
}
define('APP_URL', rtrim($rawAppUrl !== '' ? $rawAppUrl : 'http://localhost', '/'));
define('APP_NAME', env('APP_NAME', 'LMS'));
define('APP_TIMEZONE', env('APP_TIMEZONE', 'UTC'));

// BASE_PATH is the URL path prefix of the app (derived from APP_URL when possible).
$appUrlPath = (string) parse_url(APP_URL, PHP_URL_PATH);
$basePath = rtrim($appUrlPath, '/');
if ($basePath === '/') {
    $basePath = '';
}

// Optional override when APP_URL path cannot be detected (rare).
$envBase = trim((string) env('APP_BASE_PATH', env('BASE_PATH', '')));
if ($envBase !== '') {
    $basePath = rtrim(str_replace('\\', '/', $envBase), '/');
}

// Fallback: derive from front controller when APP_URL has no path (CLI / misconfiguration).
if ($basePath === '') {
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptBase = rtrim(dirname($scriptName), '/');
    if (preg_match('#/admin$#', $scriptBase)) {
        $scriptBase = preg_replace('#/admin$#', '', $scriptBase) ?: '';
    }
    if ($scriptBase !== '' && $scriptBase !== '/') {
        $basePath = $scriptBase;
    }
}

define('BASE_PATH', $basePath);
define('BASE_URL', BASE_PATH === '' ? '/' : (BASE_PATH . '/'));
define('LOGO_PATH', path('assets/images/logo.png'));
define('BANNER_PATH', path('assets/images/banner.jpg'));

date_default_timezone_set(APP_TIMEZONE);

ensureUploadDirectories();

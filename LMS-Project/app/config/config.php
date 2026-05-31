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
define('APP_URL', env('APP_URL', 'http://localhost'));
define('APP_NAME', env('APP_NAME', 'LMS'));
define('APP_TIMEZONE', env('APP_TIMEZONE', 'UTC'));

// Base path for apps served from a subdirectory (e.g. /LMS-Project/public on WAMP).
// This is derived from the front controller script location so it works automatically.
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
define('BASE_PATH', ($basePath === '' || $basePath === '/') ? '' : $basePath);
define('BASE_URL', BASE_PATH === '' ? '/' : (BASE_PATH . '/'));
define('LOGO_PATH', BASE_URL . 'assets/images/logo.png');
define('BANNER_PATH', BASE_URL . 'assets/images/banner.jpg');

date_default_timezone_set(APP_TIMEZONE);


<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/lib/Auth.php';
require_once dirname(__DIR__) . '/app/lib/Database.php';
require_once dirname(__DIR__) . '/app/lib/GoogleOAuthService.php';
require_once dirname(__DIR__) . '/app/lib/GoogleCalendarMeetingService.php';
require_once dirname(__DIR__) . '/app/models/StudentPayment.php';
require_once dirname(__DIR__) . '/app/models/TeacherGoogleAccount.php';
require_once dirname(__DIR__) . '/app/controllers/ClassController.php';
require_once dirname(__DIR__) . '/app/controllers/GoogleIntegrationController.php';

$appPath = rtrim((string) parse_url((string) env('APP_URL', 'http://localhost/LMS-Project/public'), PHP_URL_PATH), '/');
$query = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . $appPath . '/auth/google/callback' . ($query !== '' ? ('?' . $query) : ''), true, 302);
exit;

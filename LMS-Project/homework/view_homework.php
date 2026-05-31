<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/lib/Auth.php';

Auth::startSession();

$base = defined('BASE_PATH') ? BASE_PATH : '';
if (Auth::isStudent()) {
    header('Location: ' . $base . '/student/homework', true, 302);
    exit;
}
header('Location: ' . $base . '/teacher/homework', true, 302);
exit;

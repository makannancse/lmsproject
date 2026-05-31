<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/lib/Auth.php';

Auth::startSession();

$base = defined('BASE_PATH') ? BASE_PATH : '';
if (Auth::isAdmin()) {
    header('Location: ' . $base . '/admin/reports', true, 302);
} else {
    header('Location: ' . $base . '/teacher/reports', true, 302);
}
exit;

<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/config.php';
require_once dirname(__DIR__, 2) . '/app/lib/Auth.php';

Auth::startSession();

$base = defined('BASE_PATH') ? BASE_PATH : '';
header('Location: ' . $base . '/admin/users/create-student', true, 302);
exit;

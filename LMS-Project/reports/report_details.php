<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/lib/Auth.php';

Auth::startSession();

$base = defined('BASE_PATH') ? BASE_PATH : '';
$id = (int) ($_GET['id'] ?? 0);
header('Location: ' . $base . '/reports/view?id=' . $id, true, 302);
exit;

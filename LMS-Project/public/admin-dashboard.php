<?php

declare(strict_types=1);

/**
 * Optional direct URL (no physical /admin/ folder): sends you to the routed admin dashboard.
 * Prefer: /LMS-Project/public/admin (handled by index.php + routes).
 */
require_once dirname(__DIR__) . '/app/config/config.php';

$base = defined('BASE_PATH') ? BASE_PATH : '';
header('Location: ' . $base . '/admin', true, 302);
exit;

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/config.php';

$query = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . url('auth/google/callback') . ($query !== '' ? ('?' . $query) : ''), true, 302);
exit;

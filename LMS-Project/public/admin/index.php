<?php

declare(strict_types=1);

$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$projectBase = preg_replace('#/admin$#', '', $base) ?: '';
header('Location: ' . $projectBase . '/admin', true, 302);
exit;

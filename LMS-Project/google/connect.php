<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/config.php';

$teacherId = isset($_GET['teacher_id']) ? ('?teacher_id=' . urlencode((string) $_GET['teacher_id'])) : '';
header('Location: ' . appRelativeUrl('/connect-google') . $teacherId, true, 302);
exit;

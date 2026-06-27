<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/config.php';

$query = isset($_GET['teacher_id']) ? ('?teacher_id=' . urlencode((string) $_GET['teacher_id'])) : '';
header('Location: ' . url('connect-google') . $query, true, 302);
exit;

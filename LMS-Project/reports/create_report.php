<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/lib/Auth.php';

Auth::startSession();

if (Auth::isAdmin()) {
    redirectTo('admin/reports/create');
}
redirectTo('teacher/reports/create');

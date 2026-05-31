<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/config.php';
require_once dirname(__DIR__, 2) . '/app/lib/Auth.php';
require_once dirname(__DIR__, 2) . '/app/controllers/KeepAliveController.php';

KeepAliveController::ping();

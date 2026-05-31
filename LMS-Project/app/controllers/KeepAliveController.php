<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';

class KeepAliveController
{
    public static function ping(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        Auth::startSession();

        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['status' => 'expired'], JSON_UNESCAPED_SLASHES);
            return;
        }

        Auth::touchActivity();

        Auth::logSessionEvent('keepalive', [
            'user_id' => Auth::userId(),
            'role' => Auth::role(),
            'seconds_until_timeout' => Auth::secondsUntilTimeout(),
        ]);

        echo json_encode([
            'status' => 'ok',
            'seconds_until_timeout' => Auth::secondsUntilTimeout(),
        ], JSON_UNESCAPED_SLASHES);
    }
}

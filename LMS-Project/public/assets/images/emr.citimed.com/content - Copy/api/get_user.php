<?php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.txt');
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/common.php';
	require_once __DIR__ . '/../../../rest/class/connection.php';

    if (!class_exists('dbConnection')) {
        throw new Exception('dbConnection class not loaded. Check common.php include path.');
    }

    $db = dbConnection::connect();

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization']
        ?? $headers['authorization']
        ?? $_SERVER['HTTP_AUTHORIZATION']
        ?? '';

    if (!$authHeader) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'No Authorization header']);
        exit;
    }

    if (!preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Invalid token format']);
        exit;
    }

    $token = $matches[1];

    $sql = "SELECT first_name, last_name,su.users_id,ur.role_id,sr.role_desc
			FROM 	tb_sys_users su
					inner join tb_sys_user_role ur on su.users_id = ur.users_id
					inner join tb_sys_role sr on ur.role_id = sr.role_id
			WHERE token = ?";

    $stmt = $db->prepare($sql);
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Invalid token']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'user' => $user
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Internal error',
        'error' => $e->getMessage()
    ]);
}
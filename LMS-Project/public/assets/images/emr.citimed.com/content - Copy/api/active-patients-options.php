<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/common.php';

function out_json($ok, $payload = array()) {
    echo json_encode(array_merge(array('ok' => $ok ? true : false), $payload));
    exit;
}

try {
    $conn = db();

    $facilities = rows($conn, "
                 SELECT facility_id, facility_desc
				FROM tb_facility
				WHERE active_flag = 1
					and facility_id in (5,6,8,9)
				ORDER BY facility_id
    ");

    $caseTypes = rows($conn, "
        SELECT case_type_id, COALESCE(case_type_desc, portal_desc) AS case_type_desc
        FROM tb_case_type
        ORDER BY COALESCE(case_type_desc, portal_desc)
    ");

    out_json(true, array(
        'facilities' => $facilities,
        'case_types' => $caseTypes
    ));
} catch (Throwable $e) {
    http_response_code(500);
    out_json(false, array(
        'message' => 'Internal error',
        'error' => $e->getMessage()
    ));
}
?>

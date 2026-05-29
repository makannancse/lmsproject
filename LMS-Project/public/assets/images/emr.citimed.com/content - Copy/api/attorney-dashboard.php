<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../../new-ui/content/class/connection.php';

    if (!class_exists('dbConnection')) {
        throw new Exception('dbConnection class not loaded.');
    }

    $db = dbConnection::connect();

    $attorneyId = isset($_GET['attorney_id']) ? (int) $_GET['attorney_id'] : 0;

    if (!$attorneyId) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'message' => 'Missing attorney_id.'
        ]);
        exit;
    }

    $sql = "
        SELECT
            atty.attorney_id,
            atty.attorney_full_name,
            pa.patient_id,
            pa.patient_full_name,
            ct.portal_desc AS case_type,
            CONVERT(varchar(10), pc.loss_dtm, 101) AS dol,
            pa.active_patient_flag,
            pc.ptonly_flag
        FROM tb_patient_case pc
        INNER JOIN tb_attorney atty
            ON pc.attorney_id = atty.attorney_id
        INNER JOIN tb_patient pa
            ON pc.patient_id = pa.patient_id
        INNER JOIN tb_case_type ct
            ON pc.case_type_id = ct.case_type_id
        WHERE
            atty.attorney_id = ?
        ORDER BY
            pa.active_patient_flag DESC,
            pc.loss_dtm DESC,
            pa.patient_full_name;
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$attorneyId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'rows' => $rows,
        'count' => count($rows),
        'attorney' => count($rows) > 0 ? [
            'attorney_id' => $rows[0]['attorney_id'],
            'attorney_full_name' => $rows[0]['attorney_full_name']
        ] : null
    ]);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'message' => 'Unable to load attorney dashboard.',
        'error' => $e->getMessage()
    ]);
}
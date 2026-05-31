<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../../new-ui/content/class/connection.php';

    if (!class_exists('dbConnection')) {
        throw new Exception('dbConnection class not loaded.');
    }

    $db = dbConnection::connect();

    $patientId = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : 0;

    if (!$patientId) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'message' => 'Missing patient_id.'
        ]);
        exit;
    }

    $caseSql = "
        SELECT TOP 1 patient_case_id
        FROM tb_patient_case
        WHERE patient_id = ?
        ORDER BY patient_case_id DESC;
    ";

    $caseStmt = $db->prepare($caseSql);
    $caseStmt->execute([$patientId]);
    $caseRow = $caseStmt->fetch(PDO::FETCH_ASSOC);

    if (!$caseRow) {
        echo json_encode([
            'ok' => true,
            'patient_id' => $patientId,
            'patient_case_id' => null,
            'rows' => []
        ]);
        exit;
    }

    $patientCaseId = (int) $caseRow['patient_case_id'];

    $sql = "
        SELECT
            pc.patient_id,
            pc.patient_case_id,
            CONVERT(varchar(10), ap.appointment_dtm_date, 101) AS appointment_date,
            aps.appointment_status_desc,
            dc.document_url_path,
            dt.document_type_desc,
            dg.document_group_desc,
            se.service_id,
            se.service_desc,
            CONVERT(varchar(10), dc.generated_dtm, 101) AS generated_date,
            CONVERT(varchar(20), dc.generated_dtm, 120) AS generated_dtm,
            do.doctor_full_name
        FROM tb_document_case dc
        INNER JOIN tb_document_type dt
            ON dc.document_type_id = dt.document_type_id
        INNER JOIN tb_document_group dg
            ON dt.document_group_id = dg.document_group_id
        INNER JOIN tb_patient_case pc
            ON dc.patient_case_id = pc.patient_case_id
        INNER JOIN tb_appointment ap
            ON dc.appointment_id = ap.appointment_id
        INNER JOIN tb_doctor do
            ON ap.doctor_id = do.doctor_id
        INNER JOIN tb_appointment_status aps
            ON ap.appt_status = aps.appointment_status_id
        INNER JOIN tb_service se
            ON ap.service_id = se.service_id
        WHERE
            pc.patient_id = ?
            AND pc.patient_case_id = ?
            AND dg.document_group_desc = 'Medical Reports'
            AND ISNULL(dc.deleted_flag, 0) = 0
        ORDER BY
            do.doctor_id,
            ap.appointment_dtm_date,
            ap.appt_from_time ASC,
            dc.generated_dtm DESC;
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$patientId, $patientCaseId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'patient_id' => $patientId,
        'patient_case_id' => $patientCaseId,
        'count' => count($rows),
        'rows' => $rows
    ]);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'message' => 'Unable to load client EHR documents.',
        'error' => $e->getMessage()
    ]);
}
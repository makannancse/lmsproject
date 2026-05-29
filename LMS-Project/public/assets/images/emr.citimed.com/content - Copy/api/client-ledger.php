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

    /*
      Get active/latest case for patient
    */
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
            'case_id' => null,
            'totals' => [
                'charges' => 0,
                'payments' => 0,
                'balance' => 0
            ],
            'charges' => [],
            'payments' => []
        ]);
        exit;
    }

    $caseId = (int) $caseRow['patient_case_id'];

    /*
      Charges
    */
    $chargesSql = "
        SELECT
            pt.patient_id,
            cs.case_type_id,
            pe.encounter_id,
            cp.encounter_cpt_id,
            pt.patient_legal_name,
            pt.patient_full_name,
            CONVERT(varchar(10), ap.appointment_dtm, 101) AS appointment_date,
            ap.appointment_id,
            cp.procedure_code,
            cp.unit_qt,
            cp.price_amt,
            dc.doctor_full_name,
            fc.facility_id AS location_id,
            fc.facility_desc,
            tt.attorney_last_name,
            tt.attorney_first_name,
            SUBSTRING(ds.cpt_desc, 1, 60) AS cpt_desc,
            cs.patient_case_id,
            CONVERT(varchar(10), COALESCE(cp.send_dtm, sb.submission_dtm), 101) AS sent_dtm,
            CONVERT(varchar(10), cp.pom_dtm, 101) AS pom_dtm,
            py.check_nbr,
            CONVERT(varchar(10), py.check_dtm, 101) AS check_dtm,
            ISNULL(py.encounter_cpt_id, 0) AS already_paid,
            ISNULL(SUM(py.payment_amt), 0) AS payment_amt
        FROM tb_appointment ap
        LEFT JOIN tb_patient_encounter pe
            ON pe.appointment_id = ap.appointment_id
        LEFT JOIN tb_patient_encounter_cpt cp
            ON pe.encounter_id = cp.encounter_id
        LEFT JOIN tb_patient pt
            ON ap.pacient_id = pt.patient_id
        LEFT JOIN tb_patient_case cs
            ON cs.patient_case_id = ap.patient_case_id
        LEFT JOIN tb_doctor dc
            ON ap.doctor_id = dc.doctor_id
        LEFT JOIN tb_facility fc
            ON fc.facility_id = ap.location_id
        LEFT JOIN tb_attorney tt
            ON tt.attorney_id = cs.attorney_id
        LEFT JOIN tb_cpt ds
            ON ds.cpt_code = cp.procedure_code
        LEFT JOIN (
            SELECT
                encounter_cpt_id,
                MAX(submission_dtm) AS submission_dtm
            FROM edi.tb_submission
            GROUP BY encounter_cpt_id
        ) sb
            ON sb.encounter_cpt_id = COALESCE(cp.former_claim_id, cp.encounter_cpt_id)
        LEFT JOIN (
            SELECT *
            FROM tb_payment
            WHERE ISNULL(deleted_flag, 0) = 0
        ) py
            ON py.encounter_cpt_id = cp.encounter_cpt_id
        WHERE
            pt.patient_id = ?
            AND cp.delete_flag = 0
            AND ISNULL(cp.procedure_code, '') <> ''
            AND ap.appt_status = 5
            AND cp.procedure_code NOT IN (
                SELECT cpt_code
                FROM tb_cpt_exclude
                WHERE start_dtm < CAST(ap.appointment_dtm AS date)
            )
        GROUP BY
            fc.facility_id,
            pt.patient_id,
            pt.eclipse_id,
            cs.case_type_id,
            pe.encounter_id,
            cp.encounter_cpt_id,
            pt.patient_legal_name,
            pt.patient_full_name,
            ap.appointment_dtm,
            ap.appointment_id,
            cp.procedure_code,
            cp.unit_qt,
            cp.price_amt,
            dc.doctor_full_name,
            fc.facility_id,
            fc.facility_desc,
            tt.attorney_last_name,
            tt.attorney_first_name,
            SUBSTRING(ds.cpt_desc, 1, 60),
            cs.patient_case_id,
            COALESCE(cp.send_dtm, sb.submission_dtm),
            cp.pom_dtm,
            ISNULL(py.encounter_cpt_id, 0),
            py.check_nbr,
            py.check_dtm
        ORDER BY
            ap.appointment_dtm DESC,
            cp.procedure_code;
    ";

    $chargeStmt = $db->prepare($chargesSql);
    $chargeStmt->execute([$patientId]);
    $charges = $chargeStmt->fetchAll(PDO::FETCH_ASSOC);

    /*
      Payments
    */
    $paymentsSql = "
        SELECT *
        FROM (
            SELECT
                pt.payment_id,
                tp.type_payment_desc,
                CONVERT(varchar(10), pt.received_dtm, 101) AS received_dtm,
                pt.check_nbr,
                CASE
                    WHEN pt.is_layer = 0 THEN gr.guarantor_desc
                    ELSE at.attorney_full_name
                END AS guarantor_desc,
                CONVERT(varchar(10), pt.dos_begin, 101) AS dos_begin,
                CONVERT(varchar(10), pt.dos_end, 101) AS dos_end,
                pt.payment_amt,
                pt.payment_amt * -1 AS reduce_amt,
                1 AS source_type
            FROM tb_payment pt
            LEFT JOIN tb_type_payment tp
                ON pt.payment_type_id = tp.type_payment_id
            LEFT JOIN tb_guarantor gr
                ON gr.guarantor_id = pt.guarantor_id
            LEFT JOIN tb_attorney at
                ON at.attorney_id = pt.guarantor_id
            WHERE
                pt.patient_case_id = ?
                AND pt.patient_id = ?
                AND ISNULL(pt.deleted_flag, 0) = 0

            UNION ALL

            SELECT
                pt.patient_id AS payment_id,
                tp.type_payment_desc,
                CONVERT(varchar(10), acc.[date], 101) AS received_dtm,
                acc.checknumber AS check_nbr,
                '' AS guarantor_desc,
                '' AS dos_begin,
                '' AS dos_end,
                acc.amount AS payment_amt,
                acc.amount * -1 AS reduce_amt,
                2 AS source_type
            FROM accounts acc
            INNER JOIN tb_patient pt
                ON acc.patientid = pt.eclipse_id
            INNER JOIN tb_type_payment tp
                ON tp.eclipse_entrytype = acc.entrytype
            WHERE
                acc.entrytype > 5
                AND pt.patient_id = ?
        ) t1
        ORDER BY
            received_dtm DESC,
            check_nbr;
    ";

    $paymentStmt = $db->prepare($paymentsSql);
    $paymentStmt->execute([$caseId, $patientId, $patientId]);
    $payments = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

    /*
      Totals
    */
    $totalCharges = 0;
    foreach ($charges as $row) {
        $totalCharges += (float) ($row['price_amt'] ?? 0);
    }

    $totalPayments = 0;
    foreach ($payments as $row) {
        $totalPayments += (float) ($row['payment_amt'] ?? 0);
    }

    $balance = $totalCharges - $totalPayments;

    echo json_encode([
        'ok' => true,
        'patient_id' => $patientId,
        'case_id' => $caseId,
        'totals' => [
            'charges' => round($totalCharges, 2),
            'payments' => round($totalPayments, 2),
            'balance' => round($balance, 2)
        ],
        'charges' => $charges,
        'payments' => $payments
    ]);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'message' => 'Unable to load client ledger.',
        'error' => $e->getMessage()
    ]);
}
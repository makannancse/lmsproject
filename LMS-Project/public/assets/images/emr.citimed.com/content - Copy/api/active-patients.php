<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/common.php';
   require_once __DIR__ . '/../../../new-ui/content/class/connection.php';

function out_json($ok, $payload = array()) {
    echo json_encode(array_merge(array('ok' => $ok ? true : false), $payload));
    exit;
}

function input_bool($data, $key) {
    return isset($data[$key]) && (int)$data[$key] === 1 ? 1 : 0;
}

function input_int_or_null($data, $key) {
    if (!isset($data[$key]) || trim((string)$data[$key]) === '') return null;
    return (int)$data[$key];
}

function input_money_or_null($data, $key) {
    if (!isset($data[$key]) || trim((string)$data[$key]) === '') return null;
    return (float)$data[$key];
}

function input_date_or_null($data, $key) {
    if (!isset($data[$key]) || trim((string)$data[$key]) === '') return null;
    $value = trim((string)$data[$key]);
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if (!$dt || $dt->format('Y-m-d') !== $value) return null;
    return $value;
}

try {
    if (!class_exists('dbConnection')) {
        throw new Exception('dbConnection class not loaded.');
    }

    $db = dbConnection::connect();

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = array();

    $facilityId = input_int_or_null($data, 'facility_id');
    $caseTypeId = input_int_or_null($data, 'case_type_id');
    $chargesLessThan = input_money_or_null($data, 'charges_less_than');
    $search = isset($data['search']) ? trim((string)$data['search']) : '';
    $npStartDate = input_date_or_null($data, 'np_start_date');
    $npEndDate = input_date_or_null($data, 'np_end_date');

    $noAttorney = input_bool($data, 'no_attorney');
    $surgicalOnly = input_bool($data, 'surgical_only');
    $noMri = input_bool($data, 'no_mri');
    $zeroPayments = input_bool($data, 'zero_payments');
    $nonCompliantTherapy = input_bool($data, 'non_compliant_therapy');

    $where = array();
    $params = array();

    if ($facilityId !== null && $facilityId > 0) {
        $where[] = "base.location_id = ?";
        $params[] = $facilityId;
    }

    if ($caseTypeId !== null && $caseTypeId > 0) {
        $where[] = "base.case_type_id = ?";
        $params[] = $caseTypeId;
    }

    if ($search !== '') {
        $where[] = "(base.patient_full_name LIKE ? OR CAST(base.patient_id AS varchar(30)) = ?)";
        $params[] = '%' . $search . '%';
        $params[] = $search;
    }

    if ($noAttorney === 1) $where[] = "ISNULL(base.attorney_id, 0) = 0";
    if ($surgicalOnly === 1) $where[] = "base.surgical_flag = 1";
    if ($noMri === 1) $where[] = "ISNULL(base.mri_count, 0) = 0";

    if ($chargesLessThan !== null) {
        $where[] = "ISNULL(base.total_charges_raw, 0) < ?";
        $params[] = $chargesLessThan;
    }

    if ($zeroPayments === 1) $where[] = "ISNULL(base.total_collected_raw, 0) = 0";

    if ($npStartDate !== null) {
        $where[] = "base.np_eval_date_raw >= ?";
        $params[] = $npStartDate;
    }

    if ($npEndDate !== null) {
        $where[] = "base.np_eval_date_raw < DATEADD(day, 1, ?)";
        $params[] = $npEndDate;
    }

    if ($nonCompliantTherapy === 1) $where[] = "base.therapy_status = 'non_compliant'";

    $whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "
WITH active_appts AS (
    SELECT ap.pacient_id AS patient_id, ap.location_id, MIN(ap.appointment_dtm_date) AS next_appointment_date
    FROM tb_appointment ap
    WHERE ap.appt_status = 1 AND ap.appointment_dtm_date >= CAST(GETDATE() AS date)
    GROUP BY ap.pacient_id, ap.location_id
),
next_appt AS (
    SELECT aa.patient_id, aa.location_id, aa.next_appointment_date, ap.appointment_id, ap.service_id, ap.doctor_id,
           ROW_NUMBER() OVER (PARTITION BY aa.patient_id ORDER BY aa.next_appointment_date ASC, ap.from_time ASC, ap.appointment_id ASC) AS rn
    FROM active_appts aa
    INNER JOIN tb_appointment ap
        ON ap.pacient_id = aa.patient_id
       AND ap.location_id = aa.location_id
       AND ap.appointment_dtm_date = aa.next_appointment_date
       AND ap.appt_status = 1
),
patient_case AS (
    SELECT pc.*, ROW_NUMBER() OVER (PARTITION BY pc.patient_id ORDER BY pc.patient_case_id DESC) AS rn
    FROM tb_patient_case pc
),
np_eval AS (
    SELECT ap.pacient_id AS patient_id, MIN(ap.appointment_dtm_date) AS np_eval_date
    FROM tb_appointment ap
    WHERE ap.service_id IN (7, 172) AND ap.appt_status = 5
    GROUP BY ap.pacient_id
),
mri AS (
    SELECT ap.pacient_id AS patient_id, COUNT(*) AS mri_count
    FROM tb_appointment ap
    INNER JOIN tb_service se ON ap.service_id = se.service_id
    WHERE se.service_group = 2 AND ap.appt_status IN (5, 6, 7)
    GROUP BY ap.pacient_id
),
charges AS (
    SELECT ap.pacient_id AS patient_id, SUM(CASE WHEN ecpt.delete_flag = 0 THEN ecpt.price_amt ELSE 0 END) AS total_charges
    FROM tb_patient_encounter_cpt ecpt
    INNER JOIN tb_patient_encounter pe ON pe.encounter_id = ecpt.encounter_id
    INNER JOIN tb_appointment ap ON ap.appointment_id = pe.appointment_id
    GROUP BY ap.pacient_id
),
payments AS (
    SELECT pay.patient_id, SUM(CASE WHEN pay.deleted_flag = 0 THEN pay.payment_amt ELSE 0 END) AS total_collected
    FROM tb_payment pay
    GROUP BY pay.patient_id
),
therapy AS (
    SELECT pa.patient_id,
           MAX(CASE WHEN ap.appt_status = 5 AND ap.appointment_dtm_date <= GETDATE() THEN ap.appointment_dtm_date END) AS last_completed_therapy_date,
           MIN(CASE WHEN ap.appt_status = 1 AND ap.appointment_dtm_date >= CAST(GETDATE() AS date) THEN ap.appointment_dtm_date END) AS next_scheduled_therapy_date
    FROM tb_patient pa
    LEFT JOIN tb_appointment ap ON ap.pacient_id = pa.patient_id
    LEFT JOIN tb_service se ON ap.service_id = se.service_id
    WHERE pa.active_patient_flag = 1 AND se.service_group = 7
    GROUP BY pa.patient_id
),
base AS (
    SELECT pa.patient_id, pa.patient_full_name, pa.cell_phone_nbr, pa.active_patient_flag,
           nap.location_id, fa.facility_desc, nap.next_appointment_date, se.service_desc AS next_service_desc, doc.doctor_full_name AS next_provider,
           pc.patient_case_id, pc.case_type_id, ct.case_type_desc, pc.attorney_id, atty.attorney_full_name,
           ISNULL(pc.surgical_flag, 0) AS surgical_flag,
           ISNULL(pc.author_ortho, 0) AS author_ortho,
           ISNULL(pc.author_spinal, 0) AS author_spinal,
           ISNULL(pc.not_ra, 0) AS not_ra,
           ISNULL(pc.not_ra_yet, 0) AS not_ra_yet,
           ISNULL(pc.treat_conservative, 0) AS treat_conservative,
           ISNULL(pc.not_author, 0) AS not_author,
           npe.np_eval_date AS np_eval_date_raw,
           CONVERT(varchar, npe.np_eval_date, 101) AS np_eval_date,
           ISNULL(mri.mri_count, 0) AS mri_count,
           ISNULL(charges.total_charges, 0) AS total_charges_raw,
           FORMAT(ISNULL(charges.total_charges, 0), 'N2') AS total_charges,
           ISNULL(pay.total_collected, 0) AS total_collected_raw,
           FORMAT(ISNULL(pay.total_collected, 0), 'N2') AS total_collected,
           CONVERT(varchar, therapy.last_completed_therapy_date, 101) AS last_completed_therapy_date,
           CONVERT(varchar, therapy.next_scheduled_therapy_date, 101) AS next_scheduled_therapy_date,
           CASE
               WHEN therapy.next_scheduled_therapy_date IS NULL THEN 'approaching_end'
               WHEN therapy.last_completed_therapy_date IS NULL THEN 'non_compliant'
               WHEN therapy.last_completed_therapy_date < DATEADD(day, -14, CAST(GETDATE() AS date)) THEN 'non_compliant'
               ELSE 'compliant'
           END AS therapy_status
    FROM next_appt nap
    INNER JOIN tb_patient pa ON pa.patient_id = nap.patient_id
    LEFT JOIN tb_facility fa ON fa.facility_id = nap.location_id
    LEFT JOIN tb_service se ON se.service_id = nap.service_id
    LEFT JOIN tb_doctor doc ON doc.doctor_id = nap.doctor_id
    LEFT JOIN patient_case pc ON pc.patient_id = pa.patient_id AND pc.rn = 1
    LEFT JOIN tb_case_type ct ON ct.case_type_id = pc.case_type_id
    LEFT JOIN tb_attorney atty ON atty.attorney_id = pc.attorney_id
    LEFT JOIN np_eval npe ON npe.patient_id = pa.patient_id
    LEFT JOIN mri ON mri.patient_id = pa.patient_id
    LEFT JOIN charges ON charges.patient_id = pa.patient_id
    LEFT JOIN payments pay ON pay.patient_id = pa.patient_id
    LEFT JOIN therapy ON therapy.patient_id = pa.patient_id
    WHERE nap.rn = 1 AND pa.active_patient_flag = 1
)
SELECT TOP 500
    base.patient_id, base.patient_full_name, base.cell_phone_nbr, base.facility_desc, base.case_type_desc,
    CONVERT(varchar, base.next_appointment_date, 101) AS next_appointment_date,
    base.next_service_desc, base.next_provider, base.np_eval_date, base.attorney_full_name,
    base.surgical_flag, base.author_ortho, base.author_spinal, base.not_ra, base.not_ra_yet,
    base.treat_conservative, base.not_author, base.mri_count, base.total_charges, base.total_collected,
    base.last_completed_therapy_date, base.next_scheduled_therapy_date, base.therapy_status
FROM base
$whereSql
ORDER BY base.patient_full_name
";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    out_json(true, array(
        'rows' => $rows,
        'row_count' => count($rows)
    ));
} catch (Throwable $e) {
    http_response_code(500);
    out_json(false, array(
        'message' => 'Internal error',
        'error' => $e->getMessage()
    ));
}
?>

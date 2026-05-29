<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/common.php';

function ai_json($ok, $payload = array()) {
    echo json_encode(array_merge(array('ok' => $ok ? true : false), $payload));
    exit;
}

function openai_api_key() {
    $key = getenv('OPENAI_API_KEY');
    if (!$key && defined('OPENAI_API_KEY')) $key = OPENAI_API_KEY;
    if (!$key && defined('EHR_OPENAI_API_KEY')) $key = EHR_OPENAI_API_KEY;
    return trim((string)$key);
}

function openai_text($apiKey, $system, $user, $temperature = 0.0) {
    $payload = array(
        'model' => 'gpt-4.1-mini',
        'temperature' => $temperature,
        'input' => array(
            array('role' => 'system', 'content' => $system),
            array('role' => 'user', 'content' => $user)
        )
    );

    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $err = curl_error($ch);
        curl_close($ch);
        ai_json(false, array('message' => 'OpenAI request failed: ' . $err));
    }
    curl_close($ch);

    $json = json_decode($response, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        ai_json(false, array(
            'message' => isset($json['error']['message']) ? $json['error']['message'] : 'OpenAI API failed',
            'http_code' => $httpCode
        ));
    }

    if (isset($json['output_text'])) return trim((string)$json['output_text']);
    if (isset($json['output'][0]['content'][0]['text'])) return trim((string)$json['output'][0]['content'][0]['text']);
    return '';
}

function extract_json_object($text) {
    $text = trim($text);
    $decoded = json_decode($text, true);
    if (is_array($decoded)) return $decoded;

    if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/is', $text, $m)) {
        $decoded = json_decode($m[1], true);
        if (is_array($decoded)) return $decoded;
    }

    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        if (is_array($decoded)) return $decoded;
    }

    return null;
}

function normalize_params($params) {
    if (!is_array($params)) return array();
    $out = array();
    foreach ($params as $p) {
        if (is_bool($p)) $out[] = $p ? 1 : 0;
        elseif (is_numeric($p) && !preg_match('/^0\d+/', (string)$p)) $out[] = $p + 0;
        else $out[] = (string)$p;
    }
    return $out;
}

function reference_rows($conn) {
    $ref = array();
    $ref['today'] = date('Y-m-d');
    $ref['facilities'] = rows($conn, "select facility_id, facility_desc from tb_facility where active_flag = 1 and facility_id in (5,7,8,9,12) order by facility_desc");
    $ref['providers'] = rows($conn, "select doctor_id, doctor_full_name from tb_doctor where active_flag = 1 and (doctor_title_id in (2,5,15) or doctor_id in (25,26)) and doctor_id not in (69) order by doctor_full_name");
    $ref['appointment_statuses'] = rows($conn, "select appointment_status_id, appointment_status_desc from tb_appointment_status order by appointment_status_desc");
    $ref['service_groups'] = rows($conn, "select distinct sg.service_group_id, sg.service_group from tb_service_group sg inner join tb_service se on se.service_group = sg.service_group_id where se.active_flag = 1 order by sg.service_group");
    $ref['important_services'] = rows($conn, "select service_id, service_desc from tb_service where service_id in (7,172,181,240,36,32,233,243) order by service_id");
    $ref['case_types'] = rows($conn, "select case_type_id, portal_desc from tb_case_type order by portal_desc");
    $ref['current_kpi_standards'] = rows($conn, "select top 1 * from kpi_standards where dtm = datefromparts(year(getdate()), month(getdate()), 1)");
    $ref['dow_weights'] = rows($conn, "select top 1 Monday, Tuesday, Wednesday, Thursday, Friday, Saturday, Sunday from kpi_standards_dow");
    return $ref;
}

function validate_ai_sql($sql, $params) {
    $trimmed = trim((string)$sql);

    if ($trimmed === '') return 'The assistant did not generate SQL.';
    if (!preg_match('/^select\s/i', $trimmed)) return 'Only SELECT queries are allowed.';
    if (strpos($trimmed, ';') !== false) return 'Semicolons and multiple statements are not allowed.';
    if (preg_match('/(--|\/\*|\*\/)/', $trimmed)) return 'SQL comments are not allowed.';
    if (preg_match('/\b(insert|update|delete|drop|alter|exec|execute|merge|truncate|create|grant|revoke|xp_|sp_)\b/i', $trimmed)) return 'Unsafe SQL keyword detected.';
    if (preg_match('/\b(into\s+outfile|openrowset|opendatasource|bulk\s+insert)\b/i', $trimmed)) return 'Unsafe SQL operation detected.';

    $allowedTables = array(
        'tb_appointment', 'tb_patient', 'tb_service', 'tb_service_group', 'tb_facility', 'tb_doctor',
        'tb_appointment_status', 'tb_patient_case', 'tb_case_type', 'tb_attorney', 'tb_guarantor_case',
        'tb_document_case', 'kpi_standards', 'kpi_standards_dow', 'holidays',
        'tb_patient_encounter', 'tb_patient_encounter_cpt','tb_cpt', 'tb_payment', 'tb_type_payment',
        'tb_sys_users', 'tb_sys_user_role', 'tb_sys_role', 'tb_clinical_note',
        'tb_clinical_int_value', 'tb_clinical_structure', 'tb_cpt_mri'
    );

    if (preg_match_all('/\b(?:from|join)\s+([a-zA-Z0-9_\.\[\]]+)/i', $trimmed, $matches)) {
        foreach ($matches[1] as $table) {
            $table = str_replace(array('[', ']'), '', $table);
            $parts = explode('.', $table);
            $tableName = strtolower(end($parts));
            if (!in_array($tableName, $allowedTables, true)) {
                return 'Query references a table that is not approved: ' . $tableName;
            }
        }
    } else {
        return 'Query must reference at least one approved table.';
    }

    $placeholderCount = substr_count($trimmed, '?');
    if ($placeholderCount !== count($params)) return 'SQL parameter count does not match placeholders.';

    return null;
}

function limit_sql_if_needed($sql) {
    $trimmed = trim($sql);
    if (preg_match('/^select\s+top\s+\(?\d+\)?\s/i', $trimmed)) return $trimmed;
    if (preg_match('/\b(count|sum|avg|min|max)\s*\(/i', $trimmed) || preg_match('/\bgroup\s+by\b/i', $trimmed)) return $trimmed;
    return preg_replace('/^select\s+/i', 'select top 100 ', $trimmed, 1);
}

$apiKey = openai_api_key();
if ($apiKey === '' || strpos($apiKey, 'sk-') !== 0) {
    ai_json(false, array('message' => 'Missing OpenAI API key. Set OPENAI_API_KEY as a server environment variable, or define EHR_OPENAI_API_KEY in api/config.php.'));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = array();

$question = isset($input['question']) ? trim((string)$input['question']) : '';
$filters = isset($input['filters']) && is_array($input['filters']) ? $input['filters'] : array();
$dashboard = isset($input['dashboard']) && is_array($input['dashboard']) ? $input['dashboard'] : array();
if ($question === '') ai_json(false, array('message' => 'Question is required.'));

$conn = db();
$reference = reference_rows($conn);
$today = date('Y-m-d');

$schema = <<<'SCHEMA'
SQL Server schema. Use ONLY these tables/columns.

Core scheduling tables:
- tb_appointment ap: appointment_id, pacient_id, service_id, location_id, doctor_id, appt_status, appointment_dtm_date, appointment_dtm, from_time, thru_time
- tb_patient pa: patient_id, patient_full_name, cell_phone_nbr, active_patient_flag
- tb_service se: service_id, service_desc, service_group, active_flag
- tb_service_group sg: service_group_id, service_group
- tb_facility fa: facility_id, facility_desc, active_flag
- tb_doctor doc: doctor_id, doctor_full_name, doctor_title_id, active_flag
- tb_appointment_status aps: appointment_status_id, appointment_status_desc
- tb_patient_case pc: patient_case_id, patient_id, case_type_id, attorney_id, ptonly_flag
- tb_case_type ct: case_type_id, portal_desc, case_type_desc
- tb_attorney atty: attorney_id, attorney_full_name
- tb_guarantor_case gc: patient_case_id, guarantor_id, guarantor_type_id
- tb_document_case dc: document_case_id, patient_case_id, document_type_id

KPI / billing / payment / portal tables from usp_rpt_steve_daily_kpi:
- kpi_standards kpi: dtm, MRIstandard, non_sx_billing, non_sx_collected, standard, np_kendall, np_midtown, np_NMB, np_Hollywood, np_OnDemand, tx_standard, tx_kendall, tx_midtown, tx_NMB, tx_Hollywood
- kpi_standards_dow dow: Monday, Tuesday, Wednesday, Thursday, Friday, Saturday, Sunday
- Holidays hd: Date
- tb_patient_encounter pe: encounter_id, appointment_id
- tb_patient_encounter_cpt ecpt: encounter_cpt_id, encounter_id, price_amt, delete_flag, procedure_code
- tb_cpt cpt: cpt_id,cpt_code,cpt_desc,cpt_fee
- tb_payment pay: patient_id, payment_type_id, received_dtm, payment_amt, deleted_flag
- tb_type_payment payt: type_payment_id, type_payment_desc
- tb_sys_users susers: users_id, create_dtm, login_name, user_full_name
- tb_sys_user_role urole: users_id, role_id
- tb_sys_role srole: role_id, role_desc
- tb_clinical_note nt: pt_note_id, appointment_id
- tb_clinical_int_value tt: note_id, note_struc_id
- tb_clinical_structure cs: note_struc_id, note_struc_desc
- tb_cpt_mri xr: struct_desc, cpt_code

Required joins:
- ap.pacient_id = pa.patient_id
- ap.service_id = se.service_id
- se.service_group = sg.service_group_id
- ap.location_id = fa.facility_id
- ap.doctor_id = doc.doctor_id
- ap.appt_status = aps.appointment_status_id
- pa.patient_id = pc.patient_id
- pc.case_type_id = ct.case_type_id
- pc.attorney_id = atty.attorney_id
- gc.patient_case_id = pc.patient_case_id
- dc.patient_case_id = pc.patient_case_id
- pe.appointment_id = ap.appointment_id
- ecpt.encounter_id = pe.encounter_id
- cpt.cpt_code = ecpt.procedure_code
- pay.payment_type_id = payt.type_payment_id
- susers.users_id = urole.users_id
- urole.role_id = srole.role_id
- nt.appointment_id = ap.appointment_id
- tt.note_id = nt.pt_note_id
- cs.note_struc_id = tt.note_struc_id
- xr.struct_desc = cs.note_struc_desc

Dashboard definitions that must match dashboard-data.php and usp_rpt_steve_daily_kpi:
- Active scheduled appointment baseline: ap.appointment_dtm_date >= convert(date, ?) and ap.appointment_dtm_date < dateadd(day, 1, convert(date, ?)); exclude canceled with ap.appt_status != 8 unless the user asks for canceled, rescheduled, all statuses, or a specific status.
- New patients scheduled/actuals: ap.service_id in (7,172). KPI MTD actuals use ap.appt_status in (5,6,7). Completed new patient visits only require ap.appt_status = 5.
- Missing EMC: patient has appointment in requested date range; active patient; MVA case pc.case_type_id = 3; no completed EMC appointment where service_id = 181 and appt_status = 5; no document_case with document_type_id in (29,83); appointment not canceled.
- Therapy Appointments / Therapy visits: use service_group_id = 7 when asking broadly for therapy appointments. KPI therapy completed uses ap.service_id in (36,32,233), ap.appt_status = 5, and excludes ap.pacient_id = 190375.
- Chiropractors are doctors with doc.doctor_title_id = 15 and doc.active_flag = 1.
- Missing PIP: patient has appointment in requested date range; active patient; MVA case pc.case_type_id = 3; no guarantor_case row where guarantor_type_id = 3; appointment not canceled.
- Missing follow up: patient has appointment in requested date range; active patient; no completed follow-up appointment where service_id = 240 and appt_status = 5; has completed NP appointment service_id in (7,172), appt_status = 5, NP date >= DATEADD(week, -4, GETDATE()); appointment not canceled.
- Missing attorney: patient has appointment in requested date range; active patient; patient_case has no linked attorney; appointment not canceled.
- Drop cases: new patient services ap.service_id in (7,172), ap.appt_status <> 5, appointment in current month, pa.active_patient_flag = 0, left join patient case with pc.ptonly_flag = 0.
- MRI actuals: service description like '%MRI%', ap.appt_status = 5 for MTD KPI actuals, exclude ap.pacient_id = 190375. MRI month comparison uses ap.appt_status in (5,6,7) and ap.location_id in (9,12).
- Billing tracking: join ecpt -> pe -> ap -> fa; use sum(ecpt.price_amt), ecpt.delete_flag = 0, ap.location_id in (5,6,7,8,9,10,11,12,16), appointment date in requested period.
- Billing by case type: join ecpt -> pe -> ap -> pc -> ct -> fa; group by ct.case_type_desc; ecpt.delete_flag = 0; location_id in (5,6,7,8,9,10,11,12,16).
- Payments tracking: sum(pay.payment_amt) by payt.type_payment_desc; pay.deleted_flag = 0; pay.patient_id != 190375; date uses pay.received_dtm.
- Portal attorney firms: srole.role_desc like 'attorney'; exclude login_name like '%@citimed.com' and user_full_name in ('testing account','Sebastian Rodriguez','Alexander Davila').
- Surgical schedule: ap.location_id not in (5,6,7,8,9,10,11,12,13,16); include date, doctor, procedure/service, surgical center/facility, status.
- Chiro stats current month: chiropractor doctors have doctor_title_id = 15. Charges/adjustments join ecpt -> pe -> ap -> doc, exclude ap.pacient_id = 190375, ecpt.delete_flag = 0, ap.location_id != 12. Adjustments use procedure_code in ('98940','98941','98942'). New patients use service_id in (7,172), Finals use service_id = 243, other visits exclude service_id in (7,172,243); chiro appointment status in (5,6,7).
- KPI tracking projection: for current month projected/tracking values often use metric * dbo.workingdays(getdate()) / (dbo.WorkingDaysSoFar(getdate()) + 1). Use kpi_standards for monthly standards when the question asks actual vs standard, tracking, goal, above/below, or KPI.
- Non-compliant patients:
  Active patients only: pa.active_patient_flag = 1.
  General non-compliant patient = active patient with missed appointments in the last 14 days.
  Missed appointment means ap.appt_status represents missed/no-show status. Use tb_appointment_status to identify the status if needed.
  Therapy non-compliance is separate: therapy uses se.service_group = 7.
  If a patient completed another service in the last 14 days, they may be generally compliant, but if they missed therapy in the last 14 days, they are non-compliant for therapy.
  When user asks for non-compliant therapy patients, filter se.service_group = 7.
  When user asks for non-compliant patients generally, show missed appointments in last 14 days and include service_group/service_desc so staff can distinguish therapy vs other services.
 - Surgical cases:
  Active patients only: pa.active_patient_flag = 1.
  Surgical case defined as pc.surgical_flag = 1.
  When listing surgical cases, include patient_id, patient_full_name, case_type_desc, and authorization flags.
SCHEMA;

$querySystem = "You are a careful SQL Server query planner for a medical scheduling dashboard.\n"
    . "Return JSON only with this exact shape: {\"sql\":\"...\",\"params\":[...],\"intent\":\"short label\",\"assumptions\":[\"...\"],\"answer_type\":\"summary|table|list\"}.\n"
    . "Accuracy rules:\n"
    . "1) Use exact IDs from Reference data when matching facilities, providers, services, and statuses. Do not guess IDs.\n"
    . "2) If the user asks for counts/summaries, return aggregate SQL, not detail rows.\n"
    . "3) If the user asks for patient lists, include patient_id, patient_full_name, appointment date, facility, provider/service/status when applicable, TOP 100.\n"
    . "4) Use ? placeholders for all dates and user-controlled values.\n"
    . "5) Default date range is today only if the user gives no date period. Today is " . $today . ".\n"
    . "6) For this week, use Monday through Sunday. For last week, previous Monday through previous Sunday.\n"
    . "7) Exclude ap.appt_status = 8 unless the user explicitly asks for canceled/rescheduled/all statuses/specific status.\n"
    . "8) For missing EMC/PIP/follow-up/attorney, copy the dashboard definitions exactly.\n"
    . "9) Use KPI definitions when question mentions KPI, actuals, standards, tracking, billing, payments, MRI, chiro stats, portal firms, surgical schedule, or drop cases.\n"
    . "10) Never invent unavailable columns such as no_show_flag, attorney_name, case_manager, appointment_type, created_at, updated_at.\n"
    . "11) Only SELECT, no semicolon, no comments.\n\n"
	. "12) Non-compliance: active patients only. General non-compliance means missed appointments in the last 14 days. Therapy non-compliance must use se.service_group = 7 and should be labeled separately from other missed services.\n"
	. $schema;

$queryUser = "Reference data from this database:\n" . json_encode($reference)
    . "\n\nCurrent dashboard filters are context only, not a hard limit unless the user says current/selected filters:\n" . json_encode($filters)
    . "\n\nCurrent dashboard summary is context only, not a hard limit:\n" . json_encode($dashboard)
    . "\n\nUser question:\n" . $question;

$queryText = openai_text($apiKey, $querySystem, $queryUser, 0.0);
$queryPlan = extract_json_object($queryText);
if (!is_array($queryPlan) || !isset($queryPlan['sql'])) {
    ai_json(false, array('message' => 'Could not create a query plan for that question.', 'debug_text' => $queryText));
}

$reviewSystem = "You are a strict SQL reviewer. Compare the SQL to the user question, schema, dashboard definitions, and reference data. Return JSON only in the same shape. If the SQL is correct, return it unchanged. If not, return corrected SQL. Enforce exact joins, date filters, status exclusions, and dashboard definitions. Only SELECT, no semicolon, no comments.";


$reviewUser = "Schema and definitions:\n" . $schema
    . "\n\nReference data:\n" . json_encode($reference)
    . "\n\nUser question:\n" . $question
    . "\n\nCandidate plan:\n" . json_encode($queryPlan);
$reviewText = openai_text($apiKey, $reviewSystem, $reviewUser, 0.0);
$reviewPlan = extract_json_object($reviewText);
if (is_array($reviewPlan) && isset($reviewPlan['sql'])) $queryPlan = $reviewPlan;

$sql = limit_sql_if_needed((string)$queryPlan['sql']);
$params = normalize_params(isset($queryPlan['params']) ? $queryPlan['params'] : array());
$validationError = validate_ai_sql($sql, $params);
if ($validationError !== null) {
    ai_json(false, array(
        'message' => 'Generated query was rejected: ' . $validationError,
        'generated_sql' => $sql,
        'params' => $params,
        'assumptions' => isset($queryPlan['assumptions']) ? $queryPlan['assumptions'] : array()
    ));
}

$resultRows = rows($conn, $sql, $params);
$sampleRows = array_slice($resultRows, 0, 100);

$summarySystem = "You are a medical scheduling dashboard assistant. Summarize SQL results for operations staff. Do not diagnose or give medical advice. Be concise and accurate. Never claim beyond returned rows.";

//$summarySystem = "You are a medical scheduling dashboard assistant. Summarize SQL results for operations staff. Do not diagnose or give medical advice. Be concise and accurate. Mention assumptions and whether rows were capped. Never claim beyond returned rows.";

$summaryUser = "User question:\n" . $question
    . "\n\nQuery intent:\n" . (isset($queryPlan['intent']) ? $queryPlan['intent'] : '')
    . "\n\nAssumptions:\n" . json_encode(isset($queryPlan['assumptions']) ? $queryPlan['assumptions'] : array())
    . "\n\nRows returned: " . count($resultRows)
    . "\n\nRows shown to you: " . count($sampleRows)
    . "\n\nRows JSON:\n" . json_encode($sampleRows);
$answer = openai_text($apiKey, $summarySystem, $summaryUser, 0.1);

ai_json(true, array(
    'answer' => $answer
    //,'rows' => $sampleRows,
    //'row_count' => count($resultRows),
    //'generated_sql' => $sql,
    //'params' => $params,
    //'intent' => isset($queryPlan['intent']) ? $queryPlan['intent'] : '',
    //'assumptions' => isset($queryPlan['assumptions']) ? $queryPlan['assumptions'] : array(),
    //'answer_type' => isset($queryPlan['answer_type']) ? $queryPlan['answer_type'] : 'summary'
));
?>

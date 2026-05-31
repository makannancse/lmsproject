<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/common.php';

function doc_json($ok, $payload = array()) {
    echo json_encode(array_merge(array('ok' => $ok ? true : false), $payload));
    exit;
}

function openai_api_key_for_docs() {
    $key = getenv('OPENAI_API_KEY');
    if (!$key && defined('OPENAI_API_KEY')) $key = OPENAI_API_KEY;
    if (!$key && defined('EHR_OPENAI_API_KEY')) $key = EHR_OPENAI_API_KEY;
    return trim((string)$key);
}

function normalize_document_url($path) {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('/^https?:\/\//i', $path)) return $path;

    $clean = str_replace('..', '', $path);
    $clean = str_replace('\\', '/', $clean);

    if (strpos($clean, '/') === 0) {
        return 'https://emr.citimed.com' . $clean;
    }

    return 'https://ehr.citimed.com/citimed/' . ltrim($clean, '/');
}

function extract_json_object_from_text($text) {
    $text = trim((string)$text);
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

function openai_summarize_documents($apiKey, $patientId, $documents) {
    $content = array();
    $content[] = array(
        'type' => 'input_text',
        'text' => "You are reviewing insurance documents for a medical billing team.\n" .
            "Patient ID: " . $patientId . "\n" .
            "Summarize denial or pending-payment reasons from the attached documents.\n" .
            "Important: EOB is only an authorization document. Do not treat EOB itself as a denial. If no EOB/payment/denial reason is found, say so.\n" .
			"Review all provided EOB, denial, PIP, payment, and explanation documents together as one patient-level review.\n" .
			"Some documents are uploaded by mistake and categorized as an EOB, but it could be some other PIP correspondence.\n".
            "Return JSON only with this shape: {\"status\":\"paid|denied|pending|no_denial_found|unknown\",\"payer\":\"\",\"claim_number\":\"\",\"denial_code\":\"\",\"denial_reason\":\"\",\"total_billed\":\"\",\"total_paid\":\"\",\"patient_responsibility\":\"\",\"dates_of_service\":[\"\"],\"recommended_action\":\"\",\"plain_english_summary\":\"\"}"
    );

    $maxFiles = count($documents);
    $added = 0;
    foreach ($documents as $doc) {
        if ($added >= $maxFiles) break;
        $url = normalize_document_url(isset($doc['document_url_path']) ? $doc['document_url_path'] : '');
        if ($url === '') continue;
        $content[] = array(
							'type' => 'input_file',
							'file_url' => $url,
							'detail' => 'high'
						);
        $added++;
    }

    if ($added === 0) {
        return array(
            'status' => 'no_denial_found',
            'payer' => '',
            'claim_number' => '',
            'denial_code' => '',
            'denial_reason' => '',
            'total_billed' => '',
            'total_paid' => '',
            'patient_responsibility' => '',
            'dates_of_service' => array(),
            'recommended_action' => 'No readable document URLs were found for this patient.',
            'plain_english_summary' => 'No document URLs were available to summarize.'
        );
    }

    $payload = array(
        'model' => 'gpt-4.1-mini',
        'temperature' => 0.0,
        'input' => array(
            array(
                'role' => 'user',
                'content' => $content
            )
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
        doc_json(false, array('message' => 'OpenAI request failed: ' . $err));
    }
    curl_close($ch);

    $json = json_decode($response, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        doc_json(false, array(
            'message' => isset($json['error']['message']) ? $json['error']['message'] : 'OpenAI API failed',
            'http_code' => $httpCode
        ));
    }

    $text = '';
    if (isset($json['output_text'])) {
        $text = trim((string)$json['output_text']);
    } elseif (isset($json['output'][0]['content'][0]['text'])) {
        $text = trim((string)$json['output'][0]['content'][0]['text']);
    }

    $summary = extract_json_object_from_text($text);
    if (!is_array($summary)) {
        $summary = array(
            'status' => 'unknown',
            'payer' => '',
            'claim_number' => '',
            'denial_code' => '',
            'denial_reason' => '',
            'total_billed' => '',
            'total_paid' => '',
            'patient_responsibility' => '',
            'dates_of_service' => array(),
            'recommended_action' => '',
            'plain_english_summary' => $text
        );
    }

    return $summary;
}

$patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
if ($patientId <= 0) {
    http_response_code(400);
    doc_json(false, array('message' => 'Missing or invalid patient_id'));
}

$apiKey = openai_api_key_for_docs();
if ($apiKey === '' || strpos($apiKey, 'sk-') !== 0) {
    http_response_code(500);
    doc_json(false, array('message' => 'Missing OpenAI API key.'));
}

$conn = db();

$caseRows = rows($conn, "
    SELECT top 1 pc.case_type_id, ct.case_type_desc
    FROM tb_patient_case pc
    LEFT JOIN tb_case_type ct ON pc.case_type_id = ct.case_type_id
    WHERE pc.patient_id = ?
    ORDER BY pc.patient_case_id DESC
", array($patientId));

$caseTypeId = isset($caseRows[0]['case_type_id']) ? (int)$caseRows[0]['case_type_id'] : 0;
$caseTypeDesc = isset($caseRows[0]['case_type_desc']) ? (string)$caseRows[0]['case_type_desc'] : '';
$isMva = ($caseTypeId === 3 || stripos($caseTypeDesc, 'MVA') !== false);

if (!$isMva) {
    doc_json(true, array(
        'patient_id' => $patientId,
        'is_mva' => false,
        'eob_on_file' => null,
        'documents' => array(),
        'summary' => array(
            'status' => 'not_applicable',
            'plain_english_summary' => 'EOB and PIP denial review are only applicable for MVA patients.',
            'recommended_action' => 'No EOB review required for this case type.'
        )
    ));
}

$eobRows = rows($conn, "
    SELECT dc.document_case_id, dc.document_type_id, dt.document_type_desc, dc.document_url_path
    FROM tb_document_case dc
    INNER JOIN tb_patient_case pc ON dc.patient_case_id = pc.patient_case_id
    LEFT JOIN tb_document_type dt ON dc.document_type_id = dt.document_type_id
    WHERE pc.patient_id = ?
      AND dc.document_type_id = 61
    ORDER BY dc.created_date DESC
", array($patientId));

$eobOnFile = count($eobRows) > 0;

if (!$eobOnFile) {
    doc_json(true, array(
        'patient_id' => $patientId,
        'is_mva' => true,
        'eob_on_file' => false,
        'documents' => array(),
        'summary' => array(
            'status' => 'missing_eob',
            'plain_english_summary' => 'This MVA patient does not have an EOB on file.',
            'recommended_action' => 'Obtain signed EOB before pursuing PIP payment review.'
        )
    ));
}

$docRows = rows($conn, "
    SELECT TOP 5
        dc.document_case_id,
        dc.document_type_id,
        dt.document_type_desc,
        dc.document_url_path
    FROM tb_document_case dc
    INNER JOIN tb_patient_case pc ON dc.patient_case_id = pc.patient_case_id
    LEFT JOIN tb_document_type dt ON dc.document_type_id = dt.document_type_id
    WHERE pc.patient_id = ?
      AND (
            dc.document_type_id = 61
            OR dt.document_type_desc LIKE '%EOB%'
            OR dt.document_type_desc LIKE '%PIP%'
            OR dt.document_type_desc LIKE '%Denial%'
            OR dt.document_type_desc LIKE '%Explanation%'
          )
      AND ISNULL(dc.document_url_path, '') <> ''
    ORDER BY dc.document_case_id DESC
", array($patientId));

$summary = openai_summarize_documents($apiKey, $patientId, $docRows);

$outDocs = array();
foreach ($docRows as $doc) {
    $outDocs[] = array(
        'document_case_id' => isset($doc['document_case_id']) ? $doc['document_case_id'] : '',
        'document_type_id' => isset($doc['document_type_id']) ? $doc['document_type_id'] : '',
        'document_type_desc' => isset($doc['document_type_desc']) ? $doc['document_type_desc'] : '',
        'document_url' => normalize_document_url(isset($doc['document_url_path']) ? $doc['document_url_path'] : '')
    );
}

doc_json(true, array(
    'patient_id' => $patientId,
    'is_mva' => true,
    'eob_on_file' => true,
    'documents' => $outDocs,
    'summary' => $summary
));

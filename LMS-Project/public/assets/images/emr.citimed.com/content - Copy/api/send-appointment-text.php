<?php
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/twilio-config.php';

api_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(false, null, array('message' => 'POST required'));
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!is_array($input)) {
    api_json(false, null, array('message' => 'Invalid JSON body'));
}

$patientId = isset($input['patient_id']) ? trim((string)$input['patient_id']) : '';
$phone = isset($input['phone']) ? trim((string)$input['phone']) : '';
$message = isset($input['message']) ? trim((string)$input['message']) : '';

if ($patientId === '' && $phone === '') {
    api_json(false, null, array('message' => 'Missing patient_id or phone'));
}

$conn = db();

if ($phone === '' && $patientId !== '') {
    $phoneRows = rows($conn, "
select
    pa.cell_phone_nbr
from tb_patient pa
where pa.patient_id = ?
", array((int)$patientId));

    if (!empty($phoneRows)) {
        $phone = trim((string)$phoneRows[0]['cell_phone_nbr']);
    }
}

function normalize_sms_phone($value) {
    $value = trim((string)$value);
    if ($value === '') return '';

    $digits = preg_replace('/[^0-9]/', '', $value);

    if (strlen($digits) === 10) {
        return '+1' . $digits;
    }

    if (strlen($digits) === 11 && substr($digits, 0, 1) === '1') {
        return '+' . $digits;
    }

    if (substr($value, 0, 1) === '+') {
        return '+' . preg_replace('/[^0-9]/', '', substr($value, 1));
    }

    return '';
}

$to = normalize_sms_phone($phone);

if ($to === '') {
    api_json(false, null, array('message' => 'Patient does not have a valid cell phone number'));
}

if ($message === '') {
    $message = 'Citimed reminder: You have an upcoming appointment. Please contact our office if you need to reschedule.';
}

$url = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Messages.json';

$postFields = http_build_query(array(
    'To' => $to,
    'MessagingServiceSid' => 'MG3a9e2765719e08d91e45eeb40e9990d9',
    'Body' => $message
));


$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);


// (temporary fix for your SSL issue)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);


$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$responseJson = json_decode($response, true);

if ($response === false || $httpCode < 200 || $httpCode >= 300) {
    api_json(false, null, array(
        'message' => 'Twilio SMS failed',
        'http_code' => $httpCode,
        'curl_error' => $curlError,
        'twilio_response' => $responseJson ? $responseJson : $response
    ));
}

api_json(true, array(
    'message' => 'SMS sent',
    'to' => $to,
    'twilio' => $responseJson
));

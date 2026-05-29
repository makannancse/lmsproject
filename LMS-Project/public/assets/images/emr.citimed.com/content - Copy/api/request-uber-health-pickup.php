<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.txt');
error_reporting(E_ALL);

function pickup_json($ok, $payload = array(), $status = 200) {
    http_response_code($status);
    echo json_encode(array_merge(array('ok' => $ok ? true : false), $payload));
    exit;
}

function pickup_input() {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : array();
}

function pickup_value($data, $key) {
    return isset($data[$key]) ? trim((string)$data[$key]) : '';
}

function pickup_iso_datetime($value) {
    if ($value === '') return '';
    $ts = strtotime($value);
    return $ts ? date('c', $ts) : '';
}

function uber_health_token($clientId, $clientSecret, $scope) {
    $ch = curl_init('https://login.uber.com/oauth/v2/token');

    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(array(
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'client_credentials',
            'scope' => $scope
        ))
    ));

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception('Uber token request failed: ' . $error);
    }

    $data = json_decode($response, true);

    if ($status < 200 || $status >= 300 || !isset($data['access_token'])) {
        throw new Exception('Uber token request was rejected.');
    }

    return $data['access_token'];
}

function write_mock_ride_log($ride) {
    $dir = __DIR__ . '/../../storage/uber-health';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $file = $dir . '/pickup-requests.jsonl';
    @file_put_contents($file, json_encode($ride) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        pickup_json(false, array('message' => 'POST required.'), 405);
    }

    $data = pickup_input();

    $patientId = pickup_value($data, 'patient_id');
    $pickupAddress = pickup_value($data, 'pickup_address');
    $dropoffAddress = pickup_value($data, 'dropoff_address');
    $pickupTime = pickup_value($data, 'pickup_time');
    $phone = pickup_value($data, 'phone');
    $notes = pickup_value($data, 'notes');

    if ($patientId === '' || $pickupAddress === '' || $dropoffAddress === '' || $pickupTime === '') {
        pickup_json(false, array('message' => 'Patient, pickup address, dropoff address, and pickup time are required.'), 400);
    }

    $pickupIso = pickup_iso_datetime($pickupTime);
    if ($pickupIso === '') {
        pickup_json(false, array('message' => 'Invalid pickup time.'), 400);
    }

    /*
      MOCK MODE while waiting for Uber Health credentials.
      Set UBER_HEALTH_MOCK_MODE=false when your Uber Health API app is approved.
    */
    $mockMode = getenv('UBER_HEALTH_MOCK_MODE');
    $mockMode = $mockMode === false || $mockMode === '' ? 'true' : strtolower($mockMode);
    $isMock = !in_array($mockMode, array('0', 'false', 'no'), true);

    if ($isMock) {
        $mockRide = array(
            'ride_id' => 'mock_uber_health_' . time() . '_' . preg_replace('/\D+/', '', $patientId),
            'patient_id' => $patientId,
            'pickup_address' => $pickupAddress,
            'dropoff_address' => $dropoffAddress,
            'pickup_time' => $pickupIso,
            'phone' => $phone,
            'notes' => $notes,
            'status' => 'mock_created',
            'created_at' => date('c')
        );

        write_mock_ride_log($mockRide);

        pickup_json(true, array(
            'mock' => true,
            'ride_id' => $mockRide['ride_id'],
            'message' => 'Mock Uber Health pickup created.'
        ));
    }

    $clientId = getenv('UBER_HEALTH_CLIENT_ID');
    $clientSecret = getenv('UBER_HEALTH_CLIENT_SECRET');
    $baseUrl = getenv('UBER_HEALTH_BASE_URL') ?: 'https://sandbox-api.uber.com/v1/health';
    $scope = getenv('UBER_HEALTH_SCOPE') ?: 'health.sandbox';

    if (!$clientId || !$clientSecret) {
        pickup_json(false, array('message' => 'Uber Health credentials are not configured.'), 500);
    }

    $token = uber_health_token($clientId, $clientSecret, $scope);

    /*
      This payload is intentionally simple for first integration testing.
      Confirm final field names with Uber Health support before production cutover.
    */
    $tripPayload = array(
        'pickup' => array('address' => $pickupAddress),
        'dropoff' => array('address' => $dropoffAddress),
        'guest' => array('phone_number' => $phone),
        'pickup_time' => $pickupIso,
        'notes' => $notes
    );

    $ch = curl_init(rtrim($baseUrl, '/') . '/trips');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ),
        CURLOPT_POSTFIELDS => json_encode($tripPayload)
    ));

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        pickup_json(false, array('message' => 'Uber Health request failed: ' . $error), 500);
    }

    $uberResponse = json_decode($response, true);

    if ($status < 200 || $status >= 300) {
        pickup_json(false, array(
            'message' => 'Uber Health request failed.',
            'uber_status' => $status,
            'uber_response' => $uberResponse
        ), $status ?: 500);
    }

    pickup_json(true, array(
        'mock' => false,
        'ride_id' => $uberResponse['trip_id'] ?? $uberResponse['id'] ?? null,
        'uber_response' => $uberResponse
    ));
} catch (Throwable $e) {
    pickup_json(false, array('message' => $e->getMessage()), 500);
}

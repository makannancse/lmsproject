<?php
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

function transcribe_json($ok, $data = []) {
    echo json_encode(array_merge(['ok' => $ok], $data));
    exit;
}

function openai_api_key() {
    $key = getenv('OPENAI_API_KEY');

    if (!$key && defined('EHR_OPENAI_API_KEY')) {
        $key = EHR_OPENAI_API_KEY;
    }

    if (!$key && defined('OPENAI_API_KEY')) {
        $key = OPENAI_API_KEY;
    }

    return trim((string)$key);
}

$apiKey = openai_api_key();

if ($apiKey === '' || strpos($apiKey, 'sk-') !== 0) {
    transcribe_json(false, [
        'error' => 'Missing OpenAI API key. Define EHR_OPENAI_API_KEY in api/config.php or set OPENAI_API_KEY on the server.'
    ]);
}

if (!isset($_FILES['audio']) || !is_uploaded_file($_FILES['audio']['tmp_name'])) {
    transcribe_json(false, [
        'error' => 'No audio file uploaded.'
    ]);
}

$tmpPath = $_FILES['audio']['tmp_name'];
$fileName = $_FILES['audio']['name'] ?: 'question.webm';

$ch = curl_init();

$mimeType = $_FILES['audio']['type'] ?: 'application/octet-stream';

$postFields = [
    "model" => "gpt-4o-mini-transcribe",
    "file" => new CURLFile($tmpPath, $mimeType, $fileName),
    "response_format" => "json"
];

curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.openai.com/v1/audio/transcriptions',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey
    ],
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    CURLOPT_POSTFIELDS => $postFields
]);

$result = curl_exec($ch);
$error = curl_error($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($error || $status >= 400) {
    transcribe_json(false, [
        'error' => $error ?: $result
    ]);
}

$data = json_decode($result, true);

transcribe_json(true, [
    'text' => $data['text'] ?? ''
]);
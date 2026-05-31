<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../new-ui/content/class/connection.php';

try {
    $db = dbConnection::connect();

    $value = isset($_GET['q']) ? trim($_GET['q']) : '';

    if ($value === '') {
        echo json_encode(['ok' => true, 'patients' => []]);
        exit;
    }

    $sql = "EXEC dbo.usp_get_patient_list ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$value]);

    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'patients' => $patients
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Patient search failed',
        'error' => $e->getMessage()
    ]);
}
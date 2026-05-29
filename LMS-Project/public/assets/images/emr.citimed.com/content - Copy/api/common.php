<?php
require_once __DIR__ . '/config.php';

function api_headers() {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
}

function api_json($ok, $data, $error = null) {
    echo json_encode(array('ok' => $ok ? true : false, 'data' => $data, 'error' => $error));
    exit;
}

function db() {
    $conn = sqlsrv_connect(EHR_SQL_SERVER, array('UID' => EHR_SQL_USER, 'PWD' => EHR_SQL_PASS));
    if ($conn === false) api_json(false, null, array('message' => 'Database connection failed', 'details' => sqlsrv_errors()));

    $use = sqlsrv_query($conn, 'USE ' . EHR_SQL_DB);
    if ($use === false) api_json(false, null, array('message' => 'USE database failed', 'details' => sqlsrv_errors()));
    sqlsrv_free_stmt($use);
    return $conn;
}

function rows($conn, $sql, $params = array()) {
    $stmt = sqlsrv_prepare($conn, $sql, $params);
    if ($stmt === false) api_json(false, null, array('message' => 'Prepare failed', 'details' => sqlsrv_errors(), 'sql' => $sql));
    if (!sqlsrv_execute($stmt)) api_json(false, null, array('message' => 'Execute failed', 'details' => sqlsrv_errors(), 'sql' => $sql));

    $out = array();
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        foreach ($r as $k => $v) {
            if ($v instanceof DateTime) {
                $r[$k] = (stripos($k, 'time') !== false && stripos($k, 'date') === false) ? $v->format('H:i:s') : $v->format('Y-m-d');
            }
        }
        $out[] = $r;
    }
    sqlsrv_free_stmt($stmt);
    return $out;
}

function p($name, $default) { return isset($_GET[$name]) ? $_GET[$name] : $default; }

function format_time($t) { return date('h:i A', strtotime($t)); }

function status_class($s) {
    $s = strtolower(trim($s));
    if (in_array($s, array('completed','is present','in service'), true)) return 'status-success';
    if (in_array($s, array('confirmed','scheduled'), true)) return 'status-info';
    if (in_array($s, array('canceled','rescheduled'), true)) return 'status-danger';
    return 'status-neutral';
}

function count_status($rows, $statuses) {
    $n = array_map('strtolower', $statuses);
    $c = 0;
    foreach ($rows as $r) if (in_array(strtolower((string)$r['appointment_status_desc']), $n, true)) $c++;
    return $c;
}

function block_data($appointments) {
    $blocks = array();
    foreach ($appointments as $r) {
        $ts = strtotime((string)$r['from_time']);
        $slot = sprintf('%02d:%02d:00', (int)date('H',$ts), floor(((int)date('i',$ts))/15)*15);
        $hm = date('H:i', strtotime($slot));

        if ($hm >= '12:00' && $hm < '14:00') {
            $slot = '12:00:00';
            $label = 'Lunch Break';
            $type = 'lunch';
        } else {
            $label = format_time($slot);
            $type = 'standard';
        }

        $dateLabel = date('M j, Y', strtotime((string)$r['appointment_dtm_date']));
        $facility = trim((string)$r['facility_desc']);
        $key = $facility . '|' . $dateLabel . '|' . $label;

        if (!isset($blocks[$key])) {
            $blocks[$key] = array(
                'facility_desc' => $facility,
                'appointment_date' => (string)$r['appointment_dtm_date'],
                'date_label' => $dateLabel,
                'time' => $label,
                'slot_time' => $slot,
                'block_type' => $type,
                'patients' => array()
            );
        }

        $blocks[$key]['patients'][] = array(
            'patient_id' => (string)$r['patient_id'],
            'patient_full_name' => (string)$r['patient_full_name'],
            'from_time' => (string)$r['from_time'],
            'thru_time' => (string)$r['thru_time'],
            'service_desc' => (string)$r['service_desc'],
            'service_group' => (string)$r['service_group'],
            'doctor_full_name' => (string)$r['doctor_full_name'],
            'appointment_status_desc' => (string)$r['appointment_status_desc'],
            'status_class' => status_class((string)$r['appointment_status_desc'])
        );
    }
    return array_values($blocks);
}

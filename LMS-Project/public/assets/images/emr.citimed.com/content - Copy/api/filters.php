<?php
require_once __DIR__ . '/common.php';
api_headers();
$conn = db();

$facilities = array(array('id' => 'All', 'name' => 'All Locations'));
foreach (rows($conn, "select fa.facility_id, replace(fa.facility_desc,'Citimed ','') as Facility from tb_facility fa where fa.active_flag = 1 and fa.facility_id in (5,7,8,9,12) order by Facility_id") as $r) {
    $facilities[] = array('id' => (string)$r['facility_id'], 'name' => (string)$r['Facility']);
}

$providers = array(array('id' => 'All', 'name' => 'All Providers'));
foreach (rows($conn, "select do.doctor_id, do.doctor_full_name, do.doctor_title_id from tb_doctor do where do.active_flag = 1 and (do.doctor_title_id in (2,5,15) or do.doctor_id in (25,26)) and do.doctor_id not in (69) order by do.doctor_title_id, do.doctor_full_name") as $r) {
    $providers[] = array('id' => (string)$r['doctor_id'], 'name' => (string)$r['doctor_full_name']);
}

$groups = array();
foreach (rows($conn, "select sg.service_group, se.service_id, se.service_desc from tb_service se inner join tb_service_group sg on se.service_group = sg.service_group_id where se.active_flag = 1 order by sg.sorting, se.service_desc") as $r) {
    $g = trim((string)$r['service_group']);
    if ($g === '') $g = 'Other';
    if (!isset($groups[$g])) $groups[$g] = array('group_name' => $g, 'services' => array());
    $groups[$g]['services'][] = array('id' => (string)$r['service_id'], 'name' => (string)$r['service_desc']);
}

$statuses = array();
foreach (rows($conn, "select aps.appointment_status_id, aps.appointment_status_desc from tb_appointment_status aps order by aps.appointment_status_desc") as $r) {
    $statuses[] = array('id' => (string)$r['appointment_status_id'], 'name' => (string)$r['appointment_status_desc']);
}

api_json(true, array(
    'facilities' => $facilities,
    'providers' => $providers,
    'serviceGroups' => array_values($groups),
    'appointmentStatuses' => $statuses
));

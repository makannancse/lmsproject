<?php

if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}


require_once __DIR__ . '/common.php';
api_headers();
$conn = db();

$today = date('Y-m-d');
$from = DateTime::createFromFormat('Y-m-d', p('date_from', $today));
$to = DateTime::createFromFormat('Y-m-d', p('date_to', p('date_from', $today)));
if (!$from) $from = new DateTime($today);
if (!$to) $to = clone $from;
if ($to < $from) { $tmp = $from; $from = $to; $to = $tmp; }

$fromSql = $from->format('Y-m-d');
$toSql = $to->format('Y-m-d');
$location = p('location', 'All');
$provider = p('provider', 'All');
$serviceGroup = p('service_group', 'All');
$services = (isset($_GET['services']) && is_array($_GET['services'])) ? $_GET['services'] : array();
$statuses = (isset($_GET['appt_statuses']) && is_array($_GET['appt_statuses'])) ? $_GET['appt_statuses'] : array();
$statusMode = p('status_filter_mode', 'custom');

$sql = "
select fa.facility_desc, fa.facility_id, pa.patient_id, pa.patient_full_name, pa.cell_phone_nbr,
ap.appointment_dtm_date, ap.from_time, ap.thru_time, se.service_id, se.service_desc,
sg.service_group, do.doctor_id, do.doctor_full_name, aps.appointment_status_desc
from tb_appointment ap
inner join tb_patient pa on ap.pacient_id = pa.patient_id
inner join tb_service se on ap.service_id = se.service_id
inner join tb_service_group sg on se.service_group = sg.service_group_id
inner join tb_facility fa on ap.location_id = fa.facility_id
inner join tb_doctor do on ap.doctor_id = do.doctor_id
inner join tb_appointment_status aps on ap.appt_status = aps.appointment_status_id
where ap.appointment_dtm_date >= convert(date, ?)
and ap.appointment_dtm_date < dateadd(day, 1, convert(date, ?))
";
$params = array($fromSql, $toSql);

if ($location !== 'All') { $sql .= " and fa.facility_id = ? "; $params[] = (int)$location; }
if ($provider !== 'All') { $sql .= " and do.doctor_id = ? "; $params[] = (int)$provider; }
if ($serviceGroup !== 'All') { $sql .= " and sg.service_group = ? "; $params[] = $serviceGroup; }
elseif (!empty($services)) {
    $ids = array();
    foreach ($services as $id) if ($id !== '') $ids[] = (int)$id;
    if (!empty($ids)) {
        $sql .= " and se.service_id in (" . implode(',', array_fill(0, count($ids), '?')) . ") ";
        foreach ($ids as $id) $params[] = $id;
    }
}
if (!empty($statuses)) {
    $ids = array();
    foreach ($statuses as $id) if ($id !== '') $ids[] = (int)$id;
    if (!empty($ids)) {
        $sql .= " and ap.appt_status in (" . implode(',', array_fill(0, count($ids), '?')) . ") ";
        foreach ($ids as $id) $params[] = $id;
    }
} elseif ($statusMode !== 'all') {
    $sql .= " and ap.appt_status != 8 ";
}
$sql .= " order by fa.facility_desc, ap.appointment_dtm_date, ap.from_time, pa.patient_full_name ";
$appointments = rows($conn, $sql, $params);

$taskParams = array($fromSql, $toSql);
$officeSql = '';
if ($location !== 'All') { $officeSql = " and fa.facility_id = ? "; $taskParams[] = (int)$location; }

$missingEmc = rows($conn, "
select distinct pa.patient_id, pa.patient_full_name, ct.case_type_id, ct.portal_desc,fa.facility_id 
from tb_appointment ap
inner join tb_patient pa on ap.pacient_id = pa.patient_id
inner join tb_patient_case pc on pa.patient_id = pc.patient_id
inner join tb_case_type ct on pc.case_type_id = ct.case_type_id
inner join tb_facility fa on ap.location_id = fa.facility_id
left join tb_appointment ap1 on ap1.pacient_id = pa.patient_id and ap1.service_id = 181 and ap1.appt_status = 5
left join tb_document_case dc2 on dc2.patient_case_id = pc.patient_case_id and dc2.document_type_id in (29,83)
where ap.appointment_dtm_date >= convert(date, ?)
and ap.appointment_dtm_date < dateadd(day, 1, convert(date, ?))
and pa.active_patient_flag = 1 and ct.case_type_id = 3
and ap1.appointment_id is null and ap.appt_status != 8 and dc2.document_case_id is null
" . $officeSql . " order by fa.facility_id,pa.patient_full_name", $taskParams);

$taskParams2 = array($fromSql, $toSql);
$officeSql2 = '';
if ($location !== 'All') { $officeSql2 = " and fa.facility_id = ? "; $taskParams2[] = (int)$location; }

$missingPip = rows($conn, "
select distinct fa.facility_desc, pa.patient_id, pa.patient_full_name, fa.facility_id
from tb_appointment ap
inner join tb_facility fa on ap.location_id = fa.facility_id
inner join tb_patient pa on ap.pacient_id = pa.patient_id
inner join tb_patient_case pc on pa.patient_id = pc.patient_id
left join tb_guarantor_case gc on gc.patient_case_id = pc.patient_case_id and gc.guarantor_type_id = 3
where ap.appointment_dtm_date >= convert(date, ?)
and ap.appointment_dtm_date < dateadd(day, 1, convert(date, ?))
and pc.case_type_id = 3 and gc.guarantor_id is null and ap.appt_status != 8 and pa.active_patient_flag = 1
" . $officeSql2 . " order by fa.facility_id, pa.patient_full_name", $taskParams2);

$missingFollowUpParams = array();
$missingFollowUpFacilitySql = '';

if ($location !== 'All') {
    $missingFollowUpFacilitySql = " and fa.facility_id = ? ";
    $missingFollowUpParams[] = (int)$location;
}

$missingFollowUpParams[] = $fromSql;
$missingFollowUpParams[] = $toSql;


$missingFollowUpPatients = rows($conn, "
select A. * from
(
select distinct
    pa.patient_id,
    pa.patient_full_name,
    pa.cell_phone_nbr,
    fa.facility_desc
from tb_appointment ap
inner join tb_patient pa 
    on ap.pacient_id = pa.patient_id
inner join tb_facility fa 
    on ap.location_id = fa.facility_id
where 
	pa.active_patient_flag = 1
    " . $missingFollowUpFacilitySql . "
    and ap.appointment_dtm_date >= convert(date, ?)
    and ap.appointment_dtm_date < dateadd(day, 1, convert(date, ?))
    and not exists (
        select 1
        from tb_appointment ap2
        where ap2.pacient_id = ap.pacient_id
          and ap2.service_id = 240
          and ap2.appt_status = 5
    )
) A
inner join 
(select ap.pacient_id,ap.appointment_dtm_date as 'NP' from tb_appointment ap
where ap.service_id in (7,172) and ap.appt_status = 5) 
B
on A.patient_id = B.pacient_id
Where B.NP >= DATEADD(week, -4, GETDATE()) 
order by A.facility_desc,A.patient_full_name
", $missingFollowUpParams);

$missingAttorneyParams = array($fromSql, $toSql);
$missingAttorneyFacilitySql = '';

if ($location !== 'All') {
    $missingAttorneyFacilitySql = " and fa.facility_id = ? ";
    $missingAttorneyParams[] = (int)$location;
}

$missingAttorneyPatients = rows($conn, "
select distinct
    pa.patient_id,
    pa.patient_full_name,
    fa.facility_desc,
    case
        when fa.facility_desc = 'Citimed NMB' then 'mtelleria@citimed.com'
        when fa.facility_desc = 'Citimed Kendall' then 'rgonzalez@citimed.com'
        when fa.facility_desc = 'Citimed Midtown' then 'cqueipo@citimed.com'
		when fa.facility_desc = 'Citimed Hollywood' then 'mtelleria@citimed.com'
        else 'mtelleria@citimed.com'
    end as case_manager_email
from tb_appointment ap
inner join tb_patient pa 
    on ap.pacient_id = pa.patient_id
inner join tb_facility fa 
    on ap.location_id = fa.facility_id
inner join tb_patient_case pc
    on pa.patient_id = pc.patient_id
left join tb_attorney atty
    on atty.attorney_id = pc.attorney_id
where 
    pa.active_patient_flag = 1
	and ap.appointment_dtm_date >= convert(date, ?)
    and ap.appointment_dtm_date < dateadd(day, 1, convert(date, ?))
    " . $missingAttorneyFacilitySql . "
    and atty.attorney_id is null
order by fa.facility_desc,pa.patient_full_name
", $missingAttorneyParams);


$scheduleAppointments = array();
foreach ($appointments as $a) {
    $time24 = date('H:i', strtotime((string)$a['from_time']));
    $scheduleAppointments[] = array(
        'date' => (string)$a['appointment_dtm_date'],
        'time' => format_time((string)$a['from_time']),
        'time24' => $time24,
        'isLunch' => ($time24 >= '12:00' && $time24 < '14:00') ? 1 : 0,
        'facility' => (string)$a['facility_desc'],
        'patient' => (string)$a['patient_full_name'],
        'patient_id' => (string)$a['patient_id'],
        'phone' => isset($a['cell_phone_nbr']) ? (string)$a['cell_phone_nbr'] : '',
        'service' => (string)$a['service_desc'],
        'doctor' => (string)$a['doctor_full_name'],
        'status' => (string)$a['appointment_status_desc']
    );
}


$allStats = array(
    array('label' => 'Appointments', 'value' => count($appointments), 'subLabel' => 'Selected range', 'class' => 'appointments', 'statusNames' => array()),
    array('label' => 'Scheduled', 'value' => count_status($appointments, array('scheduled')), 'subLabel' => 'Needs confirmation', 'class' => 'scheduled', 'statusNames' => array('scheduled')),
    array('label' => 'Confirmed', 'value' => count_status($appointments, array('confirmed')), 'subLabel' => 'Ready for visit', 'class' => 'confirmed', 'statusNames' => array('confirmed')),
    array('label' => 'In Service', 'value' => count_status($appointments, array('is present','in service')), 'subLabel' => 'Arrived / checked in', 'class' => 'inservice', 'statusNames' => array('is present','in service')),
    array('label' => 'Completed', 'value' => count_status($appointments, array('completed')), 'subLabel' => 'Closed visits', 'class' => 'completed', 'statusNames' => array('completed')),
    array('label' => 'Missed', 'value' => count_status($appointments, array('missed','no show','no-show')), 'subLabel' => 'No show', 'class' => 'missed', 'statusNames' => array('missed','no show','no-show')),
    array('label' => 'Canceled', 'value' => count_status($appointments, array('canceled','rescheduled')), 'subLabel' => 'Needs follow-up', 'class' => 'cancel', 'statusNames' => array('canceled','rescheduled'))
);

$selectedStatusNames = array();

if (!empty($statuses)) {
    $statusIdsForLookup = array();

    foreach ($statuses as $statusId) {
        if ($statusId !== '') {
            $statusIdsForLookup[] = (int)$statusId;
        }
    }

    if (!empty($statusIdsForLookup)) {
        $statusSql = "
select
    aps.appointment_status_desc
from tb_appointment_status aps
where aps.appointment_status_id in (" . implode(',', array_fill(0, count($statusIdsForLookup), '?')) . ")
";
        $statusRows = rows($conn, $statusSql, $statusIdsForLookup);

        foreach ($statusRows as $statusRow) {
            $selectedStatusNames[] = strtolower(trim((string)$statusRow['appointment_status_desc']));
        }
    }
}

$visibleStats = array();

foreach ($allStats as $stat) {
    if ($stat['label'] === 'Appointments') {
        $visibleStats[] = $stat;
        continue;
    }

    if ($statusMode === 'all' || empty($selectedStatusNames)) {
        $visibleStats[] = $stat;
        continue;
    }

    foreach ($stat['statusNames'] as $statusName) {
        if (in_array(strtolower(trim($statusName)), $selectedStatusNames, true)) {
            $visibleStats[] = $stat;
            break;
        }
    }
}

$newPatientParams = array($fromSql, $toSql);
$newPatientOfficeSql = '';

if ($location !== 'All') {
    $newPatientOfficeSql = " and fa.facility_id = ? ";
    $newPatientParams[] = (int)$location;
}

$newPatientRows = rows($conn, "
select count(ap.pacient_id) as newpatients
from tb_appointment ap
inner join tb_facility fa on ap.location_id = fa.facility_id
where ap.service_id in (7,172)
and ap.appt_status != 8
and ap.appointment_dtm_date >= convert(date, ?)
and ap.appointment_dtm_date < dateadd(day, 1, convert(date, ?))
" . $newPatientOfficeSql, $newPatientParams);

$newPatients = 0;
if (!empty($newPatientRows)) {
    $newPatients = (int)$newPatientRows[0]['newpatients'];
}



api_json(true, array(
    'range' => array('date_from' => $from->format('Y-m-d'), 'date_to' => $to->format('Y-m-d')),
	'newPatients' => $newPatients,
    'appointments' => $appointments,
    'scheduleBlocks' => block_data($appointments),
    'scheduleAppointments' => $scheduleAppointments,
    'stats' => $visibleStats,
    'alertCards' => array(
        array('key' => 'missing_emc', 'title' => count($missingEmc) . ' MVA Missing EMC', 'subtitle' => 'Motor vehicle cases without EMC', 'type' => 'warning', 'action' => 'View'),
        array('key' => 'missing_pip', 'title' => count($missingPip) . ' MVA Missing PIP Information', 'subtitle' => 'Motor vehicle cases without PIP insurance info', 'type' => 'warning', 'action' => 'View'),
		
		array('key' => 'missing_followup', 'title' => count($missingFollowUpPatients) . ' Missing Follow Up', 'subtitle' => 'Patients without Follow up Appointments', 'type' => 'warning', 'action' => 'View'),
		
		array('key' => 'missing_attorney', 'title' => count($missingAttorneyPatients) . ' Cases with No Attorney', 'subtitle' => 'Patients without Legal Representation', 'type' => 'warning', 'action' => 'View'),
				
        array('key' => 'canceled', 'title' => count_status($appointments, array('canceled')) . ' appointments canceled', 'subtitle' => 'Needs follow-up', 'type' => 'danger', 'action' => ''),
        array('key' => 'active_visits', 'title' => count_status($appointments, array('confirmed','scheduled')) . ' upcoming active visits', 'subtitle' => 'Operational queue', 'type' => 'info', 'action' => '')
    ),
    'missingEmcPatients' => $missingEmc,
    'missingPipPatients' => $missingPip,
	'missingFollowUpPatients' => $missingFollowUpPatients,
	'missingAttorneyPatients' => $missingAttorneyPatients
));

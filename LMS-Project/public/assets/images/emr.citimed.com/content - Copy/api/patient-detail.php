<?php
	header('Content-Type: application/json; charset=utf-8');

	ini_set('display_errors', '0');
	ini_set('log_errors', '1');
	ini_set('error_log', __DIR__ . '/php_error.txt');
	error_reporting(E_ALL);

	try {
		require_once __DIR__ . '/../../../new-ui/content/class/connection.php';

		if (!class_exists('dbConnection')) {
			throw new Exception('dbConnection class not loaded.');
		}

		$db = dbConnection::connect();

		$patientId = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : 0;

		if ($patientId <= 0) {
			http_response_code(400);
			echo json_encode([
				'ok' => false,
				'message' => 'Missing or invalid patient_id'
			]);
			exit;
		}

		$sql = "EXEC dbo.usp_get_patient_detail ?";
		$stmt = $db->prepare($sql);
		$stmt->execute([$patientId]);

		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if (!$rows || count($rows) === 0) {
			http_response_code(404);
			echo json_encode([
				'ok' => false,
				'message' => 'Patient not found'
			]);
			exit;
		}

		// Stored procedure may return multiple rows because of addresses/cases.
		// Use first row as primary patient record.
		$row = $rows[0];

		$patient = [
			'patient_id' => $row['patient_id'] ?? $patientId,

			// Photo
			'full_photo_path' => $row['full_photo_path'] ?? '',

			// General info
			'patient_full_name' => $row['patient_full_name'] ?? trim(
				($row['first_name'] ?? '') . ' ' .
				($row['middle_name'] ?? '') . ' ' .
				($row['last_name'] ?? '')
			),
			'first_name' => $row['first_name'] ?? '',
			'middle_name' => $row['middle_name'] ?? '',
			'last_name' => $row['last_name'] ?? '',
			'dob_dtm' => $row['dob_dtm_str'] ?? '',
			'age' => $row['patient_age'] ?? ($row['age'] ?? ''),
			'gender' => $row['patient_sex'] ?? '',
			'race' => $row['race_desc'] ?? '',
			'ethnicity' => $row['ethnicity_desc'] ?? '',
			'nationality' => $row['nationality_desc'] ?? '',
			'primary_language' => $row['primary_language_desc'] ?? '',
			'marital_status' => $row['marital_status_desc'] ?? '',

			// Contact
			'phone_nbr' => $row['phone_nbr'] ?? '',
			'cell_phone_nbr' => $row['cell_phone_nbr'] ?? '',
			'email_addr' => $row['email_addr'] ?? '',
			'emergency_contact' => $row['emergency_contact'] ?? '',
			'emergency_phone' => $row['emergency_phone'] ?? '',
			'emergency_relationship' => $row['emergency_relationship'] ?? '',

			// Home address
			'home_address1' => $row['patient_address_1'] ?? '',
			'home_address2' => $row['patient_address_2'] ?? '',
			'home_city' => $row['patient_city'] ?? '',
			'home_state' => $row['patient_state'] ?? ($row['patient_state_abbrev'] ?? ''),
			'home_zip' => $row['patient_zip'] ?? '',

			// Pickup / drop-off from tb_patient_facility_address
			'pickup_location' => $row['pickup_location'] ?? ($row['pickup_address'] ?? ''),
			'pickup_notes' => $row['pickup_notes'] ?? '',
			'dropoff_location' => $row['dropoff_location'] ?? ($row['dropoff_address'] ?? ''),
			'dropoff_notes' => $row['dropoff_notes'] ?? '',

			// Case
			'patient_case_id' => $row['patient_case_id'] ?? '',
			'case_type' => $row['case_type_desc'] ?? '',
			'facility_desc' => $row['facility_desc'] ?? '',
			'loss_dtm' => $row['accident_dtm'] ?? '',
			'accident_place_desc' => $row['accident_place_desc'] ?? '',
			'surgical_flag' => $row['surgical_flag'] ?? '',
			'other_claim_id' => $row['other_claim_id'] ?? '',

			// Attorney
			'attorney_full_name' => trim(($row['attorney_first_name'] ?? '') . ' ' . ($row['attorney_last_name'] ?? '')),
			'attorney_phone' => $row['attorney_phone_nbr'] ?? '',
			'attorney_fax' => $row['attorney_fax_nbr'] ?? '',
			'attorney_email' => $row['attorney_email_addr'] ?? '',
			'attorney_address1' => $row['attorney_address_1'] ?? '',
			'attorney_city' => $row['attorney_city'] ?? '',
			'attorney_state' => $row['attorney_state_desc'] ?? '',
			'attorney_zip' => $row['attorney_zip'] ?? '',

			// Treating doctor
			'doctor_full_name' => trim(($row['doctor_first_name'] ?? '') . ' ' . ($row['doctor_last_name'] ?? '')),
			'doctor_npi' => $row['doctor_npi'] ?? '',

			// Guarantor / claim
			'guarantor_name' => $row['guarantor_desc'] ?? '',
			'guarantor_address1' => $row['guarantor_address_1'] ?? '',
			'guarantor_address2' => $row['guarantor_address_2'] ?? '',
			'guarantor_city' => $row['guarantor_city'] ?? '',
			'guarantor_state' => $row['guarantor_state'] ?? '',
			'guarantor_zip' => $row['guarantor_zip'] ?? '',
			'guarantor_fax' => $row['guarantor_fax_nbr'] ?? '',
			'policy_nbr' => $row['policy_nbr'] ?? '',
			'party_claim_nbr' => $row['party_claim_nbr'] ?? '',
			'party_limit_per_individual' => $row['party_limit_per_individual'] ?? '',
			'party_limit_per_accident' => $row['party_limit_per_accident'] ?? '',
			'party_limit_per_limit_lifetime' => $row['party_limit_per_limit_lifetime'] ?? '',

			// These will be filled later by separate queries/endpoints
			'schedule' => [],
			'notes' => [],
			'ledger' => []
		];
			$patient['therapy_status'] = 'unknown';
			$patient['therapy_message'] = '';
			$patient['missed_therapy_visits'] = 0;
			$patient['last_completed_therapy_date'] = '';
			$patient['next_scheduled_therapy_date'] = '';
		
				$emcSql = "
				SELECT DISTINCT pa.patient_id
				FROM tb_appointment ap
				INNER JOIN tb_patient pa 
					ON ap.pacient_id = pa.patient_id
				INNER JOIN tb_patient_case pc 
					ON pa.patient_id = pc.patient_id
				INNER JOIN tb_case_type ct 
					ON pc.case_type_id = ct.case_type_id
				LEFT JOIN tb_appointment ap1 
					ON ap1.pacient_id = pa.patient_id 
					AND ap1.service_id = 181 
					AND ap1.appt_status = 5
				LEFT JOIN tb_document_case dc2 
					ON dc2.patient_case_id = pc.patient_case_id 
					AND dc2.document_type_id IN (29,83)
				WHERE pa.patient_id = ?
				AND ct.case_type_id = 3
				AND ap1.appointment_id IS NULL
				AND ap.appt_status != 8
				AND dc2.document_case_id IS NULL
			";

			$emcStmt = $db->prepare($emcSql);
			$emcStmt->execute([$patientId]);
			$emcRow = $emcStmt->fetch(PDO::FETCH_ASSOC);

			$patient['missing_emc'] = $emcRow ? true : false;
			
			$followupSql = "
				select distinct
					pa.patient_id
				from tb_appointment ap
				inner join tb_patient pa 
					on ap.pacient_id = pa.patient_id
				inner join tb_facility fa 
					on ap.location_id = fa.facility_id
				where 
					pa.patient_id = ?
					and not exists (
						select 1
						from tb_appointment ap2
						where ap2.pacient_id = ap.pacient_id
						and ap2.service_id = 240
						and ap2.appt_status = 5
					)";
					
			$followupStmt = $db->prepare($followupSql);
			$followupStmt->execute([$patientId]);
			$followupRow = $followupStmt->fetch(PDO::FETCH_ASSOC);

			$patient['missing_chiro_followup'] = $followupRow ? true : false;
			
			$missingMRISql = "
					select distinct ap.pacient_id
					from tb_appointment ap
					inner join tb_service se on ap.service_id = se.service_id
					where
						ap.pacient_id = ?
						and se.service_group = 2
						and ap.location_id = 12
						and ap.appt_status in(5,6,7)";
			$missingMRIStmt = $db->prepare($missingMRISql);
			$missingMRIStmt->execute([$patientId]);
			$missingMRIRow = $missingMRIStmt->fetch(PDO::FETCH_ASSOC);

			$patient['no_mri_on_record'] = $missingMRIRow ? false : true;	


			$therapySql = "
							WITH therapy AS (
								SELECT
									ap.pacient_id,
									ct.case_type_desc,
									ct.case_type_id,
									ap.appointment_dtm_date,
									ap.from_time,
									ap.thru_time,
									ap.appt_status,
									se.service_desc
								FROM tb_appointment ap
								INNER JOIN tb_service se 
									ON ap.service_id = se.service_id
								INNER JOIN tb_patient pa 
									ON ap.pacient_id = pa.patient_id
								INNER JOIN tb_patient_case pc 
									ON pa.patient_id = pc.patient_id
								INNER JOIN tb_case_type ct 
									ON pc.case_type_id = ct.case_type_id
								WHERE
									ap.pacient_id = ?
									AND pa.active_patient_flag = 1
									AND se.service_group = 7
							),
							summary AS (
								SELECT
									pacient_id,

									COUNT(CASE 
										WHEN appointment_dtm_date >= DATEADD(DAY, -14, CAST(GETDATE() AS DATE))
										AND appointment_dtm_date < DATEADD(DAY, 1, CAST(GETDATE() AS DATE))
										AND appt_status = 5
										THEN 1 
									END) AS completed_last_2_weeks,

									COUNT(CASE 
										WHEN appointment_dtm_date >= CAST(GETDATE() AS DATE)
										AND appt_status != 8
										THEN 1 
									END) AS future_count,

									MAX(CASE 
										WHEN case_type_desc LIKE '%MVA%' THEN 2
										WHEN case_type_desc LIKE '%LOP%' THEN 1
										ELSE 1
									END) AS expected_per_week,

									MAX(CASE
										WHEN appt_status = 5
										AND appointment_dtm_date <= GETDATE()
										THEN appointment_dtm_date
									END) AS last_completed_therapy_date,

									MIN(CASE
										WHEN appointment_dtm_date > CAST(GETDATE() AS DATE)
										AND appt_status NOT IN (8)
										THEN appointment_dtm_date
									END) AS next_scheduled_therapy_date
								FROM therapy
								GROUP BY pacient_id
							)
							SELECT
								completed_last_2_weeks,
								future_count,
								expected_per_week,
								CONVERT(varchar, last_completed_therapy_date, 101) AS last_completed_therapy_date,
								CONVERT(varchar, next_scheduled_therapy_date, 101) AS next_scheduled_therapy_date
							FROM summary
							";

					$therapyStmt = $db->prepare($therapySql);
					$therapyStmt->execute([$patientId]);
					$therapyRow = $therapyStmt->fetch(PDO::FETCH_ASSOC);
					
					$patient['last_completed_therapy_date'] = $therapyRow['last_completed_therapy_date'] ?? '';
					$patient['next_scheduled_therapy_date'] = $therapyRow['next_scheduled_therapy_date'] ?? '';
					
					if ($therapyRow) {

						$patient['last_completed_therapy_date'] = $therapyRow['last_completed_therapy_date'] ?? '';
						$patient['next_scheduled_therapy_date'] = $therapyRow['next_scheduled_therapy_date'] ?? '';

						$completed = (int)$therapyRow['completed_last_2_weeks'];
						$future = (int)$therapyRow['future_count'];

						if ($future === 0) {
							$patient['therapy_status'] = 'approaching_end';
							$patient['therapy_message'] = 'Patient approaching end of treatment';
						} elseif ($completed >= 1) {
							$patient['therapy_status'] = 'compliant';
							$patient['therapy_message'] =
								'Compliant with therapy'
								. ' | Last completed: ' . ($patient['last_completed_therapy_date'] ?: 'None')
								. ' | Next scheduled: ' . ($patient['next_scheduled_therapy_date'] ?: 'None');
						} else {
							$patient['therapy_status'] = 'non_compliant';
							$patient['therapy_message'] =
								'Non-compliant (no therapy in last 14 days)'
								. ' | Last completed: ' . ($patient['last_completed_therapy_date'] ?: 'None')
								. ' | Next scheduled: ' . ($patient['next_scheduled_therapy_date'] ?: 'None');
						}

						$patient['missed_therapy_visits'] = $completed === 0 ? 1 : 0;

					} else {
						$patient['therapy_status'] = 'unknown';
						$patient['therapy_message'] = 'No therapy data';
						$patient['missed_therapy_visits'] = 0;
						$patient['last_completed_therapy_date'] = '';
						$patient['next_scheduled_therapy_date'] = '';
					} 
					
		$summarySql = "
					SELECT
						MAX(ct.case_type_desc) AS case_type,
						
						MAX(CASE WHEN pc.surgical_flag = 1 THEN 1 ELSE 0 END) AS surgical_flag,
						MAX(CASE WHEN pc.author_ortho = 1 THEN 1 ELSE 0 END) AS author_ortho,
						MAX(CASE WHEN pc.author_spinal = 1 THEN 1 ELSE 0 END) AS author_spinal,
						MAX(CASE WHEN pc.not_ra = 1 THEN 1 ELSE 0 END) AS not_ra,
						MAX(CASE WHEN pc.not_ra_yet = 1 THEN 1 ELSE 0 END) AS not_ra_yet,
						MAX(CASE WHEN pc.treat_conservative = 1 THEN 1 ELSE 0 END) AS treat_conservative,
						MAX(CASE WHEN pc.not_author = 1 THEN 1 ELSE 0 END) AS not_author,

						MIN(CASE 
							WHEN ap.service_id IN (7,172) AND ap.appt_status <> 8
							THEN ap.appointment_dtm_date
						END) AS initial_np_date,

						MIN(CASE 
							WHEN se.service_desc LIKE '%consult%' AND ap.appt_status <> 8
							THEN ap.appointment_dtm_date
						END) AS consult_date,

						MAX(CASE 
							WHEN se.service_desc LIKE '%consult%' AND ap.appt_status <> 8
							THEN doc.doctor_full_name
						END) AS consult_doctor,

						MAX(CASE WHEN pc.ptonly_flag = 1 THEN 1 ELSE 0 END) AS ptonly_flag,

						COUNT(CASE 
							WHEN se.service_group = 7 AND ap.appt_status <> 8
							THEN 1
						END) AS therapy_total,

						COUNT(CASE 
							WHEN se.service_group = 7 AND ap.appt_status = 5
							THEN 1
						END) AS therapy_completed,

						COUNT(CASE 
							WHEN se.service_group = 7
							 AND ap.appointment_dtm_date >= CAST(GETDATE() AS DATE)
							 AND ap.appt_status <> 8
							THEN 1
						END) AS therapy_future,

						ISNULL(SUM(CASE 
							WHEN ecpt.delete_flag = 0 
							THEN ecpt.price_amt 
							ELSE 0 
						END), 0) AS total_charges,

						ISNULL((
							SELECT SUM(pay.payment_amt)
							FROM tb_payment pay
							WHERE pay.patient_id = pa.patient_id
							  AND pay.deleted_flag = 0
						), 0) AS total_collected,
						
						COUNT(CASE 
							WHEN se.service_group = 2
							 AND ap.appt_status = 5
							THEN 1
						END) AS mri_completed,

						COUNT(CASE 
							WHEN se.service_group = 2
							 AND ap.appointment_dtm_date >= CAST(GETDATE() AS DATE)
							 AND ap.appt_status <> 8
							THEN 1
						END) AS mri_future

					FROM tb_patient pa
					LEFT JOIN tb_patient_case pc 
						ON pa.patient_id = pc.patient_id
					LEFT JOIN tb_case_type ct 
						ON pc.case_type_id = ct.case_type_id
					LEFT JOIN tb_appointment ap 
						ON ap.pacient_id = pa.patient_id
					LEFT JOIN tb_service se 
						ON ap.service_id = se.service_id
					LEFT JOIN tb_doctor doc 
						ON ap.doctor_id = doc.doctor_id
					LEFT JOIN tb_patient_encounter pe 
						ON pe.appointment_id = ap.appointment_id
					LEFT JOIN tb_patient_encounter_cpt ecpt 
						ON ecpt.encounter_id = pe.encounter_id

					WHERE pa.patient_id = ?

					GROUP BY pa.patient_id
					";

					$summaryStmt = $db->prepare($summarySql);
					$summaryStmt->execute([$patientId]);
					$summaryRow = $summaryStmt->fetch(PDO::FETCH_ASSOC);

					if ($summaryRow) {
						$patient['summary'] = [
							'case_type' => $summaryRow['case_type'] ?? '',
							'initial_np_date' => $summaryRow['initial_np_date'] ? date('m/d/Y', strtotime($summaryRow['initial_np_date'])) : '',
							'consult_date' => $summaryRow['consult_date'] ? date('m/d/Y', strtotime($summaryRow['consult_date'])) : '',
							'consult_doctor' => $summaryRow['consult_doctor'] ?? '',
							'ptonly_flag' => (int)($summaryRow['ptonly_flag'] ?? 0),
							'therapy_total' => (int)($summaryRow['therapy_total'] ?? 0),
							'therapy_completed' => (int)($summaryRow['therapy_completed'] ?? 0),
							'therapy_future' => (int)($summaryRow['therapy_future'] ?? 0),
							'total_charges' => number_format((float)($summaryRow['total_charges'] ?? 0), 2),
							'total_collected' => number_format((float)($summaryRow['total_collected'] ?? 0), 2),
							'mri_completed' => (int)($summaryRow['mri_completed'] ?? 0),
							'mri_future' => (int)($summaryRow['mri_future'] ?? 0),
							'surgical_flag' => (int)($summaryRow['surgical_flag'] ?? 0),
							'author_ortho' => (int)($summaryRow['author_ortho'] ?? 0),
							'author_spinal' => (int)($summaryRow['author_spinal'] ?? 0),
							'not_ra' => (int)($summaryRow['not_ra'] ?? 0),
							'not_ra_yet' => (int)($summaryRow['not_ra_yet'] ?? 0),
							'treat_conservative' => (int)($summaryRow['treat_conservative'] ?? 0),
							'not_author' => (int)($summaryRow['not_author'] ?? 0),
						];
					} else {
						$patient['summary'] = [];
					}			
					
					$isMva = (
						(isset($row['case_type_id']) && (int)$row['case_type_id'] === 3) ||
						stripos((string)($patient['case_type'] ?? ''), 'MVA') !== false
					);

					if ($isMva) {
						$eobSql = "
							SELECT TOP 1 1
							FROM tb_document_case dc
							INNER JOIN tb_patient_case pc 
								ON dc.patient_case_id = pc.patient_case_id
							WHERE
								pc.patient_id = ?
								AND dc.document_type_id = 61
						";

						$eobStmt = $db->prepare($eobSql);
						$eobStmt->execute([$patientId]);
						$eobRow = $eobStmt->fetch(PDO::FETCH_ASSOC);

						$patient['eob_on_file'] = $eobRow ? true : false;

						if ($patient['eob_on_file']) {
							$patient['eob_denial_reason'] = 'Use ai to inteprete it.';
						} else {
							$patient['eob_denial_reason'] = 'Missing eob';
						}
					} else {
						$patient['eob_on_file'] = null;
						$patient['eob_denial_reason'] = 'Not applicable';
					}

					$patient['summary']['eob_on_file'] = $patient['eob_on_file'];
					$patient['summary']['eob_denial_reason'] = $patient['eob_denial_reason'];				
					
$surgicalReferralSql = "
SELECT TOP 1
    CASE 
        WHEN st.note_struc_desc = 'plan_neurosurgeon' THEN 'Spine Surgeon'
        WHEN st.note_struc_desc = 'plan_orthopedic' THEN 'Extremity Surgeon'
        WHEN st.note_struc_desc = 'plan_neurologist' THEN 'Neurologist'
        ELSE st.note_struc_desc
    END AS referral_type,
    fa.facility_desc,
    doc.doctor_full_name,
    nt.note_type,
    CONVERT(varchar, ap.appointment_dtm, 101) AS appointment_date,
    CONVERT(varchar, nt.created_dtm, 101) AS referral_date
FROM tb_clinical_note nt
INNER JOIN tb_clinical_int_value vl 
    ON nt.pt_note_id = vl.note_id
INNER JOIN tb_clinical_structure st 
    ON st.note_struc_id = vl.note_struc_id
INNER JOIN tb_appointment ap 
    ON ap.appointment_id = nt.appointment_id
INNER JOIN tb_facility fa 
    ON ap.location_id = fa.facility_id
INNER JOIN tb_doctor doc 
    ON ap.doctor_id = doc.doctor_id
WHERE
    ap.pacient_id = ?
    AND st.note_struc_desc IN (
        'plan_orthopedic',
        'plan_neurosurgeon',
        'plan_neurologist'
    )
ORDER BY nt.created_dtm DESC
";

$surgicalReferralStmt = $db->prepare($surgicalReferralSql);
$surgicalReferralStmt->execute([$patientId]);
$surgicalReferralRow = $surgicalReferralStmt->fetch(PDO::FETCH_ASSOC);

$patient['summary']['surgical_referral_type'] = $surgicalReferralRow['referral_type'] ?? '';
$patient['summary']['surgical_referral_doctor'] = $surgicalReferralRow['doctor_full_name'] ?? '';
$patient['summary']['surgical_referral_facility'] = $surgicalReferralRow['facility_desc'] ?? '';
$patient['summary']['surgical_referral_date'] = $surgicalReferralRow['referral_date'] ?? '';
$patient['summary']['surgical_referral_appt_date'] = $surgicalReferralRow['appointment_date'] ?? '';					

		echo json_encode([
			'ok' => true,
			'patient' => $patient,
			'raw_count' => count($rows)
		]);

	} catch (Throwable $e) {
		http_response_code(500);
		echo json_encode([
			'ok' => false,
			'message' => 'Internal error',
			'error' => $e->getMessage()
		]);
	}
?>
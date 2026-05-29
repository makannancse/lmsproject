
		function escapeHtml(str) {
							  return String(str ?? '')
								.replace(/&/g, '&amp;')
								.replace(/</g, '&lt;')
								.replace(/>/g, '&gt;')
								.replace(/"/g, '&quot;')
								.replace(/'/g, '&#039;');
							};
              
    var patientId = new URLSearchParams(window.location.search).get('patient_id') || '';
    var patientData = null;

    document.addEventListener('DOMContentLoaded', function () {
      bindPatientTabs();
      bindPatientActions();
	  
	  bindSidebarToggle();
	  
	  if (typeof loadMenuLeft === 'function') {
		loadMenuLeft();
		
      loadPatient();
	  
	  
  }
	  
    });
	
	
	function bindSidebarToggle() {
	  var app = document.getElementById('appShell');
	  var toggle = document.getElementById('menuToggle');

	  if (toggle && app) {
		toggle.onclick = function () {
		  app.classList.toggle('sidebar-collapsed');
		};
	  }
	}
	
    function bindPatientTabs() {

	
	var app = document.getElementById('appShell');
	  var toggle = document.getElementById('menuToggle');

	  if (toggle && app) {
		toggle.onclick = function () {
		  app.classList.toggle('sidebar-collapsed');
		};
	  }
	
	
	
      document.querySelectorAll('.patient-tab-btn').forEach(function (btn) {
        btn.onclick = function () {
          var section = this.getAttribute('data-section');

          document.querySelectorAll('.patient-tab-btn').forEach(function (b) {
            b.classList.toggle('active', b === btn);
          });

          document.querySelectorAll('.patient-section').forEach(function (panel) {
            panel.classList.toggle('active', panel.id === 'section-' + section);
          });
        };
      });
    }

    function bindPatientActions() {
      var refresh = document.getElementById('refreshPatientBtn');
      var printBtn = document.getElementById('printPatientBtn');

      if (refresh) refresh.onclick = loadPatient;
      if (printBtn) printBtn.onclick = function () { window.print(); };
    }

    async function loadPatient() {

      if (!patientId) {
        showPatientError('Missing patient_id in URL.');
        return;
      }

      document.getElementById('patientIdLabel').textContent = patientId;

      try {
        var res = await fetch('/new-ui/content/api/patient-detail.php?patient_id=' + encodeURIComponent(patientId), {
          method: 'GET',
          credentials: 'include'
        });

        var json = await res.json();

        if (!res.ok || !json.ok) {
          throw new Error(json.message || 'Unable to load patient.');
        }

        patientData = json.patient || {};
        renderPatient(patientData);
      } catch (err) {
        showPatientError(err.message);
      }
    }

    function renderPatient(p) {
      p = p || {};

      var fullName = p.patient_full_name || [p.first_name, p.middle_name, p.last_name].filter(Boolean).join(' ') || 'Unknown Patient';
      document.getElementById('patientName').textContent = fullName;
      var initials = document.getElementById('patientInitials');
      var photo = document.getElementById('patientPhoto');

      if (initials) initials.textContent = getInitials(fullName);

      if (photo && p.full_photo_path) {
        photo.src = p.full_photo_path;
        photo.style.display = 'block';
        if (initials) initials.style.display = 'none';

        photo.onerror = function () {
          photo.style.display = 'none';
          if (initials) initials.style.display = 'grid';
        };
      } else {
        if (photo) photo.style.display = 'none';
        if (initials) initials.style.display = 'grid';
      }
      document.getElementById('patientSummary').innerHTML = 'Patient #' + escapeHtml(p.patient_id || patientId) + ' &bull; DOB ' + escapeHtml(p.dob_dtm || '');

      renderInfoList('generalDemographics', [
        ['Patient ID', p.patient_id || patientId],
        ['First Name', p.first_name],
        ['Middle Name', p.middle_name],
        ['Last Name', p.last_name],
        ['DOB', p.dob_dtm],
        ['Age', p.age],
        ['Gender', p.gender]
      ]);

      renderInfoList('generalContact', [
        ['Home Phone', p.phone_nbr],
        ['Cell Phone', p.cell_phone_nbr],
        ['Work Phone', p.work_phone_nbr],
        ['Email', p.email_addr],
        ['Preferred Contact', p.preferred_contact]
      ]);

      renderInfoList('homeAddress', [
        ['Address 1', p.home_address1],
        ['Address 2', p.home_address2],
        ['City', p.home_city],
        ['State', p.home_state],
        ['Zip', p.home_zip]
      ]);

      renderInfoList('workAddress', [
        ['Employer', p.employer_name],
        ['Address 1', p.work_address1],
        ['Address 2', p.work_address2],
        ['City', p.work_city],
        ['State', p.work_state],
        ['Zip', p.work_zip]
      ]);

      renderInfoList('pickupAddress', [
        ['Pickup Location', p.pickup_location],
        ['Pickup Notes', p.pickup_notes]
      ]);

      renderInfoList('dropoffAddress', [
        ['Drop-off Location', p.dropoff_location],
        ['Drop-off Notes', p.dropoff_notes]
      ]);

      renderInfoList('caseDetails', [
        ['Case Type', p.case_type],
        ['Facility', p.facility_desc],
        ['Date of Loss', p.loss_dtm],
        ['Surgical Flag', p.surgical_flag],
        ['Case Status', p.case_status]
      ]);

      renderInfoList('attorneyDetails', [
        ['Attorney', p.attorney_full_name],
        ['Firm', p.firm_name],
        ['Phone', p.attorney_phone],
        ['Email', p.attorney_email]
      ]);

      renderInfoList('claimDetails', [
        ['Guarantor', p.guarantor_name],
        ['Policy #', p.policy_nbr],
        ['Claim #', p.party_claim_nbr],
        ['Insurance', p.insurance_name],
        ['Adjuster', p.adjuster_name]
      ]);

      renderSchedule(p.schedule || []);
      renderNotes(p.notes || []);
      renderLedger(p.ledger || []);
      renderPatientAlertDashboard(p);
	  renderPatientSummary(p.summary || {});
    }
	
	function renderPatientSummary(summary) {
	
  var target = document.getElementById('patientSummaryGrid');
  if (!target) return;
  
  var caseTypeHtml = escapeHtml(summary.case_type || '-');

		if (Number(summary.surgical_flag) === 1) {
		  caseTypeHtml += '<br><span class="case-surgical">SURGICAL CASE</span>';
		}

		var riskHtml = '<span class="risk-ok">Pending</span>';

		if (Number(summary.not_ra_yet) === 1) {
		  riskHtml = '<span class="risk-pending">NOT ASSESSED YET</span>';
		} else if (Number(summary.not_author) === 1) {
		  riskHtml =
			'<span class="risk-denied">NOT AUTHORIZED</span>' +
			'<br><span class="risk-sub">Not allowed to schedule consult with surgeons</span>';
		} else if (Number(summary.not_ra) === 1) {
		  riskHtml = '<span class="risk-denied">NOT AUTHORIZED</span>';
		} else if (Number(summary.treat_conservative) === 1) {
		  riskHtml =
			'<span class="risk-pending">TREAT CONSERVATIVE</span>' +
			'<br><span class="risk-sub">Therapy 1x/week · Max 2 MRIs · Ledger ≤ $15,000</span>';
		} else if (Number(summary.author_ortho) === 1 && Number(summary.author_spinal) === 1) {
		  riskHtml =
			'<span class="risk-ok">AUTHORIZED</span>' +
			'<br><span class="risk-sub">Extremity ortho + spinal</span>';
		} else if (Number(summary.author_ortho) === 1) {
		  riskHtml =
			'<span class="risk-ok">AUTHORIZED</span>' +
			'<br><span class="risk-sub">Extremity Ortho</span>';
		} else if (Number(summary.author_spinal) === 1) {
		  riskHtml =
			'<span class="risk-ok">AUTHORIZED</span>' +
			'<br><span class="risk-sub">Spinal</span>';
		}

	var riskText = 'Risk assessed';
	var riskSub = '';

	if (Number(summary.not_ra_yet) === 1) {
	  riskText = 'Not risk assessed yet';
	} else if (Number(summary.not_ra) === 1) {
	  riskText = 'Not authorized';
	} else if (Number(summary.author_ortho) === 1 && Number(summary.author_spinal) === 1) {
	  riskText = 'Authorized';
	  riskSub = 'Extremity ortho + spinal';
	} else if (Number(summary.author_ortho) === 1) {
	  riskText = 'Authorized';
	  riskSub = 'Extremity Ortho';
	} else if (Number(summary.author_spinal) === 1) {
	  riskText = 'Authorized';
	  riskSub = 'Spinal';
	}
  

  summary = summary || {};

  var initialVisitText = 'Not found';
  var initialVisitSub = '';

  if (summary.ptonly_flag === 1) {
    initialVisitText = 'PT Only';
    initialVisitSub = 'Patient case marked PT only';
  } else if (summary.initial_np_date) {
    initialVisitText = summary.initial_np_date;
    initialVisitSub = 'New patient visit';
  } else if (summary.consult_date) {
    initialVisitText = summary.consult_date;
    initialVisitSub = 'Consult with ' + (summary.consult_doctor || 'doctor');
  }

  var eobValue = summary.eob_on_file === null
    ? 'N/A'
    : (summary.eob_on_file ? 'EOB on file' : 'No EOB');

  var eobSub = summary.eob_denial_reason || '';
  if (summary.eob_on_file === true) {
    eobSub += ' ';
    //eobSub += '<a href="patient-denial-summary.html?patient_id=' + encodeURIComponent(patientId) + '">Analyze documents</a>';
	eobSub += '<button type="button" class="ghost-btn analyze-documents-btn">Analyze documents</button>';
  }
  
  var surgicalReferralValue = 'No referral found';
var surgicalReferralSub = '';

if (summary.surgical_referral_type) {
  surgicalReferralValue = summary.surgical_referral_type;
  surgicalReferralSub =
    'By ' + (summary.surgical_referral_doctor || 'Unknown doctor') +
    ' on ' + (summary.surgical_referral_date || 'unknown date');
}

  target.innerHTML =
summaryTileHtml('eob', 'EOB / Denial', eobValue, eobSub) +

summaryTile('case_type', 'Case Type', caseTypeHtml, '', true) +

summaryTile('risk', 'Risk Assessment', riskHtml, '', true) +

summaryTile('initial_visit', 'Initial Visit', initialVisitText, initialVisitSub) +

summaryTile('therapy', 'Therapy',
  String(summary.therapy_completed || 0) + ' completed',
  'Total: ' + (summary.therapy_total || 0) + ' | Future: ' + (summary.therapy_future || 0)
) +

summaryTile('mri', 'MRI Status',
  (summary.mri_completed || 0) + ' completed',
  (summary.mri_completed > 0)
    ? 'MRI completed'
    : ((summary.mri_future || 0) > 0 ? 'Scheduled in future: ' + summary.mri_future : 'No MRI completed or scheduled')
) +

summaryTile('charges', 'Total Charges', '$' + (summary.total_charges || '0.00')) +

summaryTile('collected', 'Total Collected', '$' + (summary.total_collected || '0.00')) +

summaryTile('surgical_referral', 'Surgical Referral', surgicalReferralValue, surgicalReferralSub);
	
applySummaryTileOrder();
bindSummaryTileDragDrop();	
bindAnalyzeDocumentsButton();
	
}

function bindAnalyzeDocumentsButton() {
  var btn = document.querySelector('.analyze-documents-btn');
  if (!btn) return;

  btn.onclick = function (e) {
    e.preventDefault();
    e.stopPropagation();

    window.location.href = './patient-denial-summary.html?patient_id=' + encodeURIComponent(patientId);
  };
}

function getSummaryOrderKey() {
  return 'patient_summary_tile_order';
}

function applySummaryTileOrder() {
  var grid = document.getElementById('patientSummaryGrid');
  if (!grid) return;

  var saved = localStorage.getItem(getSummaryOrderKey());
  if (!saved) return;

  var order;

  try {
    order = JSON.parse(saved);
  } catch (e) {
    return;
  }

  if (!Array.isArray(order)) return;

  order.forEach(function (key) {
    var tile = grid.querySelector('[data-summary-key="' + key + '"]');
    if (tile) grid.appendChild(tile);
  });
}

function saveSummaryTileOrder() {
  var grid = document.getElementById('patientSummaryGrid');
  if (!grid) return;

  var order = Array.from(grid.querySelectorAll('.summary-tile'))
    .map(function (tile) {
      return tile.getAttribute('data-summary-key');
    })
    .filter(Boolean);

  localStorage.setItem(getSummaryOrderKey(), JSON.stringify(order));
}

function bindSummaryTileDragDrop() {
  var grid = document.getElementById('patientSummaryGrid');
  if (!grid) return;

  var dragged = null;
  var startX = 0;
  var startY = 0;
  var isDragging = false;

  grid.querySelectorAll('.summary-tile').forEach(function (tile) {
    tile.setAttribute('draggable', 'false');

    tile.addEventListener('pointerdown', function (e) {

		  // DO NOT DRAG WHEN CLICKING BUTTONS/LINKS
		  if (
			e.target.closest('button') ||
			e.target.closest('a')
		  ) {
			return;
		  }

		  dragged = tile;
		  startX = e.clientX;
		  startY = e.clientY;
		  isDragging = false;

		  tile.setPointerCapture(e.pointerId);
		});

    tile.addEventListener('pointermove', function (e) {
      if (!dragged) return;

      var moveX = Math.abs(e.clientX - startX);
      var moveY = Math.abs(e.clientY - startY);

      if (!isDragging && moveX < 8 && moveY < 8) return;

      isDragging = true;
      dragged.classList.add('dragging');

      var target = document.elementFromPoint(e.clientX, e.clientY);
      var overTile = target ? target.closest('.summary-tile') : null;

      if (!overTile || overTile === dragged || !grid.contains(overTile)) return;

      var tiles = Array.from(grid.querySelectorAll('.summary-tile'));
      var draggedIndex = tiles.indexOf(dragged);
      var targetIndex = tiles.indexOf(overTile);

      if (draggedIndex < targetIndex) {
        grid.insertBefore(dragged, overTile.nextSibling);
      } else {
        grid.insertBefore(dragged, overTile);
      }
    });

    tile.addEventListener('pointerup', function () {
      if (!dragged) return;

      dragged.classList.remove('dragging');

      if (isDragging) {
        saveSummaryTileOrder();
      }

      dragged = null;
      isDragging = false;
    });

    tile.addEventListener('pointercancel', function () {
      if (dragged) dragged.classList.remove('dragging');
      dragged = null;
      isDragging = false;
    });
  });
}

function summaryTile(key, label, value, sub, allowHtml) {
  return '<article class="summary-tile" draggable="true" data-summary-key="' + escapeHtml(key) + '">' +
    '<div class="summary-label">' + escapeHtml(label || '') + '</div>' +
    '<div class="summary-value">' +
      (allowHtml ? value : escapeHtml(value || '-')) +
    '</div>' +
    (sub ? '<div class="summary-sub">' + escapeHtml(sub) + '</div>' : '') +
  '</article>';
}

function summaryTileHtml(key, label, value, subHtml) {
  return '<article class="summary-tile" draggable="true" data-summary-key="' + escapeHtml(key) + '">' +
    '<div class="summary-label">' + escapeHtml(label || '') + '</div>' +
    '<div class="summary-value">' + escapeHtml(value || '-') + '</div>' +
    (subHtml ? '<div class="summary-sub">' + subHtml + '</div>' : '') +
  '</article>';
}

    function renderPatientAlertDashboard(p) {
      p = p || {};

      var hasAttorney = Boolean(p.attorney_full_name || p.attorney_first_name || p.attorney_last_name || p.attorney_phone || p.attorney_email);
      var missedTherapyCount = Number(p.missed_therapy_visits || p.missed_therapy_count || 0);

      setPatientAlert(
        'alertChiroFollowUp',
        'chiroFollowUpValue',
        Boolean(p.missing_chiro_followup),
        'Missing follow up',
        'Follow up complete / not required'
      );

      setPatientAlert(
        'alertLegalRepresentation',
        'legalRepresentationValue',
        !hasAttorney,
        'No attorney on file',
        p.attorney_full_name || 'Attorney on file'
      );

      setPatientAlert(
        'alertMissingEmc',
        'missingEmcValue',
        Boolean(p.missing_emc),
        'EMC missing',
        'EMC on file / not required'
      );

      setPatientAlert(
        'alertNoMri',
        'noMriValue',
        Boolean(p.no_mri_on_record),
        'No MRI found',
        'MRI on record'
      );

      setPatientAlert(
		  'alertMissedTherapy',
		  'missedTherapyValue',
		  p.therapy_status === 'non_compliant',
		  p.therapy_message,
		  p.therapy_message
		);

      setPatientAlert(
        'alertSurgicalRecommendation',
        'surgicalRecommendationValue',
        Boolean(p.surgical_recommendation_not_scheduled),
        'Recommendation pending scheduling',
        'No unscheduled surgical recommendation'
      );
    }

    function setPatientAlert(cardId, valueId, isProblem, problemText, okText) {
      var card = document.getElementById(cardId);
      var value = document.getElementById(valueId);

      if (!card || !value) return;

      card.classList.remove('is-ok', 'is-warning', 'is-danger');
      card.classList.add(isProblem ? 'is-danger' : 'is-ok');
      value.textContent = isProblem ? problemText : okText;
    }

    function renderInfoList(targetId, rows) {
      var target = document.getElementById(targetId);
      if (!target) return;

      target.innerHTML = rows.map(function (row) {
        return '<div class="info-row">' +
          '<div class="info-label">' + escapeHtml(row[0] || '') + '</div>' +
          '<div class="info-value">' + escapeHtml(row[1] || '-') + '</div>' +
        '</div>';
      }).join('');
    }

    function renderSchedule(rows) {
      var tbody = document.getElementById('scheduleRows');
      if (!tbody) return;

      rows = Array.isArray(rows) ? rows : [];

      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="6">No appointments found.</td></tr>';
        return;
      }

      tbody.innerHTML = rows.map(function (r) {
        return '<tr>' +
          '<td>' + escapeHtml(r.appointment_date || '') + '</td>' +
          '<td>' + escapeHtml(r.appointment_time || '') + '</td>' +
          '<td>' + escapeHtml(r.facility_desc || '') + '</td>' +
          '<td>' + escapeHtml(r.provider_name || '') + '</td>' +
          '<td>' + escapeHtml(r.service_desc || '') + '</td>' +
          '<td>' + escapeHtml(r.status || '') + '</td>' +
        '</tr>';
      }).join('');
    }

    function renderNotes(rows) {
      var list = document.getElementById('notesList');
      if (!list) return;

      rows = Array.isArray(rows) ? rows : [];

      if (!rows.length) {
        list.innerHTML = '<div class="empty-state">No internal notes yet.</div>';
        return;
      }

      list.innerHTML = rows.map(function (n) {
        return '<article class="note-item">' +
          '<div class="note-meta">' + escapeHtml(n.created_by || '') + ' &bull; ' + escapeHtml(n.created_at || '') + '</div>' +
          '<div>' + escapeHtml(n.note_text || '') + '</div>' +
        '</article>';
      }).join('');
    }

    function renderLedger(rows) {
      var tbody = document.getElementById('ledgerRows');
      if (!tbody) return;

      rows = Array.isArray(rows) ? rows : [];

      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="6">No ledger activity found.</td></tr>';
        return;
      }

      tbody.innerHTML = rows.map(function (r) {
        return '<tr>' +
          '<td>' + escapeHtml(r.transaction_date || '') + '</td>' +
          '<td>' + escapeHtml(r.transaction_type || '') + '</td>' +
          '<td>' + escapeHtml(r.description || '') + '</td>' +
          '<td>' + escapeHtml(r.charge || '') + '</td>' +
          '<td>' + escapeHtml(r.payment || '') + '</td>' +
          '<td>' + escapeHtml(r.balance || '') + '</td>' +
        '</tr>';
      }).join('');
    }

    function getInitials(name) {
      return String(name || '')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map(function (part) { return part.charAt(0).toUpperCase(); })
        .join('') || '--';
    }

    function showPatientError(message) {
      document.getElementById('patientName').textContent = 'Unable to load patient';
      document.getElementById('patientSummary').textContent = message || 'Unknown error';
    }

/* ==============================
   Helpers
============================== */

function escapeHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function formatMoney(value) {
  var num = Number(String(value || 0).replace(/,/g, '').replace(/\$/g, ''));

  return num.toLocaleString('en-US', {
    style: 'currency',
    currency: 'USD'
  });
}

function setText(id, value) {
  var el = document.getElementById(id);
  if (el) el.textContent = value || '';
}

/* ==============================
   Global State
============================== */

var patientId = new URLSearchParams(window.location.search).get('patient_id') || '';
var patientData = null;
var ehrRows = [];
var ehrFiltered = [];

/* ==============================
   Page Init
============================== */

document.addEventListener('DOMContentLoaded', function () {
  bindSidebarToggle();
  bindPatientTabs();
  bindPatientActions();
  bindLedgerPdfButton();
  bindEhrSearch();

  if (typeof loadMenuLeft === 'function') {
    loadMenuLeft();
  }

  loadPatient();
  loadClientLedger();
  loadClientEhr();
  
});

/* ==============================
   Sidebar / Tabs / Actions
============================== */

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

  if (refresh) {
    refresh.onclick = function () {
      loadPatient();
      loadClientLedger();
    };
  }

  if (printBtn) {
    printBtn.onclick = function () {
      window.print();
    };
  }
}

function bindLedgerPdfButton() {
  var btn = document.getElementById('downloadLedgerPdfBtn');
  if (btn) btn.onclick = downloadLedgerPdf;
}

/* ==============================
   Patient Detail
============================== */

async function loadClientEhr() {
  if (!patientId) return;

  try {
    var res = await fetch('/new-ui/content/api/client-ehr.php?patient_id=' + encodeURIComponent(patientId), {
      credentials: 'include'
    });

    var json = await res.json();

    if (!res.ok || !json.ok) {
      throw new Error(json.message || 'Unable to load EHR.');
    }

    ehrRows = json.rows || [];
    ehrFiltered = ehrRows.slice();

    renderEhrSummary(ehrRows);
    renderEhrRows(ehrFiltered);

  } catch (err) {
    document.getElementById('ehrDocList').innerHTML =
      '<div class="empty-state">' + escapeHtml(err.message) + '</div>';
  }
}

function bindEhrSearch() {
  var input = document.getElementById('ehrSearchInput');
  if (!input) return;

  input.addEventListener('input', function () {
    var q = input.value.toLowerCase();

    ehrFiltered = ehrRows.filter(function (r) {
      return (
        String(r.doctor_full_name || '').toLowerCase().includes(q) ||
        String(r.service_desc || '').toLowerCase().includes(q) ||
        String(r.document_type_desc || '').toLowerCase().includes(q)
      );
    });

    renderEhrRows(ehrFiltered);
  });
}

function renderEhrSummary(rows) {
  var doctors = {};
  var services = {};

  rows.forEach(function (r) {
    if (r.doctor_full_name) doctors[r.doctor_full_name] = true;
    if (r.service_desc) services[r.service_desc] = true;
  });

  document.getElementById('ehrDocCount').textContent = rows.length;
  document.getElementById('ehrDoctorCount').textContent = Object.keys(doctors).length;
  document.getElementById('ehrServiceCount').textContent = Object.keys(services).length;
}


function renderEhrRows(rows) {
  var target = document.getElementById('ehrDocList');

  if (!rows.length) {
    target.innerHTML = '<div class="empty-state">No medical reports found.</div>';
    return;
  }

  target.innerHTML = rows.map(function (r) {
    return `
      <article class="ehr-doc-card" data-url="${escapeHtml(r.document_url_path || '')}">
        
        <div class="ehr-doc-main">
          <div class="ehr-doc-title">${escapeHtml(r.document_type_desc || 'Report')}</div>
          <div class="ehr-doc-sub">
            ${escapeHtml(r.service_desc || '')} • ${escapeHtml(r.appointment_date || '')}
          </div>
        </div>

        <div>
          <div class="ehr-label">Doctor</div>
          <div class="ehr-value">${escapeHtml(r.doctor_full_name || '-')}</div>
        </div>

        <div>
          <div class="ehr-label">Generated</div>
          <div class="ehr-value">${escapeHtml(r.generated_date || '-')}</div>
        </div>

        <div class="ehr-actions">
          <button class="ghost-btn open-doc-btn">Open</button>
        </div>

      </article>
    `;
  }).join('');

  // click handlers
  target.querySelectorAll('.ehr-doc-card').forEach(function (card) {
    card.onclick = function () {
      var url = card.getAttribute('data-url');
      if (url) window.open(url, '_blank');
    };
  });

  target.querySelectorAll('.open-doc-btn').forEach(function (btn) {
    btn.onclick = function (e) {
      e.stopPropagation();
      var card = btn.closest('.ehr-doc-card');
      var url = card.getAttribute('data-url');
      if (url) window.open(url, '_blank');
    };
  });
}




async function loadPatient() {
  if (!patientId) {
    showPatientError('Missing patient_id in URL.');
    return;
  }

  setText('patientIdLabel', patientId);

  try {
    var res = await fetch('/new-ui/content/api/client-detail.php?patient_id=' + encodeURIComponent(patientId), {
      method: 'GET',
      credentials: 'include'
    });

    var json = await res.json();

    if (!res.ok || !json.ok) {
      throw new Error(json.message || 'Unable to load Client.');
    }

    patientData = json.patient || {};
    renderPatient(patientData);
  } catch (err) {
    showPatientError(err.message);
  }
}

function renderPatient(p) {
  p = p || {};

  var fullName = p.patient_full_name ||
    [p.first_name, p.middle_name, p.last_name].filter(Boolean).join(' ') ||
    'Unknown Patient';

  setText('patientName', fullName);

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

  var summary = document.getElementById('patientSummary');
  if (summary) {
    summary.innerHTML = 'Patient #' + escapeHtml(p.patient_id || patientId) +
      ' &bull; DOB ' + escapeHtml(p.dob_dtm || '');
  }

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

  renderPatientAlertDashboard(p);
  renderPatientSummary(p.summary || {});
}

/* ==============================
   Client Ledger
============================== */

async function loadClientLedger() {
  if (!patientId) return;

  try {
    var res = await fetch('/new-ui/content/api/client-ledger.php?patient_id=' + encodeURIComponent(patientId), {
      credentials: 'include'
    });

    var json = await res.json();

    if (!res.ok || !json.ok) {
      throw new Error(json.message || 'Unable to load ledger.');
    }

    renderClientLedger(json);
  } catch (err) {
    renderLedgerError(err.message);
  }
}

function renderClientLedger(data) {
  var totals = data.totals || {};
  var charges = Array.isArray(data.charges) ? data.charges : [];
  var payments = Array.isArray(data.payments) ? data.payments : [];

  setText('ledgerTotalCharges', formatMoney(totals.charges));
  setText('ledgerTotalPayments', formatMoney(totals.payments));
  setText('ledgerBalance', formatMoney(totals.balance));

  renderLedgerCharges(charges);
  renderLedgerPayments(payments);
}

function renderLedgerCharges(rows) {
  var tbody = document.getElementById('ledgerChargeRows');
  if (!tbody) return;

  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="8">No charges found.</td></tr>';
    return;
  }

  tbody.innerHTML = rows.map(function (r) {
    return '<tr>' +
      '<td>' + escapeHtml(r.appointment_date || '-') + '</td>' +
      '<td>' + escapeHtml(r.facility_desc || '-') + '</td>' +
      '<td>' + escapeHtml(r.doctor_full_name || '-') + '</td>' +
      '<td>' + escapeHtml(r.procedure_code || '-') + '</td>' +
      '<td>' + escapeHtml(r.cpt_desc || '-') + '</td>' +
      '<td>' + escapeHtml(r.unit_qt || '-') + '</td>' +
      '<td>' + formatMoney(r.price_amt) + '</td>' +
      '<td>' + formatMoney(r.payment_amt) + '</td>' +
    '</tr>';
  }).join('');
}

function renderLedgerPayments(rows) {
  var tbody = document.getElementById('ledgerPaymentRows');
  if (!tbody) return;

  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="7">No payments found.</td></tr>';
    return;
  }

  tbody.innerHTML = rows.map(function (r) {
    return '<tr>' +
      '<td>' + escapeHtml(r.received_dtm || '-') + '</td>' +
      '<td>' + escapeHtml(r.type_payment_desc || '-') + '</td>' +
      '<td>' + escapeHtml(r.check_nbr || '-') + '</td>' +
      '<td>' + escapeHtml(r.guarantor_desc || '-') + '</td>' +
      '<td>' + escapeHtml(r.dos_begin || '-') + '</td>' +
      '<td>' + escapeHtml(r.dos_end || '-') + '</td>' +
      '<td>' + formatMoney(r.payment_amt) + '</td>' +
    '</tr>';
  }).join('');
}

function renderLedgerError(message) {
  var chargeRows = document.getElementById('ledgerChargeRows');
  var paymentRows = document.getElementById('ledgerPaymentRows');

  if (chargeRows) {
    chargeRows.innerHTML = '<tr><td colspan="8">' + escapeHtml(message) + '</td></tr>';
  }

  if (paymentRows) {
    paymentRows.innerHTML = '<tr><td colspan="7">' + escapeHtml(message) + '</td></tr>';
  }
}

function downloadLedgerPdf() {
  var ledger = document.querySelector('.client-ledger-panel');
  var patientName = document.getElementById('patientName') ? document.getElementById('patientName').textContent : 'Patient';

  if (!ledger) return;

  var printWindow = window.open('', '_blank');
  if (!printWindow) return;

  printWindow.document.write(
    '<html>' +
      '<head>' +
        '<title>Patient Ledger</title>' +
        '<style>' +
          'body{font-family:Arial,sans-serif;padding:24px;color:#203040;}' +
          'h1{margin:0 0 4px;color:#0d728a;font-size:22px;}' +
          'h2,h3{margin-top:20px;}' +
          '.print-sub{color:#6e7c8a;margin-bottom:20px;font-size:12px;}' +
          'button{display:none!important;}' +
          '.ledger-summary{display:flex;gap:12px;margin-bottom:20px;}' +
          '.ledger-kpi{border:1px solid #ccc;border-radius:8px;padding:10px;flex:1;}' +
          '.ledger-kpi-label{font-size:11px;text-transform:uppercase;color:#6e7c8a;font-weight:bold;}' +
          '.ledger-kpi-value{font-size:18px;font-weight:bold;margin-top:5px;}' +
          'table{width:100%;border-collapse:collapse;margin-top:10px;font-size:11px;}' +
          'th,td{border:1px solid #ccc;padding:6px;text-align:left;vertical-align:top;}' +
          'th{background:#f5f7fb;font-weight:bold;}' +
        '</style>' +
      '</head>' +
      '<body>' +
        '<h1>CitiMED Patient Ledger</h1>' +
        '<div class="print-sub">' + escapeHtml(patientName) + ' &bull; Patient #' + escapeHtml(patientId) + '</div>' +
        ledger.innerHTML +
      '</body>' +
    '</html>'
  );

  printWindow.document.close();
  printWindow.focus();
  setTimeout(function () {
    printWindow.print();
    printWindow.close();
  }, 250);
}

/* ==============================
   Patient Summary Tiles
============================== */

function renderPatientSummary(summary) {
  var target = document.getElementById('patientSummaryGrid');
  if (!target) return;

  summary = summary || {};

  var caseTypeHtml = escapeHtml(summary.case_type || '-');
  if (Number(summary.surgical_flag) === 1) {
    caseTypeHtml += '<br><span class="case-surgical">SURGICAL CASE</span>';
  }

  var initialVisitText = 'Not found';
  var initialVisitSub = '';

  if (Number(summary.ptonly_flag) === 1) {
    initialVisitText = 'PT Only';
    initialVisitSub = 'Patient case marked PT only';
  } else if (summary.initial_np_date) {
    initialVisitText = summary.initial_np_date;
    initialVisitSub = 'New patient visit';
  } else if (summary.consult_date) {
    initialVisitText = summary.consult_date;
    initialVisitSub = 'Consult with ' + (summary.consult_doctor || 'doctor');
  }

  var surgicalReferralValue = 'No referral found';
  var surgicalReferralSub = '';

  if (summary.surgical_referral_type) {
    surgicalReferralValue = summary.surgical_referral_type;
    surgicalReferralSub = 'By ' + (summary.surgical_referral_doctor || 'Unknown doctor') +
      ' on ' + (summary.surgical_referral_date || 'unknown date');
  }

  target.innerHTML =
    summaryTile('case_type', 'Case Type', caseTypeHtml, '', true) +
    summaryTile('initial_visit', 'Initial Visit', initialVisitText, initialVisitSub) +
    summaryTile(
      'mri',
      'MRI Status',
      (summary.mri_completed || 0) + ' completed',
      Number(summary.mri_completed || 0) > 0
        ? 'MRI completed'
        : (Number(summary.mri_future || 0) > 0
            ? 'Scheduled in future: ' + summary.mri_future
            : 'No MRI completed or scheduled')
    ) +
    summaryTile('charges', 'Total Charges', '$' + (summary.total_charges || '0.00')) +
    summaryTile('surgical_referral', 'Surgical Referral', surgicalReferralValue, surgicalReferralSub);

  applySummaryTileOrder();
  bindSummaryTileDragDrop();
}

function summaryTile(key, label, value, sub, allowHtml) {
  return '<article class="summary-tile" draggable="false" data-summary-key="' + escapeHtml(key) + '">' +
    '<div class="summary-label">' + escapeHtml(label || '') + '</div>' +
    '<div class="summary-value">' + (allowHtml ? value : escapeHtml(value || '-')) + '</div>' +
    (sub ? '<div class="summary-sub">' + escapeHtml(sub) + '</div>' : '') +
  '</article>';
}

function getSummaryOrderKey() {
  return 'client_summary_tile_order';
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
    tile.addEventListener('pointerdown', function (e) {
      dragged = tile;
      startX = e.clientX;
      startY = e.clientY;
      isDragging = false;

      if (tile.setPointerCapture) {
        tile.setPointerCapture(e.pointerId);
      }
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

      if (isDragging) saveSummaryTileOrder();

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

/* ==============================
   Alerts
============================== */

function renderPatientAlertDashboard(p) {
  p = p || {};

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
    p.therapy_message || 'Therapy risk',
    p.therapy_message || 'No therapy risk'
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

/* ==============================
   Generic Render Helpers
============================== */

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

function getInitials(name) {
  return String(name || '')
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map(function (part) {
      return part.charAt(0).toUpperCase();
    })
    .join('') || '--';
}

function showPatientError(message) {
  setText('patientName', 'Unable to load client');
  setText('patientSummary', message || 'Unknown error');
}

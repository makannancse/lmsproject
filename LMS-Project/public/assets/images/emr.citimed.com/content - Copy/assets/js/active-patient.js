 
    var activeRows = [];
    var activeSort = { key: 'patient_full_name', dir: 'asc' };
    var FILTER_KEY = 'active_patients_filters';

    document.addEventListener('DOMContentLoaded', function () {
      bindActivePatientsPage();
      loadSavedFilters();
      loadFilterOptions();
      loadActivePatients();
	  bindSidebarToggle();
		if (typeof loadMenuLeft === 'function') {
		loadMenuLeft();
		};
	
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
	
	
	
	
    function bindActivePatientsPage() {
		
	document.querySelectorAll('.active-results-header button').forEach(function (btn) {
		  btn.onclick = function () {
			var key = btn.getAttribute('data-sort-key');

			if (activeSort.key === key) {
			  activeSort.dir = activeSort.dir === 'asc' ? 'desc' : 'asc';
			} else {
			  activeSort.key = key;
			  activeSort.dir = 'asc';
			}

			renderActiveRows(activeRows);
		  };
		});	
	
	  var app = document.getElementById('appShell');

      document.getElementById('searchBtn').onclick = function () {
        saveFilters();
        loadActivePatients();
      };

      document.getElementById('resetFiltersBtn').onclick = function () {
        localStorage.removeItem(FILTER_KEY);
        document.querySelectorAll('.filter-panel input').forEach(function (el) {
          if (el.type === 'checkbox') el.checked = false;
          else el.value = '';
        });
        document.querySelectorAll('.filter-panel select').forEach(function (el) { el.value = ''; });
        loadActivePatients();
      };


    }

    async function loadFilterOptions() {
      try {
        var res = await fetch('/new-ui/content/api/active-patients-options.php', { credentials: 'include' });
        var json = await res.json();
        if (!json.ok) return;

        fillSelect('facilityId', json.facilities || [], 'facility_id', 'facility_desc');
        fillSelect('caseTypeId', json.case_types || [], 'case_type_id', 'case_type_desc');
        loadSavedFilters();
      } catch (err) {
        console.warn('Unable to load filter options', err);
      }
    }

    function fillSelect(id, rows, valueKey, textKey) {
      var select = document.getElementById(id);
      var first = select.querySelector('option');
      select.innerHTML = '';
      select.appendChild(first);

      rows.forEach(function (r) {
        var opt = document.createElement('option');
        opt.value = r[valueKey];
        opt.textContent = r[textKey];
        select.appendChild(opt);
      });
    }

    function getFilters() {
      return {
        facility_id: document.getElementById('facilityId').value,
        case_type_id: document.getElementById('caseTypeId').value,
        charges_less_than: document.getElementById('chargesLessThan').value,
        search: document.getElementById('searchText').value.trim(),
        np_start_date: document.getElementById('npStartDate').value,
        np_end_date: document.getElementById('npEndDate').value,
        no_attorney: document.getElementById('noAttorney').checked ? 1 : 0,
        surgical_only: document.getElementById('surgicalOnly').checked ? 1 : 0,
        no_mri: document.getElementById('noMri').checked ? 1 : 0,
        zero_payments: document.getElementById('zeroPayments').checked ? 1 : 0,
        non_compliant_therapy: document.getElementById('nonCompliantTherapy').checked ? 1 : 0
      };
    }

    function saveFilters() {
      localStorage.setItem(FILTER_KEY, JSON.stringify(getFilters()));
    }

    function loadSavedFilters() {
      var raw = localStorage.getItem(FILTER_KEY);
      if (!raw) return;

      var f;
      try { f = JSON.parse(raw); } catch (e) { return; }

      document.getElementById('facilityId').value = f.facility_id || '';
      document.getElementById('caseTypeId').value = f.case_type_id || '';
      document.getElementById('chargesLessThan').value = f.charges_less_than || '';
      document.getElementById('searchText').value = f.search || '';
      document.getElementById('npStartDate').value = f.np_start_date || '';
      document.getElementById('npEndDate').value = f.np_end_date || '';
      document.getElementById('noAttorney').checked = Number(f.no_attorney) === 1;
      document.getElementById('surgicalOnly').checked = Number(f.surgical_only) === 1;
      document.getElementById('noMri').checked = Number(f.no_mri) === 1;
      document.getElementById('zeroPayments').checked = Number(f.zero_payments) === 1;
      document.getElementById('nonCompliantTherapy').checked = Number(f.non_compliant_therapy) === 1;
    }

    async function loadActivePatients() {
      var tbody = document.getElementById('activeRows');
      tbody.innerHTML = '<div class="empty-state">Loading active patients...</div>';

      try {
        var res = await fetch('/new-ui/content/api/active-patients.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify(getFilters())
        });

        var json = await res.json();

        if (!res.ok || !json.ok) {
         // throw new Error(json.message || 'Unable to load active patients.');
		 throw new Error((json.message || 'Unable to load active patients.') + (json.error ? ' - ' + json.error : ''));
        }

        activeRows = json.rows || [];
        renderKpis(activeRows);
        renderActiveRows(activeRows);
      } catch (err) {
        tbody.innerHTML = '<div class="empty-state">' + escapeHtml(err.message) + '</div>';
      }
    }

    function renderKpis(rows) {
      document.getElementById('kpiPatients').textContent = rows.length;
      document.getElementById('kpiSurgical').textContent = rows.filter(function (r) { return Number(r.surgical_flag) === 1; }).length;
      document.getElementById('kpiNoMri').textContent = rows.filter(function (r) { return Number(r.mri_count || 0) === 0; }).length;
      document.getElementById('kpiTherapyRisk').textContent = rows.filter(function (r) { return r.therapy_status === 'non_compliant'; }).length;
      document.getElementById('resultSub').textContent = rows.length + ' patient(s) found.';
    }

function renderActiveRows(rows) {
  var target = document.getElementById('activeRows');
  rows = Array.isArray(rows) ? rows.slice() : [];

rows.sort(function (a, b) {
  var av = a[activeSort.key];
  var bv = b[activeSort.key];

  var numericKeys = ['patient_id', 'mri_count', 'total_charges', 'total_collected'];

  if (numericKeys.indexOf(activeSort.key) !== -1) {
    av = Number(String(av || '0').replace(/,/g, '').replace(/\$/g, ''));
    bv = Number(String(bv || '0').replace(/,/g, '').replace(/\$/g, ''));

    return activeSort.dir === 'asc' ? av - bv : bv - av;
  }

  av = String(av || '').toLowerCase();
  bv = String(bv || '').toLowerCase();

  if (av < bv) return activeSort.dir === 'asc' ? -1 : 1;
  if (av > bv) return activeSort.dir === 'asc' ? 1 : -1;
  return 0;
});
  if (!rows.length) {
    target.innerHTML = '<div class="empty-state">No active patients found.</div>';
    return;
  }

target.innerHTML = rows.map(function (r) {
  return '<article class="active-patient-card" data-patient-id="' + escapeHtml(r.patient_id) + '">' +

    '<div class="active-card-col patient-col">' +
      '<div class="active-card-label">Patient</div>' +
      '<div class="active-patient-name">' + escapeHtml(r.patient_full_name || '') + '</div>' +
      '<div class="active-patient-id">Patient #' + escapeHtml(r.patient_id || '') + '</div>' +
    '</div>' +

    '<div class="active-card-col office-col">' +
      '<div class="active-card-label">Office / Case</div>' +
      '<div class="active-card-value">' + escapeHtml(r.facility_desc || '-') + '</div>' +
      '<div class="active-patient-id">' + escapeHtml(r.case_type_desc || '-') + '</div>' +
    '</div>' +

    '<div class="active-card-col appt-col">' +
      '<div class="active-card-label">Next Appointment</div>' +
      '<div class="active-card-value">' + escapeHtml(r.next_appointment_date || '-') + '</div>' +
      '<div class="active-patient-id">' + escapeHtml(r.next_service_desc || '') + '</div>' +
    '</div>' +

    '<div class="active-card-col attorney-col">' +
      '<div class="active-card-label">Attorney</div>' +
      '<div class="active-card-value">' + escapeHtml(r.attorney_full_name || 'No Attorney') + '</div>' +
    '</div>' +

    '<div class="active-card-col money-col">' +
      '<div class="active-card-label">Charges / Payments</div>' +
      '<div class="active-card-value">$' + escapeHtml(r.total_charges || '0.00') + '</div>' +
      '<div class="active-patient-id">Paid: $' + escapeHtml(r.total_collected || '0.00') + '</div>' +
    '</div>' +

    '<div class="active-card-col badge-col">' +
      '<div class="active-card-badges">' +
        (r.attorney_full_name ? '' : '<span class="badge badge-danger">No Attorney</span>') +
        (Number(r.surgical_flag) === 1 ? '<span class="badge badge-warning">Surgical</span>' : '') +
        (Number(r.mri_count || 0) === 0 ? '<span class="badge badge-danger">No MRI</span>' : '<span class="badge badge-ok">MRI ' + escapeHtml(r.mri_count) + '</span>') +
        (r.therapy_status === 'non_compliant' ? '<span class="badge badge-danger">Therapy Risk</span>' : '') +
        (Number(String(r.total_collected || '0').replace(/,/g, '')) === 0 ? '<span class="badge badge-warning">$0 Paid</span>' : '') +
      '</div>' +
    '</div>' +

  '</article>';
}).join('');

  target.querySelectorAll('.active-patient-card').forEach(function (card) {
    card.onclick = function () {
      var id = card.getAttribute('data-patient-id');
      if (id) location.href = 'patient.html?patient_id=' + encodeURIComponent(id);
    };
  });
}

    function therapyBadge(status) {
      if (status === 'non_compliant') return '<span class="badge badge-danger">Therapy risk</span>';
      if (status === 'compliant') return '<span class="badge badge-ok">Compliant</span>';
      if (status === 'approaching_end') return '<span class="badge badge-warning">End of treatment</span>';
      return '<span class="badge">Unknown</span>';
    }

    function escapeHtml(str) {
      return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }
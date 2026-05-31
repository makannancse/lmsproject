var attorneyId = new URLSearchParams(window.location.search).get('attorney_id') || '';
var attorneyRows = [];
var attorneySort = {
  key: 'patient_full_name',
  dir: 'asc'
};

document.addEventListener('DOMContentLoaded', function () {
  bindAttorneySort();
  loadAttorneyCases();
  bindSidebarToggle();
		if (typeof loadMenuLeft === 'function') {
		loadMenuLeft();
		}; 
  loadAttorneyCases();
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
	
var attorneyFilteredRows = [];

function bindAttorneyFilters() {
  var applyBtn = document.getElementById('applyAttorneyFiltersBtn');
  var resetBtn = document.getElementById('resetAttorneyFiltersBtn');

  if (applyBtn) {
    applyBtn.onclick = applyAttorneyFilters;
  }

  if (resetBtn) {
    resetBtn.onclick = function () {
      document.getElementById('caseSearchText').value = '';
      document.getElementById('caseTypeFilter').value = '';
      document.getElementById('caseStatusFilter').value = '';
      document.getElementById('dolFromFilter').value = '';
      document.getElementById('dolToFilter').value = '';

      attorneyFilteredRows = attorneyRows.slice();
      renderAttorneyDashboard(attorneyFilteredRows);
    };
  }
}

function applyAttorneyFilters() {
  var search = document.getElementById('caseSearchText').value.trim().toLowerCase();
  var caseType = document.getElementById('caseTypeFilter').value;
  var status = document.getElementById('caseStatusFilter').value;
  var dolFrom = document.getElementById('dolFromFilter').value;
  var dolTo = document.getElementById('dolToFilter').value;

  attorneyFilteredRows = attorneyRows.filter(function (r) {
    var patientText = String((r.patient_id || '') + ' ' + (r.patient_full_name || '')).toLowerCase();

    if (search && patientText.indexOf(search) === -1) return false;
    if (caseType && String(r.case_type || '') !== caseType) return false;

    if (status === 'active' && Number(r.active_patient_flag) !== 1) return false;
    if (status === 'inactive' && Number(r.active_patient_flag) === 1) return false;
    if (status === 'ptonly' && Number(r.ptonly_flag) !== 1) return false;

    if (dolFrom && String(r.dol || '') < dolFrom) return false;
    if (dolTo && String(r.dol || '') > dolTo) return false;

    return true;
  });

  renderAttorneyDashboard(attorneyFilteredRows);
}

function fillAttorneyCaseTypes(rows) {
  var select = document.getElementById('caseTypeFilter');
  if (!select) return;

  var current = select.value;
  var types = [];

  rows.forEach(function (r) {
    if (r.case_type && types.indexOf(r.case_type) === -1) {
      types.push(r.case_type);
    }
  });

  types.sort();

  select.innerHTML = '<option value="">All case types</option>' +
    types.map(function (type) {
      return '<option value="' + escapeHtml(type) + '">' + escapeHtml(type) + '</option>';
    }).join('');

  select.value = current;
}
	

async function loadAttorneyCases() {
  var target = document.getElementById('attorneyCaseRows');

  if (!attorneyId) {
    target.innerHTML = '<div class="empty-state">Missing attorney_id.</div>';
    return;
  }

  target.innerHTML = '<div class="empty-state">Loading attorney cases...</div>';

  try {
    var res = await fetch('/new-ui/content/api/attorney-dashboard.php?attorney_id=' + encodeURIComponent(attorneyId), {
      credentials: 'include'
    });

    var json = await res.json();

    if (!res.ok || !json.ok) {
      throw new Error(json.message || 'Unable to load attorney cases.');
    }

    attorneyRows = json.rows || [];
	
	fillAttorneyCaseTypes(attorneyRows);
	attorneyFilteredRows = attorneyRows.slice();
	
	renderAttorneyDashboard(attorneyFilteredRows);
	
    //renderAttorneyDashboard(attorneyRows);
  } catch (err) {
    target.innerHTML = '<div class="empty-state">' + escapeHtml(err.message) + '</div>';
  }
}

function renderAttorneyDashboard(rows) {
  rows = Array.isArray(rows) ? rows : [];

  var attorneyName = rows[0] ? rows[0].attorney_full_name : 'Attorney Dashboard';

  document.getElementById('attorneyName').textContent = attorneyName;
  document.getElementById('attorneySub').textContent = rows.length + ' case(s) found.';

  document.getElementById('kpiTotalCases').textContent = rows.length;
  document.getElementById('kpiActiveCases').textContent = rows.filter(function (r) {
    return Number(r.active_patient_flag) === 1;
  }).length;
  document.getElementById('kpiInactiveCases').textContent = rows.filter(function (r) {
    return Number(r.active_patient_flag) !== 1;
  }).length;

  renderAttorneyCases(rows);
}

function renderAttorneyCases(rows) {
  var target = document.getElementById('attorneyCaseRows');
  rows = rows.slice();

  rows.sort(function (a, b) {
    var av = a[attorneySort.key];
    var bv = b[attorneySort.key];

    if (attorneySort.key === 'patient_id' || attorneySort.key === 'active_patient_flag') {
      av = Number(av || 0);
      bv = Number(bv || 0);
      return attorneySort.dir === 'asc' ? av - bv : bv - av;
    }

    av = String(av || '').toLowerCase();
    bv = String(bv || '').toLowerCase();

    if (av < bv) return attorneySort.dir === 'asc' ? -1 : 1;
    if (av > bv) return attorneySort.dir === 'asc' ? 1 : -1;
    return 0;
  });

  if (!rows.length) {
    target.innerHTML = '<div class="empty-state">No cases found for this attorney.</div>';
    return;
  }

  target.innerHTML = rows.map(function (r) {
    var isActive = Number(r.active_patient_flag) === 1;

    return '<article class="attorney-case-card" data-patient-id="' + escapeHtml(r.patient_id) + '">' +
      '<div>' +
        '<div class="active-card-label">Patient</div>' +
        '<div class="active-patient-name">' + escapeHtml(r.patient_full_name || '-') + '</div>' +
        '<div class="active-patient-id">Patient #' + escapeHtml(r.patient_id || '-') + '</div>' +
      '</div>' +

      '<div>' +
        '<div class="active-card-label">Case Type</div>' +
        '<div class="active-card-value">' + escapeHtml(r.case_type || '-') + '</div>' +
      '</div>' +

      '<div>' +
        '<div class="active-card-label">Date of Loss</div>' +
        '<div class="active-card-value">' + escapeHtml(r.dol || '-') + '</div>' +
      '</div>' +

      '<div class="active-card-badges">' +
        (isActive ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-danger">Inactive</span>') +
        (Number(r.ptonly_flag) === 1 ? '<span class="badge badge-warning">PT Only</span>' : '') +
      '</div>' +
    '</article>';
  }).join('');

  target.querySelectorAll('.attorney-case-card').forEach(function (card) {
    card.onclick = function () {
      var patientId = card.getAttribute('data-patient-id');
      if (patientId) {
        location.href = 'client.html?patient_id=' + encodeURIComponent(patientId);
      }
    };
  });
}

function bindAttorneySort() {
  document.querySelectorAll('.attorney-results-header button').forEach(function (btn) {
    btn.onclick = function () {
      var key = btn.getAttribute('data-sort-key');

      if (attorneySort.key === key) {
        attorneySort.dir = attorneySort.dir === 'asc' ? 'desc' : 'asc';
      } else {
        attorneySort.key = key;
        attorneySort.dir = 'asc';
      }

      renderAttorneyCases(attorneyRows);
    };
  });
}

function escapeHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
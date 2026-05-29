/* ==============================
   State
============================== */

var patientId = new URLSearchParams(window.location.search).get('patient_id') || '';
var ehrRows = [];
var ehrFilteredRows = [];
var ehrSort = {
  key: 'appointment_date',
  dir: 'desc'
};


/* ==============================
   Init
============================== */

document.addEventListener('DOMContentLoaded', function () {
  bindSidebarToggle();
  bindEhrActions();
  bindEhrSort();
  bindEhrSearch();

  if (typeof loadMenuLeft === 'function') {
    loadMenuLeft();
  }

  loadClientEhr();
});


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

function setText(id, value) {
  var el = document.getElementById(id);
  if (el) el.textContent = value || '';
}

function normalize(str) {
  return String(str || '').toLowerCase().trim();
}


/* ==============================
   Sidebar / Actions
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

function bindEhrActions() {
  var backBtn = document.getElementById('backToClientBtn');
  var refreshBtn = document.getElementById('refreshEhrBtn');

  if (backBtn) {
    backBtn.onclick = function () {
      if (patientId) {
        location.href = 'client.html?patient_id=' + encodeURIComponent(patientId);
      } else {
        location.href = 'client.html';
      }
    };
  }

  if (refreshBtn) {
    refreshBtn.onclick = loadClientEhr;
  }
}

function bindEhrSearch() {
  var input = document.getElementById('ehrSearchInput');
  if (!input) return;

  input.addEventListener('input', function () {
    applyEhrSearch();
  });
}

function bindEhrSort() {
  document.querySelectorAll('.ehr-results-header button').forEach(function (btn) {
    btn.onclick = function () {
      var key = btn.getAttribute('data-sort-key');

      if (ehrSort.key === key) {
        ehrSort.dir = ehrSort.dir === 'asc' ? 'desc' : 'asc';
      } else {
        ehrSort.key = key;
        ehrSort.dir = 'asc';
      }

      renderEhrRows(ehrFilteredRows);
      updateSortButtons();
    };
  });
}

function updateSortButtons() {
  document.querySelectorAll('.ehr-results-header button').forEach(function (btn) {
    var key = btn.getAttribute('data-sort-key');
    var label = btn.getAttribute('data-label');

    if (!label) {
      label = btn.textContent.replace(/[▲▼]/g, '').trim();
      btn.setAttribute('data-label', label);
    }

    if (key === ehrSort.key) {
      btn.textContent = label + (ehrSort.dir === 'asc' ? ' ▲' : ' ▼');
    } else {
      btn.textContent = label;
    }
  });
}


/* ==============================
   Load API
============================== */

async function loadClientEhr() {
  var target = document.getElementById('ehrDocRows');

  if (!patientId) {
    if (target) target.innerHTML = '<div class="empty-state">Missing patient_id.</div>';
    setText('ehrSub', 'Missing patient_id.');
    return;
  }

  if (target) {
    target.innerHTML = '<div class="empty-state">Loading documents...</div>';
  }

  try {
    var res = await fetch('/new-ui/content/api/client-ehr.php?patient_id=' + encodeURIComponent(patientId), {
      credentials: 'include'
    });

    var json = await res.json();

    if (!res.ok || !json.ok) {
      throw new Error(json.message || 'Unable to load EHR documents.');
    }

    ehrRows = json.rows || [];
    ehrFilteredRows = ehrRows.slice();

    renderEhrSummary(ehrRows);
    applyEhrSearch();
  } catch (err) {
    if (target) {
      target.innerHTML = '<div class="empty-state">' + escapeHtml(err.message) + '</div>';
    }

    setText('ehrSub', err.message);
  }
}


/* ==============================
   Render
============================== */

function renderEhrSummary(rows) {
  rows = Array.isArray(rows) ? rows : [];

  var doctors = {};
  var services = {};

  rows.forEach(function (r) {
    if (r.doctor_full_name) doctors[r.doctor_full_name] = true;
    if (r.service_desc) services[r.service_desc] = true;
  });

  setText('kpiDocuments', rows.length);
  setText('kpiDoctors', Object.keys(doctors).length);
  setText('kpiServices', Object.keys(services).length);

  setText('ehrSub', rows.length + ' medical report(s) found.');
}

function applyEhrSearch() {
  var search = normalize(document.getElementById('ehrSearchInput')?.value || '');

  ehrFilteredRows = ehrRows.filter(function (r) {
    if (!search) return true;

    var text = [
      r.doctor_full_name,
      r.service_desc,
      r.document_type_desc,
      r.document_group_desc,
      r.appointment_status_desc,
      r.appointment_date,
      r.generated_date
    ].join(' ').toLowerCase();

    return text.indexOf(search) !== -1;
  });

  renderEhrRows(ehrFilteredRows);
  updateSortButtons();
}

function renderEhrRows(rows) {
  var target = document.getElementById('ehrDocRows');
  if (!target) return;

  rows = Array.isArray(rows) ? rows.slice() : [];

  rows.sort(function (a, b) {
    var av = a[ehrSort.key] || '';
    var bv = b[ehrSort.key] || '';

    av = String(av).toLowerCase();
    bv = String(bv).toLowerCase();

    if (av < bv) return ehrSort.dir === 'asc' ? -1 : 1;
    if (av > bv) return ehrSort.dir === 'asc' ? 1 : -1;
    return 0;
  });

  if (!rows.length) {
    target.innerHTML = '<div class="empty-state">No medical reports found.</div>';
    return;
  }

  target.innerHTML = rows.map(function (r) {
    var url = r.document_url_path || '';

    return '<article class="ehr-doc-card" data-url="' + escapeHtml(url) + '">' +
      '<div class="ehr-doc-main">' +
        '<div class="ehr-doc-title">' + escapeHtml(r.document_type_desc || 'Medical Report') + '</div>' +
        '<div class="ehr-doc-sub">' +
          escapeHtml(r.service_desc || '-') +
          ' &bull; ' +
          escapeHtml(r.appointment_date || '-') +
        '</div>' +
      '</div>' +

      '<div>' +
        '<div class="ehr-label">Doctor</div>' +
        '<div class="ehr-value">' + escapeHtml(r.doctor_full_name || '-') + '</div>' +
      '</div>' +

      '<div>' +
        '<div class="ehr-label">Generated</div>' +
        '<div class="ehr-value">' + escapeHtml(r.generated_date || '-') + '</div>' +
      '</div>' +

      '<div>' +
        '<div class="ehr-label">Status</div>' +
        '<div class="ehr-value">' + escapeHtml(r.appointment_status_desc || '-') + '</div>' +
      '</div>' +

      '<div class="ehr-actions">' +
        '<button class="ghost-btn open-doc-btn" type="button">Open</button>' +
      '</div>' +
    '</article>';
  }).join('');

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
      var url = card ? card.getAttribute('data-url') : '';
      if (url) window.open(url, '_blank');
    };
  });
}

    function escapeHtml(str) {
      return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    var patientId = new URLSearchParams(window.location.search).get('patient_id') || '';

    document.addEventListener('DOMContentLoaded', function () {
      var back = document.getElementById('backToPatientBtn');
      var refresh = document.getElementById('refreshBtn');

      if (back) {
        back.onclick = function () {
          location.href = 'patient.html?patient_id=' + encodeURIComponent(patientId);
        };
      }

      if (refresh) refresh.onclick = loadDocumentSummary;

      if (typeof loadMenuLeft === 'function') loadMenuLeft();
      bindShell();
      loadDocumentSummary();
    });

    function bindShell() {
      var app = document.getElementById('appShell');
      var toggle = document.getElementById('menuToggle');
      if (toggle && app) {
        toggle.onclick = function () {
          app.classList.toggle('sidebar-collapsed');
        };
      }
    }

    async function loadDocumentSummary() {
      if (!patientId) {
        showError('Missing patient_id in URL.');
        return;
      }

      document.getElementById('patientLabel').textContent = 'Patient #' + patientId;
      document.getElementById('statusArea').textContent = 'Analyzing documents with AI...';
      document.getElementById('summaryGrid').innerHTML = '';
      document.getElementById('documentList').innerHTML = '';

      try {
        var res = await fetch('/new-ui/content/api/patient-document-summary.php?patient_id=' + encodeURIComponent(patientId), {
          method: 'GET',
          credentials: 'include'
        });
        var json = await res.json();

        if (!res.ok || !json.ok) {
          throw new Error(json.message || 'Unable to summarize documents.');
        }

        renderSummary(json.summary || {}, json);
        renderDocuments(json.documents || []);
      } catch (err) {
        showError(err.message);
      }
    }

    function renderSummary(summary, root) {
      summary = summary || {};
      var status = String(summary.status || 'unknown').toLowerCase();
      var statusClass = status === 'denied' ? 'denied' : (status === 'pending' ? 'pending' : (status === 'paid' ? 'paid' : ''));

      var statusArea = document.getElementById('statusArea');
      statusArea.innerHTML =
        '<span class="status-pill ' + escapeHtml(statusClass) + '">' + escapeHtml(status || 'unknown') + '</span>' +
        '<div class="document-note">' + escapeHtml(summary.plain_english_summary || summary.denial_reason || 'No denial reason found.') + '</div>';

      document.getElementById('summaryGrid').innerHTML =
        tile('eob', root.eob_on_file === true ? 'On file' : (root.eob_on_file === false ? 'Missing' : 'N/A')) +
        tile('Payer', summary.payer || '-') +
        tile('Claim #', summary.claim_number || '-') +
        tile('Denial Code', summary.denial_code || '-') +
        tile('Billed', summary.total_billed || '-') +
        tile('Paid', summary.total_paid || '-') +
        tile('Patient Responsibility', summary.patient_responsibility || '-') +
        tile('Reason', summary.denial_reason || '-') +
        tile('Recommended Action', summary.recommended_action || '-');
    }

    function renderDocuments(docs) {
      var list = document.getElementById('documentList');

      if (!docs.length) {
        list.innerHTML = '<div class="document-note">No matching document URLs were found.</div>';
        return;
      }

      list.innerHTML = docs.map(function (d) {
        return '<div class="document-row">' +
          '<div>' +
            '<strong>' + escapeHtml(d.document_type_desc || ('Document Type ' + (d.document_type_id || ''))) + '</strong>' +
            '<div class="document-label">Document #' + escapeHtml(d.document_case_id || '') + '</div>' +
          '</div>' +
          (d.document_url ? '<a class="ghost-btn" target="_blank" rel="noopener" href="' + escapeHtml(d.document_url) + '">Open PDF</a>' : '') +
        '</div>';
      }).join('');
    }

    function tile(label, value) {
      return '<article class="document-tile">' +
        '<div class="document-label">' + escapeHtml(label || '') + '</div>' +
        '<div class="document-value">' + escapeHtml(value || '-') + '</div>' +
      '</article>';
    }

    function showError(message) {
      document.getElementById('statusArea').innerHTML = '<div class="document-note">' + escapeHtml(message || 'Unknown error') + '</div>';
    }
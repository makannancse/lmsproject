var state = {
  date_from: new Date().toISOString().slice(0, 10),
  date_to: new Date().toISOString().slice(0, 10),
  location: 'All',
  provider: 'All',
  service_group: 'All',
  services: [],
  status_filter_mode: 'custom',
  appt_statuses: []
};

var filters = null;
var data = null;
var currentView = 'day';
var showPastTimeBlocks = false;
var selectingRangeEnd = false;
var calendarMonth = new Date();
var defaultStatuses = ['scheduled', 'confirmed', 'completed', 'is present', 'in service'];
var defaultServiceGroup = ['Therapy'];
var currentSort = { key: null, asc: true };
var patientSort = { key: null, asc: true };

var FILTER_STORAGE_PREFIX = 'ehr_dashboard_filters_v1_';

function getFilterStorageKey() {
  var user = localStorage.getItem('user') || 'anonymous';
  user = String(user).trim().toLowerCase();
  return FILTER_STORAGE_PREFIX + (user || 'anonymous');
}

function isValidDateString(value) {
  return /^\d{4}-\d{2}-\d{2}$/.test(String(value || ''));
}

function idExists(rows, id) {
  if (id === 'All') return true;
  return Array.isArray(rows) && rows.some(function (r) { return String(r.id) === String(id); });
}

function serviceGroupExists(groupName) {
  if (groupName === 'All') return true;
  return filters && Array.isArray(filters.serviceGroups) && filters.serviceGroups.some(function (g) {
    return String(g.group_name) === String(groupName);
  });
}

function getAvailableServiceIds() {
  var ids = [];
  if (!filters || !Array.isArray(filters.serviceGroups)) return ids;
  filters.serviceGroups.forEach(function (g) {
    (g.services || []).forEach(function (s) {
      ids.push(String(s.id));
    });
  });
  return ids;
}

function getAvailableStatusIds() {
  if (!filters || !Array.isArray(filters.appointmentStatuses)) return [];
  return filters.appointmentStatuses.map(function (s) { return String(s.id); });
}

function loadSavedDashboardFilters() {
  var raw = localStorage.getItem(getFilterStorageKey());
  if (!raw) return false;

  var saved;
  try {
    saved = JSON.parse(raw);
  } catch (err) {
    localStorage.removeItem(getFilterStorageKey());
    return false;
  }

  if (!saved || typeof saved !== 'object') return false;

  if (isValidDateString(saved.date_from)) state.date_from = saved.date_from;
  if (isValidDateString(saved.date_to)) state.date_to = saved.date_to;
  if (state.date_to < state.date_from) state.date_to = state.date_from;

  if (idExists(filters.facilities, saved.location)) state.location = String(saved.location);
  if (idExists(filters.providers, saved.provider)) state.provider = String(saved.provider);
  if (serviceGroupExists(saved.service_group)) state.service_group = String(saved.service_group);

  var availableServices = getAvailableServiceIds();
  state.services = Array.isArray(saved.services)
    ? saved.services.map(String).filter(function (id) { return availableServices.indexOf(id) !== -1; })
    : [];

  if (state.service_group !== 'All') state.services = [];

  state.status_filter_mode = saved.status_filter_mode === 'all' ? 'all' : 'custom';

  var availableStatuses = getAvailableStatusIds();
  state.appt_statuses = state.status_filter_mode === 'all'
    ? []
    : (Array.isArray(saved.appt_statuses)
        ? saved.appt_statuses.map(String).filter(function (id) { return availableStatuses.indexOf(id) !== -1; })
        : []);

  calendarMonth = new Date(state.date_from + 'T12:00:00');
  return true;
}

function saveDashboardFilters() {
  localStorage.setItem(getFilterStorageKey(), JSON.stringify({
    date_from: state.date_from,
    date_to: state.date_to,
    location: state.location,
    provider: state.provider,
    service_group: state.service_group,
    services: state.services || [],
    status_filter_mode: state.status_filter_mode,
    appt_statuses: state.appt_statuses || []
  }));
}

function sortArrow(key) {
  if (patientSort.key !== key) return '';
  return patientSort.asc ? ' ▲' : ' ▼';
}


//patient search by Alex
function bindPatientSearch() {
  var input = document.getElementById('patientSearchInput');
  var button = document.getElementById('patientSearchBtn');
  var results = document.getElementById('patientSearchResults');

  if (!input || !button || !results) return;

  async function searchPatients() {
    var q = input.value.trim();

    if (!q) {
      results.innerHTML = '';
      return;
    }

    results.innerHTML = '<div class="alert-card">Searching...</div>';

    try {
      var res = await fetch('/new-ui/content/api/patient-search.php?q=' + encodeURIComponent(q));
      var json = await res.json();

      if (!json.ok) {
        results.innerHTML = '<div class="alert-card">Search failed.</div>';
        return;
      }

      renderPatientSearchResults(json.patients || []);
    } catch (err) {
      results.innerHTML = '<div class="alert-card">Search failed: ' + escapeHtml(err.message) + '</div>';
    }
  }

  button.onclick = searchPatients;

  input.addEventListener('keydown', function (evt) {
    if (evt.key === 'Enter') {
      evt.preventDefault();
      searchPatients();
    }
  });
}

function renderPatientSearchResults(patients) {
  var results = document.getElementById('patientSearchResults');
  if (!results) return;

  if (!patients.length) {
    results.innerHTML = '<div class="alert-card">No patients found.</div>';
    return;
  }

results.innerHTML =
  '<table class="patient-table">' +
		 '<thead>' +
		  '<tr>' +
			'<th data-sort="name">Name' + sortArrow('name') + '</th>' +
			'<th data-sort="patient_id">Patient #' + sortArrow('patient_id') + '</th>' +
			'<th data-sort="dob_dtm">DOB' + sortArrow('dob_dtm') + '</th>' +
			'<th data-sort="cell_phone_nbr">Cell' + sortArrow('cell_phone_nbr') + '</th>' +
			'<th data-sort="facility_desc">Facility' + sortArrow('facility_desc') + '</th>' +
			'<th data-sort="attorney_full_name">Attorney' + sortArrow('attorney_full_name') + '</th>' +
			'<th data-sort="party_claim_nbr">Claim Number' + sortArrow('party_claim_nbr') + '</th>' +
		  '</tr>' +
		'</thead>' +
    '<tbody>' +
      patients.map(function (p) {
        var name = [p.first_name, p.middle_name, p.last_name].filter(Boolean).join(' ');

        return (
          '<tr class="patient-row" data-patient-id="' + escapeHtml(p.patient_id) + '">' +
            '<td>' + escapeHtml(name) + '</td>' +
            '<td>' + escapeHtml(p.patient_id) + '</td>' +
            '<td>' + escapeHtml(p.dob_dtm || '') + '</td>' +
            '<td>' + escapeHtml(p.cell_phone_nbr || '') + '</td>' +
            '<td>' + escapeHtml(p.facility_desc || '') + '</td>' +
            '<td>' + escapeHtml(p.attorney_full_name || '') + '</td>' +
			'<td>' + escapeHtml(p.party_claim_nbr || '') + '</td>' +
          '</tr>'
        );
      }).join('') +
    '</tbody>' +
  '</table>';

	results.querySelectorAll('.patient-row').forEach(function (row) {
	  row.onclick = function () {
		var id = this.getAttribute('data-patient-id');
		location.href = 'patient.html?patient_id=' + encodeURIComponent(id);
	  };
	});
  
  

	results.querySelectorAll('th').forEach(function (th) {
	  th.onclick = function () {
		var key = this.getAttribute('data-sort');

		if (patientSort.key === key) {
		  patientSort.asc = !patientSort.asc;
		} else {
		  patientSort.key = key;
		  patientSort.asc = true;
		}

		patients.sort(function (a, b) {
		  function getValue(p, key) {
			if (key === 'name') {
			  return [p.first_name, p.middle_name, p.last_name].filter(Boolean).join(' ');
			}
			return p[key] || '';
		  }

		  var v1 = String(getValue(a, key)).toLowerCase();
		  var v2 = String(getValue(b, key)).toLowerCase();

		  if (v1 < v2) return patientSort.asc ? -1 : 1;
		  if (v1 > v2) return patientSort.asc ? 1 : -1;
		  return 0;
		});

		renderPatientSearchResults(patients);
	  };
	});
	
	
  
}

// end of patient-search by Alex


window.onerror = function (msg, src, line) {
  showAppError('JavaScript error: ' + escapeHtml(msg) + '<br>Line: ' + escapeHtml(line));
};

document.addEventListener('DOMContentLoaded', function () {
  startDashboard();
  setInterval(loadDashboard, 30000);
});

async function startDashboard() {
  try {
    setLoading('Loading dashboard...');

    await apiGet('validate-page.php');
    await loadMenuLeft();
    bindShell();

    filters = await apiGet('filters.php');
    if (!loadSavedDashboardFilters()) {
      applyDefaultStatuses();
	  
	  if (filters.serviceGroups.some(g => g.group_name === 'Therapy')) {
		  state.service_group = 'THERAPY';
		}
	   
    }
    renderFilters();
    bindFilters();
	bindPatientSearch();
    bindCalendar();
	

	initAiAssistant();
	


    await loadDashboard();
  } catch (err) {
    console.error(err);
    showAppError('Dashboard failed to load: ' + escapeHtml(err.message));
  }
}



function initAiAssistant() {
  var aiBtn = document.getElementById('aiAssistantBtn');
  if (!aiBtn) return;

  var widget = document.getElementById('aiAssistantWidget');
  if (!widget) {
    widget = document.createElement('div');
    widget.id = 'aiAssistantWidget';
    widget.className = 'ai-assistant-widget is-hidden';
    widget.setAttribute('aria-hidden', 'true');
    widget.innerHTML =
      '<div class="ai-assistant-backdrop" data-ai-close="true"></div>' +
      '<section class="ai-assistant-panel" role="dialog" aria-modal="true" aria-labelledby="aiAssistantTitle">' +
        '<div class="ai-assistant-header">' +
          '<div class="ai-assistant-avatar">' + iconSvg('clara') + '</div>' +
          '<div class="ai-assistant-title-block">' +
            '<h2 id="aiAssistantTitle">Ask Clara - Your Clinical Intelligence Assistant<h2>' +
            '<p>Ask across the scheduling database, not just the current filters.</p>' +
          '</div>' +
          '<button class="ai-assistant-close" type="button" aria-label="Close assistant" data-ai-close="true">&times;</button>' +
        '</div>' +
        '<div class="ai-assistant-suggestions" aria-label="Suggested questions">' +
          '<button type="button" data-ai-question="Summarize today\'s scheduling priorities across all offices.">Summarize priorities</button>' +
          '<button type="button" data-ai-question="Which patients are missing attorney information this week?">Missing details</button>' +
          '<button type="button" data-ai-question="Show no-shows by provider for this month.">Status focus</button>' +
        '</div>' +
        '<div class="ai-assistant-messages" id="aiAssistantMessages" aria-live="polite"></div>' +
        '<form class="ai-assistant-form" id="aiAssistantForm">' +
          '<textarea id="aiAssistantInput" rows="2" placeholder="Ask AI about this dashboard..." aria-label="Ask AI about this dashboard"></textarea>' +
          '<button id="aiMicBtn" type="button" class="ai-mic-btn" title="Ask by voice">' + iconSvg('mic') + '</button>' +
		  '<button class="ai-assistant-send" id="aiAssistantSend" type="submit">Send</button>' +	  
        '</form>' +
      '</section>';
    document.body.appendChild(widget);
  }

  var messages = document.getElementById('aiAssistantMessages');
  var form = document.getElementById('aiAssistantForm');
  var input = document.getElementById('aiAssistantInput');
  var sendBtn = document.getElementById('aiAssistantSend');
 


 

var aiRecorder = null;
var aiAudioChunks = [];
var aiRecording = false;
var aiStream = null;

function audioExtensionFromMimeType(mimeType) {
  mimeType = String(mimeType || '').toLowerCase();

  if (mimeType.indexOf('mp4') !== -1) return 'mp4';
  if (mimeType.indexOf('mpeg') !== -1) return 'mp3';
  if (mimeType.indexOf('wav') !== -1) return 'wav';

  return 'webm';
}

var micBtn = document.getElementById('aiMicBtn');

if (micBtn && input && form) {
  micBtn.addEventListener('click', function () {
    var SpeechRecognition =
      window.SpeechRecognition ||
      window.webkitSpeechRecognition;

    if (!SpeechRecognition) {
      appendAiMessage('error', 'Voice input is not supported in this browser. Try Chrome or Edge.');
      return;
    }

    var recognition = new SpeechRecognition();
    recognition.lang = 'en-US';
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;

    micBtn.textContent = 'Listening...';
    micBtn.disabled = true;

    recognition.onresult = function (event) {
      var transcript = event.results[0][0].transcript;

      input.value = transcript;
      form.dispatchEvent(new Event('submit', { cancelable: true }));
    };

    recognition.onerror = function (event) {
      appendAiMessage('error', 'Voice input failed: ' + event.error);
    };

    recognition.onend = function () {
      micBtn.textContent = 'Mic';
      micBtn.disabled = false;
    };

    recognition.start();
  });
}
  

function openAssistant() {
  widget.classList.remove('is-hidden');
  widget.setAttribute('aria-hidden', 'false');
  document.body.classList.add('ai-assistant-open');

  const token = localStorage.getItem('token');

  if (messages && !messages.dataset.welcomed) {
    if (!token) {
      appendAiMessage('assistant', 'Hi there, I can help you.');
      messages.dataset.welcomed = 'true';
      return;
    }

		fetch('/new-ui/content/api/get_user.php', {
		  method: 'GET',
		  headers: {
			Authorization: 'Bearer ' + token
		  }
		})
    
	
	.then(async res => {
      const data = await res.json();

      if (!res.ok || !data.ok) {
        throw new Error(data.message || 'Unable to load user');
      }

      return data;
	  
    })
    .then(data => {
      const firstName = data.user?.first_name || 'there';
      appendAiMessage('assistant', `Hi ${firstName}, I can help you.`);
    })
    .catch(err => {
      console.error('get-user failed:', err);
	  alert(err.message);
      appendAiMessage('assistant', 'Hi there, I can help you.');
    });

    messages.dataset.welcomed = 'true';
  }

  setTimeout(function () {
    if (input) input.focus();
  }, 50);
}
  
  

  function closeAssistant() {
    widget.classList.add('is-hidden');
    widget.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('ai-assistant-open');
  }

  aiBtn.onclick = openAssistant;

  widget.addEventListener('click', function (evt) {
    if (evt.target && evt.target.getAttribute('data-ai-close') === 'true') closeAssistant();
    var q = evt.target && evt.target.getAttribute('data-ai-question');
    if (q && input) {
      input.value = q;
      form.dispatchEvent(new Event('submit', {cancelable: true}));
    }
  });

  document.addEventListener('keydown', function (evt) {
    if (evt.key === 'Escape' && !widget.classList.contains('is-hidden')) closeAssistant();
  });

  input.addEventListener('keydown', function (evt) {
    if (evt.key === 'Enter' && !evt.shiftKey) {
      evt.preventDefault();
      form.dispatchEvent(new Event('submit', {cancelable: true}));
    }
  });

  form.addEventListener('submit', async function (evt) {
    evt.preventDefault();
    var question = input.value.trim();
    if (!question) return;

    appendAiMessage('user', question);
    input.value = '';
    setAiLoading(true);
    var loadingId = appendAiMessage('assistant', 'Building a safe database query', true);

    try {
      var res = await askDashboardAI(question);
      removeAiMessage(loadingId);
      if (!res || res.ok === false) {
        appendAiMessage('error', (res && res.message) ? res.message : 'AI response failed. Please try again.');
      } else {
        appendAiMessage('assistant', buildAiResponseText(res));
      }
    } catch (err) {
      removeAiMessage(loadingId);
      console.error(err);
      appendAiMessage('error', 'AI failed: ' + err.name + ': ' + err.message);
    } finally {
      setAiLoading(false);
      input.focus();
    }
  });

  function setAiLoading(isLoading) {
    if (sendBtn) {
      sendBtn.disabled = isLoading;
      sendBtn.textContent = isLoading ? 'Sending...' : 'Send';
    }
    if (input) input.disabled = isLoading;
  }
}

function appendAiMessage(role, text, isLoading) {
  var messages = document.getElementById('aiAssistantMessages');
  if (!messages) return '';

  var id = 'ai-msg-' + Date.now() + '-' + Math.random().toString(16).slice(2);
  var bubble = document.createElement('div');
  bubble.id = id;
  bubble.className = 'ai-message ai-message-' + role + (isLoading ? ' ai-message-loading' : '');

  var label = role === 'user' ? 'You' : (role === 'error' ? 'Error' : 'AI Assistant');
  bubble.innerHTML =
    '<div class="ai-message-meta">' + escapeHtml(label) + '</div>' +
    '<div class="ai-message-bubble">' + formatAiText(text || '') + '</div>';

  messages.appendChild(bubble);
  messages.scrollTop = messages.scrollHeight;
  return id;
}

function removeAiMessage(id) {
  if (!id) return;
  var el = document.getElementById(id);
  if (el && el.parentNode) el.parentNode.removeChild(el);
}

function formatAiText(text) {
  return escapeHtml(text)
    .replace(/\n{2,}/g, '</p><p>')
    .replace(/\n/g, '<br>')
    .replace(/^/, '<p>')
    .replace(/$/, '</p>');
}

function buildAiResponseText(res) {
  var answer = (res && res.answer) ? res.answer : 'I did not receive an answer from the assistant.';
  if (res && typeof res.row_count !== 'undefined') {
    answer += String.fromCharCode(10, 10) + 'Rows returned: ' + res.row_count;
  }
  if (res && res.assumptions && res.assumptions.length) {
    answer += String.fromCharCode(10, 10) + 'Assumptions: ' + res.assumptions.join('; ');
  }
  if (res && res.intent) {
    answer += String.fromCharCode(10) + 'Intent: ' + res.intent;
  }
  if (res && res.generated_sql) {
    answer += String.fromCharCode(10, 10) + 'Query used:' + String.fromCharCode(10) + res.generated_sql;
  }
  return answer;
}

async function askDashboardAI(question) {
  var res = await fetch('/new-ui/content/api/ai-assistant.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    credentials: 'include',
    body: JSON.stringify({
      question: question,
      filters: state,
      dashboard: {
        newPatients: data && data.newPatients ? data.newPatients : 0,
        stats: data && data.stats ? data.stats : [],
        alertCards: data && data.alertCards ? data.alertCards : [],
        missingEmcCount: data && data.missingEmcPatients ? data.missingEmcPatients.length : 0,
        missingPipCount: data && data.missingPipPatients ? data.missingPipPatients.length : 0,
        missingFollowUpCount: data && data.missingFollowUpPatients ? data.missingFollowUpPatients.length : 0,
        missingAttorneyCount: data && data.missingAttorneyPatients ? data.missingAttorneyPatients.length : 0
      }
    })
  });

  return await res.json();
}

var toggleBtn = document.getElementById('toggleFiltersBtn');
var panel = document.getElementById('filtersPanel');

if (toggleBtn && panel) {
    toggleBtn.onclick = function () {
        var hidden = panel.classList.contains('is-hidden');

        panel.classList.toggle('is-hidden');

        toggleBtn.textContent = hidden ? 'Hide Filters' : 'Show Filters';
    };
}


function setLoading(message) {
  var alerts = document.getElementById('alertsGrid');
  if (alerts) alerts.innerHTML = '<div class="alert-card"><div class="alert-title">' + escapeHtml(message) + '</div></div>';
}

function applyDefaultStatuses() {
  state.status_filter_mode = 'custom';
  state.appt_statuses = [];

  filters.appointmentStatuses.forEach(function (s) {
    if (defaultStatuses.indexOf(String(s.name).toLowerCase().trim()) !== -1) {
      state.appt_statuses.push(String(s.id));
    }
  });
}

function bindShell() {
  var app = document.getElementById('appShell');
  var toggle = document.getElementById('menuToggle');

  if (toggle && app) {
    toggle.onclick = function () {
      app.classList.toggle('sidebar-collapsed');
    };
  }

  document.querySelectorAll('.view-btn').forEach(function (b) {
    b.onclick = function () {
      var v = b.getAttribute('data-view');
      if (v === 'week') submitWeekForDate(getCurrentSelectedDate());
      else setActiveView(v);
    };
  });

  var prev = document.getElementById('prevWeekBtn');
  var next = document.getElementById('nextWeekBtn');

  if (prev) {
    prev.onclick = function () {
      var d = startOfWeek(getCurrentSelectedDate());
      d.setDate(d.getDate() - 7);
      submitWeekForDate(d);
    };
  }

  if (next) {
    next.onclick = function () {
      var d = startOfWeek(getCurrentSelectedDate());
      d.setDate(d.getDate() + 7);
      submitWeekForDate(d);
    };
  }

  var pastBtn = document.getElementById('togglePastBlocks');
  if (pastBtn) {
    pastBtn.onclick = function () {
      showPastTimeBlocks = !showPastTimeBlocks;
      renderDay();
    };
  }
}

function toggleServiceSelector() {
  var sg = document.getElementById('serviceGroupSelect');
  var serviceSelector = document.getElementById('serviceSelector'); // wrapper div

  if (!sg || !serviceSelector) return;

  if (sg.value === 'All') {
    serviceSelector.style.display = 'block';
  } else {
    serviceSelector.style.display = 'none';

    // Optional: clear selected services when hidden
    document.querySelectorAll('#serviceSelectorBody input[name="services[]"]').forEach(function (box) {
      box.checked = false;
    });
  }
}

function renderFilters() {

toggleServiceSelector();
	
  renderSelect('locationFilter', filters.facilities, state.location);
  renderSelect('providerFilter', filters.providers, state.provider);
  
var serviceBox = document.getElementById('serviceSelectorBody');

if (serviceBox) {
  serviceBox.innerHTML = '';

  filters.serviceGroups.forEach(function (g) {
    var groupWrap = document.createElement('div');
    groupWrap.className = 'service-group-block';

    var groupHeader = document.createElement('button');
    groupHeader.type = 'button';
    groupHeader.className = 'service-group-title';
    groupHeader.textContent = g.group_name;
    groupHeader.setAttribute('data-group-name', g.group_name);

    var serviceList = document.createElement('div');
    serviceList.className = 'service-group-options';

    g.services.forEach(function (s) {
      var label = document.createElement('label');
      label.className = 'service-option';

      var checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.name = 'services[]';
      checkbox.value = String(s.id);
      checkbox.setAttribute('data-group-name', g.group_name);

      if (state.services.indexOf(String(s.id)) !== -1) {
        checkbox.checked = true;
      }

      label.appendChild(checkbox);
      label.appendChild(document.createTextNode(' ' + s.name));
      serviceList.appendChild(label);
    });

    groupWrap.appendChild(groupHeader);
    groupWrap.appendChild(serviceList);
    serviceBox.appendChild(groupWrap);
  });
}  

  var sg = document.getElementById('serviceGroupSelect');
  if (sg) {
    sg.innerHTML = '<option value="All">All services group</option>';
    filters.serviceGroups.forEach(function (g) {
      var opt = document.createElement('option');
      opt.value = g.group_name;
      opt.textContent = g.group_name;
      sg.appendChild(opt);
    });
    sg.value = state.service_group;
  }

  var status = document.getElementById('statusMultiSelect');
  if (status) {
    status.innerHTML = '';
    filters.appointmentStatuses.forEach(function (s) {
      var opt = document.createElement('option');
      opt.value = String(s.id);
      opt.textContent = s.name;
      opt.selected = state.appt_statuses.indexOf(String(s.id)) !== -1;
      status.appendChild(opt);
    });
  }

  var allBox = document.getElementById('allStatusCheckbox');
  if (allBox) allBox.checked = state.status_filter_mode === 'all';
}

function renderSelect(id, rows, selected) {
  var s = document.getElementById(id);
  if (!s) return;

  s.innerHTML = '';
  rows.forEach(function (r) {
    var opt = document.createElement('option');
    opt.value = String(r.id);
    opt.textContent = r.name;
    s.appendChild(opt);
  });
  s.value = selected;
}

function bindFilters() {
	
var serviceGroupSelect = document.getElementById('serviceGroupSelect');

if (serviceGroupSelect) {
  serviceGroupSelect.onchange = function () {
    var serviceSelector = document.getElementById('serviceSelector');

    if (this.value === 'All') {
      if (serviceSelector) serviceSelector.style.display = '';
    } else {
      if (serviceSelector) serviceSelector.style.display = 'none';

      document.querySelectorAll('#serviceSelectorBody input[name="services[]"]').forEach(function (box) {
        box.checked = false;
      });
    }
  };

  serviceGroupSelect.onchange();
}	
	
var sg = document.getElementById('serviceGroupSelect');
if (sg) {
  sg.onchange = function () {
    state.service_group = this.value;
    toggleServiceSelector();
  };
}	

  document.querySelectorAll('.service-group-title').forEach(function (btn) {
    btn.onclick = function () {
      var groupName = this.getAttribute('data-group-name');

      var boxes = document.querySelectorAll(
        '#serviceSelectorBody input[data-group-name="' + groupName.replace(/"/g, '\\"') + '"]'
      );

      var shouldCheck = Array.prototype.slice.call(boxes).some(function (box) {
        return !box.checked;
      });

      boxes.forEach(function (box) {
        box.checked = shouldCheck;
      });
    };
  });

  var apply = document.getElementById('applyFilters');
  if (apply) {
    apply.onclick = async function () {
      var loc = document.getElementById('locationFilter');
      var prov = document.getElementById('providerFilter');
      var allStatus = document.getElementById('allStatusCheckbox');
      var statusMulti = document.getElementById('statusMultiSelect');

      state.location = loc ? loc.value : 'All';
      state.provider = prov ? prov.value : 'All';

      state.services = Array.prototype.slice.call(
        document.querySelectorAll('#serviceSelectorBody input[name="services[]"]:checked')
      ).map(function (box) {
        return box.value;
      });

      // Important: when using individual services, do not also filter by service_group
      var sg = document.getElementById('serviceGroupSelect');

		var selectedGroup = sg ? sg.value : 'All';

		state.service_group = selectedGroup;

		if (selectedGroup === 'All') {
		  state.services = Array.prototype.slice.call(
			document.querySelectorAll('#serviceSelectorBody input[name="services[]"]:checked')
		  ).map(function (box) {
			return box.value;
		  });
		} else {
		  state.services = [];
		}

      var all = allStatus ? allStatus.checked : false;
      state.status_filter_mode = all ? 'all' : 'custom';
      state.appt_statuses = all || !statusMulti
        ? []
        : Array.prototype.slice.call(statusMulti.selectedOptions).map(function (o) {
            return o.value;
          });

      console.log('APPLY FILTER STATE:', state);

      saveDashboardFilters();

      var serviceSelector = document.getElementById('serviceSelector');
      if (serviceSelector) serviceSelector.classList.remove('open');

      var statusSelector = document.getElementById('statusSelector');
      if (statusSelector) statusSelector.classList.remove('open');

      await loadDashboard();
    };
  }

  var serviceToggle = document.getElementById('serviceToggleBtn');
  if (serviceToggle) {
    serviceToggle.onclick = function () {
      document.getElementById('serviceSelector').classList.toggle('open');
    };
  }

  var statusToggle = document.getElementById('statusToggleBtn');
  if (statusToggle) {
    statusToggle.onclick = function () {
      document.getElementById('statusSelector').classList.toggle('open');
    };
  }

  var allStatusBox = document.getElementById('allStatusCheckbox');
  if (allStatusBox) {
    allStatusBox.onchange = function () {
      if (this.checked) {
        var statusMulti = document.getElementById('statusMultiSelect');
        if (statusMulti) {
          Array.prototype.slice.call(statusMulti.options).forEach(function (o) {
            o.selected = false;
          });
        }
      }
    };
  }

  var statusMulti2 = document.getElementById('statusMultiSelect');
  if (statusMulti2) {
    statusMulti2.onchange = function () {
      var all = document.getElementById('allStatusCheckbox');
      if (all) all.checked = this.selectedOptions.length === 0;
    };
  }
}

async function loadDashboard() {
  showPastTimeBlocks = false;
  data = await apiGet('dashboard-data.php', state);

  renderStats();
  renderTasks();
  renderDay();
  renderFooter();
  setActiveView(currentView);
  
  var countBox = document.getElementById('facilityCount');
	if (countBox) {
	  countBox.innerHTML = data.newPatients || 0;
	}
  
}

function renderStats() {
  var target = document.getElementById('statsPanel');
  if (!target) return;

  target.innerHTML = '';

  var stats = data && Array.isArray(data.stats) ? data.stats : [];

  stats.forEach(function (s) {
    s = s || {};
    var card = document.createElement('div');
    card.className = 'stat ' + String(s.class || '');
    card.setAttribute('data-status-names', (s.statusNames || []).join('|'));
    card.innerHTML =
      '<div class="pill-icon">' + iconSvg(
											s.class === 'appointments' ? 'calendar' :
											s.class === 'cancel' ? 'x' :
											s.class === 'missed' ? 'warning' :
											s.class === 'scheduled' ? 'calendar' :
											s.class === 'inservice' ? 'clock' :
											s.class === 'completed' ? 'checkCircle' :
											'check'
										  ) + 
	  '</div>' +
      '<div><div class="label">' + escapeHtml(s.label || '') + '</div><div class="sub">' + escapeHtml(s.subLabel || '') + '</div></div>' +
      '<div class="big">' + escapeHtml(s.value == null ? '' : s.value) + '</div>';
    target.appendChild(card);
  });

  target.querySelectorAll('.stat').forEach(function (card) {
    card.onclick = async function () {
      var names = card.getAttribute('data-status-names');
      if (!names) {
        state.status_filter_mode = 'all';
        state.appt_statuses = [];
      } else {
        var list = names.split('|');
        state.status_filter_mode = 'custom';
        state.appt_statuses = filters.appointmentStatuses
          .filter(function (s) { return list.indexOf(String(s.name).toLowerCase().trim()) !== -1; })
          .map(function (s) { return String(s.id); });
      }
      renderFilters();
      saveDashboardFilters();
      await loadDashboard();
    };
  });
}

function renderTasks() {
  var label = document.getElementById('taskDateLabel');
  if (label) {
    label.innerHTML = state.date_from === state.date_to
      ? formatDisplayDate(state.date_from)
      : formatDisplayDate(state.date_from) + ' - ' + formatDisplayDate(state.date_to);
  }

  var grid = document.getElementById('alertsGrid');
  if (!grid) return;

  grid.innerHTML = '';

  var cards = data && Array.isArray(data.alertCards) ? data.alertCards : [];

  cards.forEach(function (c) {
    c = c || {};

    var card = document.createElement('div');
    card.className = 'alert-card task-today';

    var iconName =
      c.key === 'missing_followup' ? 'mri' :
      c.key === 'missing_pip' ? 'missing_insurance' :
      c.key === 'missing_emc' ? 'emc' :
	  c.key === 'missing_attorney' ? 'missing_attorney' :
      c.type === 'danger' ? 'x' :
      c.type === 'info' ? 'calendar' :
      'warning';

    var left = document.createElement('div');
    left.className = 'alert-left';
    left.innerHTML =
      '<div class="alert-badge ' + escapeHtml(c.type || 'warning') + '">' +
        iconSvg(iconName) +
      '</div>' +
      '<div><div class="alert-title">' + escapeHtml(c.title || '') + '</div>' +
      '<div class="alert-sub">' + escapeHtml(c.subtitle || '') + '</div></div>';

    card.appendChild(left);

    if (c.key === 'missing_emc' || c.key === 'missing_pip' || c.key === 'missing_followup' || c.key === 'missing_attorney') {
      var btn = document.createElement('button');
      btn.className = 'ghost-btn';
      btn.type = 'button';
      btn.textContent = 'View';
      btn.setAttribute('aria-expanded', 'false');

      btn.onclick = function () {
        var panelId =
          c.key === 'missing_emc' ? 'missingEmcPanel' :
          c.key === 'missing_pip' ? 'missingPipPanel' :
          c.key === 'missing_followup' ? 'missingFollowUpPanel' :
		  'missingAttorneyPanel' ;

        var panel = document.getElementById(panelId);
        if (!panel) return;

        var isHidden = panel.classList.contains('is-hidden');
        togglePanel(panelId);

        btn.textContent = isHidden ? 'Hide' : 'View';
        btn.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
      };

      card.appendChild(btn);
    }

    grid.appendChild(card);
  });

  renderPatientPanel('missingEmcPanel', 'MVA Missing EMC', (data && data.missingEmcPatients) || [], 'EMC Needed');
  renderPatientPanel('missingPipPanel', 'MVA Missing PIP Information', (data && data.missingPipPatients) || [], 'PIP Needed');
  renderPatientPanel('missingFollowUpPanel', 'Missing Follow up Appointments', (data && data.missingFollowUpPatients) || [], 'Follow Up Needed');
  renderPatientPanel('missingAttorneyPanel', 'Patients with no Legal Representation', (data && data.missingAttorneyPatients) || [], 'Contact Case Manager Immediately');
  
  bindSmsButtons();
}


function getCaseManagerEmail(patient) {
  patient = patient || {};

  if (patient.case_manager_email) {
    return patient.case_manager_email;
  }

  var facility = String(patient.facility_desc || '').toLowerCase();

  if (facility === 'citimed nmb') return 'mtelleria@citimed.com';
  if (facility === 'citimed kendall') return 'rgonzalez@citimed.com';
  if (facility === 'citimed midtown') return 'cqueipo@citimed.com';
  if (facility === 'citimed Hollywood') return 'mtelleria@citimed.com';
  return 'mtelleria@citimed.com';
}

function teamsCallUrl(email) {
  return 'https://teams.microsoft.com/l/call/0/0?users=' + encodeURIComponent(email || '');
}


function teamsMeetingUrl(email, patientName) {
  var subject = 'Missing attorney follow-up';
  if (patientName) {
    subject += ': ' + patientName;
  }

  return 'https://teams.microsoft.com/l/meeting/new?subject=' +
    encodeURIComponent(subject) +
    '&attendees=' + encodeURIComponent(email || '');
}



function ensureSmsModal() {
  if (document.getElementById('smsReminderModal')) return;

  var modal = document.createElement('div');
  modal.id = 'smsReminderModal';
  modal.className = 'sms-modal is-hidden';

  modal.innerHTML =
    '<div class="sms-modal-backdrop" id="smsModalBackdrop"></div>' +
    '<div class="sms-modal-card" role="dialog" aria-modal="true" aria-labelledby="smsModalTitle">' +
      '<div class="sms-modal-head">' +
        '<div><h3 id="smsModalTitle">Send Text to Patient</h3><div class="sms-modal-sub">Appointment follow-up reminder</div></div>' +
        '<button type="button" class="ghost-btn" id="smsModalClose">Close</button>' +
      '</div>' +
      '<div class="sms-modal-body">' +
        '<label>Patient ID<input type="text" id="smsPatientId" readonly></label>' +
        '<label>Patient Name<input type="text" id="smsPatientName" readonly></label>' +
        '<label>Patient Cellphone<input type="text" id="smsPatientPhone"></label>' +
        '<label>Message<textarea id="smsMessage" rows="6"></textarea></label>' +
      '</div>' +
      '<div class="sms-modal-actions">' +
        '<button type="button" class="ghost-btn" id="smsModalCancel">Cancel</button>' +
        '<button type="button" class="ghost-btn sms-btn" id="smsModalSend">Send Text</button>' +
      '</div>' +
    '</div>';

  document.body.appendChild(modal);

  document.getElementById('smsModalClose').onclick = closeSmsModal;
  document.getElementById('smsModalCancel').onclick = closeSmsModal;
  document.getElementById('smsModalBackdrop').onclick = closeSmsModal;
  document.getElementById('smsModalSend').onclick = sendSmsFromModal;
}

function openSmsModal(patient) {
  ensureSmsModal();

  patient = patient || {};

  var patientId = patient.patient_id || '';
  var patientName = patient.patient_full_name || patient.patient || '';
  var phone = patient.cell_phone_nbr || patient.phone || '';
  var facility = patient.facility_desc || patient.facility || '';

  var message = 'Citimed reminder: ' +
    (patientName ? patientName + ', ' : '') +
    'you are missing a follow-up appointment. Please contact our office to schedule your follow-up visit.';

  if (facility) {
    message += ' Location: ' + facility + '.';
  }

  document.getElementById('smsPatientId').value = patientId;
  document.getElementById('smsPatientName').value = patientName;
  document.getElementById('smsPatientPhone').value = phone;
  document.getElementById('smsMessage').value = message;

  var modal = document.getElementById('smsReminderModal');
  modal.classList.remove('is-hidden');
}

function closeSmsModal() {
  var modal = document.getElementById('smsReminderModal');
  if (modal) modal.classList.add('is-hidden');
}

async function sendSmsFromModal() {
  var sendBtn = document.getElementById('smsModalSend');
  var patientId = document.getElementById('smsPatientId').value;
  var phone = document.getElementById('smsPatientPhone').value;
  var message = document.getElementById('smsMessage').value;

  if (!phone) {
    alert('Patient cellphone is missing.');
    return;
  }

  if (!message) {
    alert('Message is missing.');
    return;
  }

  if (sendBtn) {
    sendBtn.disabled = true;
    sendBtn.textContent = 'Sending...';
  }

  try {
    var res = await fetch('/new-ui/content/api/send-appointment-text.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      credentials: 'include',
      body: JSON.stringify({
        patient_id: patientId,
        phone: phone,
        message: message
      })
    });

    var json = await res.json();

    if (!json.ok) {
      alert((json.error && json.error.message) ? json.error.message : 'Text message failed');
      return;
    }

    alert('Text message sent.');
    closeSmsModal();
  } catch (err) {
    alert('Text message failed: ' + err.message);
  } finally {
    if (sendBtn) {
      sendBtn.disabled = false;
      sendBtn.textContent = 'Send Text';
    }
  }
}


function renderPatientPanel(id, title, rows, action) {
  var panel = document.getElementById(id);
  if (!panel) return;

  rows = Array.isArray(rows) ? rows : [];
 
  panel.innerHTML =
    '<div class="section-head"><div><h2 class="section-title">' + escapeHtml(title || '') + '</h2><div class="section-sub">Patients scheduled for selected range.</div></div></div>' +
    '<div class="alerts-grid">' +
    rows.map(function (p, idx) {
      p = p || {};

      var actionHtml = '';

      if (id === 'missingFollowUpPanel') {
        actionHtml =
          '<button class="ghost-btn sms-followup-btn" type="button" data-patient-index="' + idx + '">' +
            'Send Text to Patient' +
          '</button>';
      } else if (id === 'missingAttorneyPanel') {
        var email = typeof getCaseManagerEmail === 'function' ? getCaseManagerEmail(p) : '';

        if (email && typeof teamsCallUrl === 'function') {
          actionHtml =
		  '<a class="ghost-btn teams-btn" target="_blank" rel="noopener" href="' +
		  teamsCallUrl(email) +
		  '">Teams Call</a>' +
		  '<div class="alert-sub teams-email">' + escapeHtml(email) + '</div>';
        } else {
          actionHtml =
            '<button class="ghost-btn" type="button" disabled>No Case Manager</button>';
        }
      } else {
        actionHtml =
          '<button class="ghost-btn" type="button">' + escapeHtml(action || 'View') + '</button>';
      }

      return '<div class="alert-card patient-link" data-patient-id="' + escapeHtml(p.patient_id) + '">' +
			'<div class="alert-left"><div class="alert-badge warning">!</div>' +
        '<div><div class="alert-title">' + escapeHtml(p.patient_full_name || '') + '</div><div class="alert-sub">Patient #' + escapeHtml(p.patient_id || '') + (p.facility_desc ? ' &middot; ' + escapeHtml(p.facility_desc) : '') + '</div></div></div>' +
        '<div class="task-actions">' + actionHtml + '</div></div>';
    }).join('') +
    '</div>';
	
  panel.querySelectorAll('.patient-link').forEach(function (card) {
  card.onclick = function (e) {
    // Prevent clicking buttons inside card from triggering navigation
    if (e.target.closest('button') || e.target.closest('a')) return;

    var id = this.getAttribute('data-patient-id');
    location.href = 'patient.html?patient_id=' + encodeURIComponent(id);
  };
});	
	

  if (id === 'missingFollowUpPanel') {
    panel.querySelectorAll('.sms-followup-btn').forEach(function (btn) {
      btn.onclick = function () {
        var idx = parseInt(this.getAttribute('data-patient-index'), 10);
        openSmsModal(rows[idx] || {});
      };
    });
  }
}

function togglePanel(id) {
  var panel = document.getElementById(id);
  if (panel) panel.classList.toggle('is-hidden');
}



function appointmentReminderMessage(patientName, appointmentDate, appointmentTime, facility) {
  var msg = 'Citimed reminder: ';
  if (patientName) {
    msg += patientName + ', ';
  }
  msg += 'you have an upcoming appointment';
  if (appointmentDate) {
    msg += ' on ' + appointmentDate;
  }
  if (appointmentTime) {
    msg += ' at ' + appointmentTime;
  }
  if (facility) {
    msg += ' at ' + facility;
  }
  msg += '. Please contact our office if you need to reschedule.';
  return msg;
}

async function sendAppointmentText(patient) {
  patient = patient || {};

  var patientName = patient.patient_full_name || patient.patient || '';
  var appointmentDate = patient.appointment_date || patient.date || '';
  var appointmentTime = patient.from_time ? formatTime(patient.from_time) : (patient.time || '');
  var facility = patient.facility_desc || patient.facility || '';

  var message = appointmentReminderMessage(patientName, appointmentDate, appointmentTime, facility);

  if (!confirm('Send appointment reminder text to ' + (patientName || 'this patient') + '?')) {
    return;
  }

  try {
    var res = await fetch('/new-ui/content/api/send-appointment-text.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      credentials: 'include',
      body: JSON.stringify({
        patient_id: patient.patient_id || '',
        phone: patient.phone || patient.cell_phone_nbr || '',
        message: message
      })
    });

    var json = await res.json();

    if (!json.ok) {
      alert((json.error && json.error.message) ? json.error.message : 'Text message failed');
      return;
    }

    alert('Text message sent.');
  } catch (err) {
    alert('Text message failed: ' + err.message);
  }
}

function encodePatientJson(patient) {
  return JSON.stringify(patient || {})
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function smsButtonHtml(patient) {
  return '<button class="ghost-btn sms-btn" type="button" data-patient-json="' +
    encodePatientJson(patient) +
    '">Text Reminder</button>';
}

function bindSmsButtons() {
  document.querySelectorAll('.sms-btn').forEach(function (btn) {
    btn.onclick = function () {
      var raw = this.getAttribute('data-patient-json') || '{}';
      try {
        sendAppointmentText(JSON.parse(raw));
      } catch (err) {
        alert('Unable to read patient data for SMS.');
      }
    };
  });
}


function renderDay() {
  var target = document.getElementById('dayAppointments');
  var pastBtn = document.getElementById('togglePastBlocks');
  if (!target) return;

  var isDayView = currentView === 'day';
  var selectedDate = state.date_from;
  var today = formatDateValue(new Date());
  var shouldAutoHidePast = isDayView && selectedDate === state.date_to && selectedDate === today;
  var nowMinutes = (new Date()).getHours() * 60 + (new Date()).getMinutes();
  var hiddenPastCount = 0;

  var scheduleBlocks = data && Array.isArray(data.scheduleBlocks) ? data.scheduleBlocks : [];

  if (!scheduleBlocks.length) {
    target.innerHTML = '<div class="block-card"><div class="block-header"><div><div class="block-time">No appointments found</div><div class="facility-label">Adjust filters or select another date.</div></div></div></div>';
    updatePastBlocksButton(pastBtn, false, 0);
    return;
  }

  target.innerHTML = scheduleBlocks.map(function (b) {
    b = b || {};
    var isPastBlock = shouldAutoHidePast && isPastScheduleBlock(b, nowMinutes);
    if (isPastBlock) hiddenPastCount++;

    return '<div class="block-card ' + (b.block_type === 'lunch' ? 'lunch-block ' : '') + (isPastBlock && !showPastTimeBlocks ? 'is-hidden-past' : '') + '">' +
      '<div class="block-header"><div><div class="block-time">' + escapeHtml(b.time || '') + (b.block_type === 'lunch' ? ' <span class="lunch-pill">12:00 PM - 2:00 PM</span>' : '') + '</div>' +
      '<div class="facility-label">' + escapeHtml(b.date_label || '') + ' · ' + escapeHtml(b.facility_desc || '') + '</div></div>' +
      '<div class="block-meta"><span class="block-count">' + (b.block_type === 'lunch' ? 'Offices closed for lunch' : (b.patients || []).length + ' appointments') + '</span><button class="block-toggle" type="button">Collapse</button></div></div>' +
      '<div class="block-body"><div class="patients-grid">' +
      (b.patients || []).map(function (p) {
        p = p || {};
        return '<div class="patient-card patient-link" data-patient-id="' + escapeHtml(p.patient_id) + '">' +	
		'<div class="patient"><img src="' + avatarUrl(p.patient_full_name || '') + '">' +
          '<div><div class="name">' + escapeHtml(p.patient_full_name || '') + '<small>#' + escapeHtml(p.patient_id || '') + '</small></div>' +
          '<div class="meta">' + escapeHtml(p.service_desc || '') + '</div><div class="meta">' + escapeHtml(p.service_group || '') + '</div>' +
          '<div class="meta">' + formatTime(p.from_time) + ' - ' + formatTime(p.thru_time) + '</div>' +
          '<div class="meta">' + escapeHtml(p.doctor_full_name || '') + '</div>' +
          '<div class="status-chip ' + escapeHtml(p.status_class || '') + '">' + escapeHtml(p.appointment_status_desc || '') + '</div></div></div></div>';
      }).join('') +
      '</div></div></div>';
  }).join('');

  updatePastBlocksButton(pastBtn, shouldAutoHidePast && hiddenPastCount > 0, hiddenPastCount);
  
  document.querySelectorAll('.patient-link').forEach(function (card) {
  card.onclick = function (e) {
    if (e.target.closest('button') || e.target.closest('a')) return;

    var id = this.getAttribute('data-patient-id');
    if (!id) return;

    location.href = 'patient.html?patient_id=' + encodeURIComponent(id);
  };
});

  document.querySelectorAll('.block-toggle').forEach(function (btn) {
    btn.onclick = function () {
      var block = this.closest('.block-card');
      block.classList.toggle('is-collapsed');
      this.innerHTML = block.classList.contains('is-collapsed') ? 'Expand' : 'Collapse';
    };
  });
}

function updatePastBlocksButton(button, visible, count) {
  if (!button) return;
  button.style.display = visible ? '' : 'none';
  button.innerHTML = showPastTimeBlocks ? 'Hide past time blocks' : 'View past time blocks' + (count ? ' (' + count + ')' : '');
  button.setAttribute('aria-expanded', showPastTimeBlocks ? 'true' : 'false');
}

function isPastScheduleBlock(block, nowMinutes) {
  var startMinutes = timeLabelToMinutes(block && block.time);
  if (startMinutes === null) return false;

  var endMinutes = block && block.block_type === 'lunch' ? 14 * 60 : startMinutes + 15;
  return endMinutes <= nowMinutes;
}

function timeLabelToMinutes(label) {
  if (!label || label === 'Lunch Break') return 12 * 60;

  var match = String(label).trim().match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
  if (!match) return null;

  var hours = parseInt(match[1], 10);
  var minutes = parseInt(match[2], 10);
  var period = match[3].toUpperCase();

  if (period === 'PM' && hours !== 12) hours += 12;
  if (period === 'AM' && hours === 12) hours = 0;

  return hours * 60 + minutes;
}




function renderFooter() {
  var footer = document.getElementById('footerSummary');
  if (!footer) return;
  footer.innerHTML = data.stats.map(function (s) {
    return '<span>' + escapeHtml(s.label) + ': <strong>' + escapeHtml(s.value) + '</strong></span>';
  }).join('');
}

function setActiveView(v) {
  currentView = v;

  document.querySelectorAll('.view-btn').forEach(function (b) {
    b.classList.toggle('active', b.getAttribute('data-view') === v);
  });

  ['day', 'week', 'month'].forEach(function (x) {
    var view = document.getElementById(x + 'View');
    if (view) view.classList.toggle('active', v === x);
  });

  var label = document.getElementById('scheduleRangeLabel');
  if (label) label.innerHTML = rangeLabel(getCurrentSelectedDate(), v);

  var weekNav = document.getElementById('weekNav');
  if (weekNav) weekNav.classList.toggle('active', v === 'week');

  if (v === 'day') {
    showPastTimeBlocks = false;
    renderDay();
  }
  if (v === 'week') {
    updatePastBlocksButton(document.getElementById('togglePastBlocks'), false, 0);
    buildWeekView(getCurrentSelectedDate());
  }
  if (v === 'month') {
    updatePastBlocksButton(document.getElementById('togglePastBlocks'), false, 0);
    buildMonthView(getCurrentSelectedDate());
  }
}

function generateSegments() {
  var out = [];
  for (var m = 480; m < 1080;) {
    if (m === 720) {
      out.push({label: 'Lunch Break', isLunch: true});
      m = 840;
    } else {
      out.push({label: minutesLabel(m), isLunch: false});
      m += 15;
    }
  }
  return out;
}

function apptsFor(dateKey, segment) {
  return data.scheduleAppointments.filter(function (a) {
    if (a.date !== dateKey) return false;
    if (segment.isLunch) return a.time24 >= '12:00' && a.time24 < '14:00';
    return a.time === segment.label;
  });
}

function buildWeekView(d) {
  var grid = document.getElementById('weekGrid');
  if (!grid) return;

  grid.innerHTML = '';
  var start = startOfWeek(d);
  var segments = generateSegments();

  for (var i = 0; i < 5; i++) {
    var day = new Date(start);
    day.setDate(start.getDate() + i);
    var key = formatDateValue(day);
    var total = 0;
    var gaps = 0;
    var html = '';

    segments.forEach(function (s) {
      var rows = apptsFor(key, s);
      total += rows.length;

      if (s.isLunch) {
        html += '<div class="week-slot lunch-break"><div class="week-slot-time">12:00 PM - 2:00 PM</div><div class="week-slot-meta week-slot-lunch">Lunch Break - office closed</div></div>';
      } else if (!rows.length) {
        gaps++;
        html += '<div class="week-slot has-gap"><div class="week-slot-time">' + escapeHtml(s.label || '') + '</div><div class="week-slot-meta">Open segment</div></div>';
      } else {
        html += '<div class="week-slot"><div class="week-slot-time">' + escapeHtml(s.label || '') + '</div><div class="week-slot-meta">' + rows.length + ' scheduled' +
          rows.map(function (a) { a = a || {}; return '<span class="week-slot-patient">' + escapeHtml(a.patient || '') + '</span>'; }).join('') +
          '</div></div>';
      }
    });

    grid.insertAdjacentHTML('beforeend',
      '<div class="week-day-card"><div><b>' + ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][day.getDay()] + '</b> ' +
      day.toLocaleDateString('en-US', {month:'short', day:'numeric'}) + ' <span>' + total + ' pts</span></div>' +
      '<div>' + gaps + ' open segments</div><div class="week-slots">' + html + '</div></div>'
    );
  }
}

function buildMonthView(d) {
  var grid = document.getElementById('monthGrid');
  if (!grid) return;

  grid.innerHTML = '';
  ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(function (x) {
    grid.insertAdjacentHTML('beforeend', '<div class="month-weekday">' + x + '</div>');
  });

  var first = new Date(d.getFullYear(), d.getMonth(), 1);
  var days = new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();

  for (var i = 0; i < first.getDay(); i++) {
    grid.insertAdjacentHTML('beforeend', '<div class="month-day"></div>');
  }

  for (var day = 1; day <= days; day++) {
    var cur = new Date(d.getFullYear(), d.getMonth(), day);
    var key = formatDateValue(cur);
    var count = data.scheduleAppointments.filter(function (a) { return a.date === key; }).length;
    grid.insertAdjacentHTML('beforeend', '<div class="month-day"><b>' + day + '</b><div>' + count + ' appointments</div></div>');
  }
}

function bindCalendar() {
  var button = document.getElementById('dateRangeButton');
  var pop = document.getElementById('calendarPopover');

  if (button) {
    button.onclick = function () {
      if (pop) pop.classList.toggle('is-hidden');
      renderCalendar();
    };
  }

  var prev = document.getElementById('calendarPrev');
  if (prev) {
    prev.onclick = function () {
      calendarMonth.setMonth(calendarMonth.getMonth() - 1);
      renderCalendar();
    };
  }

  var next = document.getElementById('calendarNext');
  if (next) {
    next.onclick = function () {
      calendarMonth.setMonth(calendarMonth.getMonth() + 1);
      renderCalendar();
    };
  }

  var today = document.getElementById('calendarToday');
  if (today) {
    today.onclick = async function () {
      state.date_from = formatDateValue(new Date());
      state.date_to = state.date_from;
      updateDateRangeButton();
      saveDashboardFilters();
      await loadDashboard();
    };
  }

  var range = document.getElementById('calendarRangeMode');
  if (range) {
    range.onclick = function () {
      selectingRangeEnd = !selectingRangeEnd;
      this.innerHTML = selectingRangeEnd ? 'Range Mode On' : 'Select Range';
      this.classList.toggle('active', selectingRangeEnd);
    };
  }

  var apply = document.getElementById('calendarApply');
  if (apply) {
    apply.onclick = async function () {
      if (pop) pop.classList.add('is-hidden');
      saveDashboardFilters();
      await loadDashboard();
    };
  }

  updateDateRangeButton();
}

function renderCalendar() {
  var grid = document.getElementById('calendarGrid');
  var title = document.getElementById('calendarTitle');
  if (!grid || !title) return;

  var y = calendarMonth.getFullYear();
  var m = calendarMonth.getMonth();
  var first = new Date(y, m, 1);
  var fw = first.getDay();
  var days = new Date(y, m + 1, 0).getDate();
  var prevDays = new Date(y, m, 0).getDate();

  title.innerHTML = calendarMonth.toLocaleDateString('en-US', {month: 'long', year: 'numeric'});
  grid.innerHTML = '';

  for (var i = 0; i < 42; i++) {
    var num, dt, muted = false;

    if (i < fw) {
      num = prevDays - fw + i + 1;
      dt = new Date(y, m - 1, num);
      muted = true;
    } else if (i >= fw + days) {
      num = i - fw - days + 1;
      dt = new Date(y, m + 1, num);
      muted = true;
    } else {
      num = i - fw + 1;
      dt = new Date(y, m, num);
    }

    var val = formatDateValue(dt);
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'calendar-day';
    btn.setAttribute('data-date', val);
    btn.innerHTML = num;

    if (muted) btn.classList.add('is-muted');
    if (val === state.date_from || val === state.date_to) btn.classList.add('is-selected');
    if (val > state.date_from && val < state.date_to) btn.classList.add('is-range');

    btn.onclick = async function () {
      var selected = this.getAttribute('data-date');

      if (!selectingRangeEnd) {
        state.date_from = selected;
        state.date_to = selected;
        updateDateRangeButton();
        saveDashboardFilters();
        await loadDashboard();
      } else {
        if (selected < state.date_from) {
          state.date_to = state.date_from;
          state.date_from = selected;
        } else {
          state.date_to = selected;
        }
        updateDateRangeButton();
        renderCalendar();
      }
    };

    grid.appendChild(btn);
  }
}

function updateDateRangeButton() {
  var b = document.getElementById('dateRangeButton');
  if (b) b.innerHTML = state.date_from === state.date_to ? formatDisplayDate(state.date_from) : formatDisplayDate(state.date_from) + ' - ' + formatDisplayDate(state.date_to);
}

function submitWeekForDate(d) {
  var s = startOfWeek(d);
  var e = endOfWeek(d);
  state.date_from = formatDateValue(s);
  state.date_to = formatDateValue(e);
  updateDateRangeButton();
  saveDashboardFilters();
  loadDashboard().then(function () { setActiveView('week'); });
}

function startOfWeek(d) {
  var x = new Date(d);
  var day = x.getDay();
  var diff = day === 0 ? -6 : 1 - day;
  x.setDate(x.getDate() + diff);
  return x;
}

function endOfWeek(d) {
  var x = startOfWeek(d);
  x.setDate(x.getDate() + 4);
  return x;
}

function getCurrentSelectedDate() {
  return new Date(state.date_from + 'T12:00:00');
}

function rangeLabel(d, v) {
  if (v === 'week') {
    var s = startOfWeek(d);
    var e = endOfWeek(d);
    return 'Mon-Fri week of ' + s.toLocaleDateString('en-US', {month:'short', day:'numeric'}) + ' - ' + e.toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'});
  }
  if (v === 'month') return d.toLocaleDateString('en-US', {month:'long', year:'numeric'});
  return state.date_from === state.date_to ? formatDisplayDate(state.date_from) : formatDisplayDate(state.date_from) + ' - ' + formatDisplayDate(state.date_to);
}

function formatDateValue(d) {
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function formatDisplayDate(v) {
  return new Date(v + 'T12:00:00').toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'});
}

function formatTime(v) {
  if (!v) return '';
  var p = String(v).split(':');
  var h = parseInt(p[0], 10);
  var m = p[1] || '00';
  var suffix = h >= 12 ? 'PM' : 'AM';
  var dh = h % 12;
  if (dh === 0) dh = 12;
  return String(dh).padStart(2, '0') + ':' + m + ' ' + suffix;
}

function minutesLabel(total) {
  var h = Math.floor(total / 60);
  var m = total % 60;
  var suffix = h >= 12 ? 'PM' : 'AM';
  var dh = h % 12;
  if (dh === 0) dh = 12;
  return String(dh).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ' ' + suffix;
}
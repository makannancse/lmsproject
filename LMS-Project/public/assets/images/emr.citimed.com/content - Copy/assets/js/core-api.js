const API_BASE = '/new-ui/content/api/';

async function apiGet(endpoint, params = {}) {
  const url = new URL(API_BASE + endpoint, window.location.origin);

  Object.keys(params).forEach(k => {
    const v = params[k];
    if (Array.isArray(v)) {
      v.forEach(x => url.searchParams.append(k + '[]', x));
    } else if (v !== undefined && v !== null && v !== '') {
      url.searchParams.set(k, v);
    }
  });

  let response, text, json;

  try {
    response = await fetch(url.toString(), { credentials: 'include', cache: 'no-store' });
    text = await response.text();
  } catch (err) {
    showAppError('Cannot reach API: ' + endpoint + '<br>' + escapeHtml(err.message));
    throw err;
  }

  try {
    json = JSON.parse(text);
  } catch (err) {
    showAppError('API did not return JSON: ' + endpoint + '<br><strong>URL:</strong> ' + escapeHtml(url.toString()) + '<br><strong>HTTP:</strong> ' + escapeHtml(response.status + ' ' + response.statusText) + '<pre>' + escapeHtml(text.substring(0,3000)) + '</pre>');
    throw err;
  }

  if (!json.ok) {
    showAppError('API failed: ' + endpoint + '<pre>' + escapeHtml(JSON.stringify(json.error || json, null, 2).substring(0,4000)) + '</pre>');
    throw new Error((json.error && json.error.message) || 'API error');
  }

  return json.data;
}

function showAppError(message) {
  let box = document.getElementById('appErrorBox');
  if (!box) {
    box = document.createElement('div');
    box.id = 'appErrorBox';
    box.style.cssText = 'position:fixed;left:20px;right:20px;bottom:20px;z-index:99999;background:#fff3f3;border:1px solid #e96d6d;color:#842222;border-radius:14px;padding:16px;box-shadow:0 10px 30px rgba(0,0,0,.18);font-family:Arial,sans-serif;font-size:14px;max-height:50vh;overflow:auto;';
    document.body.appendChild(box);
  }
  box.innerHTML = message;
}

function escapeHtml(v) {
  return String(v == null ? '' : v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function iconSvg(t) {
  const icons = {
    check: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    calendar: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3" stroke="currentColor" stroke-width="2"/><path d="M8 3v4M16 3v4M3 10h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
    x: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 7l10 10M17 7L7 17" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>',
    warning: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 4l8 14H4l8-14z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 9v4M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
    arrow: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    search: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
    users: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 19v-1a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="10" cy="8" r="4" stroke="currentColor" stroke-width="2"/><path d="M20 19v-1a4 4 0 0 0-3-3.87M15 4.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
    menu: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>',
	clipboard: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="6" y="7" width="12" height="13" rx="2" stroke="currentColor" stroke-width="2"/><path d="M9 7V5a3 3 0 0 1 6 0v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M9 12h6M9 16h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
	waiting: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="3" stroke="currentColor" stroke-width="2"/><path d="M2 20v-1a5 5 0 0 1 10 0v1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="17" cy="17" r="3" stroke="currentColor" stroke-width="2"/><path d="M17 15v2l1 1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
	reports: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 19h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><rect x="6" y="10" width="3" height="6" rx="1" stroke="currentColor" stroke-width="2"/><rect x="11" y="7" width="3" height="9" rx="1" stroke="currentColor" stroke-width="2"/><rect x="16" y="4" width="3" height="12" rx="1" stroke="currentColor" stroke-width="2"/></svg>',
	esign: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 20h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M6 14c2-3 4-3 6 0s4 3 6 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M14 6l4 4-6 6H8v-4l6-6z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>',
	uber: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 13l2-5a2 2 0 0 1 2-1h10a2 2 0 0 1 2 1l2 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><rect x="3" y="13" width="18" height="5" rx="1.5" stroke="currentColor" stroke-width="2"/><circle cx="7" cy="18" r="1.5" fill="currentColor"/><circle cx="17" cy="18" r="1.5" fill="currentColor"/></svg>',
	clock: '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
	checkCircle: '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M8 12l3 3 5-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
	calendarCheck: '<svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="16" rx="3" stroke="currentColor" stroke-width="2"/><path d="M8 3v4M16 3v4M3 10h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M8 14l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
	mri: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="7" width="18" height="10" rx="3" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/><path d="M3 10h2M19 10h2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
	missing_insurance: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2" stroke="currentColor" stroke-width="2"/><path d="M2 10h20" stroke="currentColor" stroke-width="2"/><path d="M10 14l4 4M14 14l-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
	emc: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-2.8 8.5-7 10-4.2-1.5-7-5.5-7-10V6l7-3z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 8v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M9 11h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M17 17l4 4M21 17l-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
	missing_attorney: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 4h9l3 3v13H6z" stroke="currentColor" stroke-width="2"/><path d="M15 4v3h3" stroke="currentColor" stroke-width="2"/><path d="M9 11h6M9 15h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M17 16l4 4M21 16l-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
	mic: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 14a3 3 0 003-3V5a3 3 0 10-6 0v6a3 3 0 003 3z" stroke="currentColor" stroke-width="2"/><path d="M19 11a7 7 0 01-14 0" stroke="currentColor" stroke-width="2"/><line x1="12" y1="18" x2="12" y2="22" stroke="currentColor" stroke-width="2"/><line x1="8" y1="22" x2="16" y2="22" stroke="currentColor" stroke-width="2"/></svg>',
	clara: '<svg width="42" height="42" viewBox="0 0 42 42" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="42" height="42" rx="14" fill="url(#claraGradient)"/><path d="M20 10H22V18H30V20H22V28H20V20H12V18H20V10Z" fill="white" opacity="0.18"/><circle cx="16" cy="23" r="2" fill="white"/><circle cx="26" cy="23" r="2" fill="white"/><path d="M16 30C18.8 32.2 23.2 32.2 26 30" stroke="white" stroke-width="2" stroke-linecap="round"/> <path d="M30.5 9L31.6 11.4L34 12.5L31.6 13.6L30.5 16L29.4 13.6L27 12.5L29.4 11.4L30.5 9Z" fill="white"/> <defs><linearGradient id="claraGradient" x1="6" y1="4" x2="36" y2="38" gradientUnits="userSpaceOnUse"><stop stop-color="#18B9C8"/><stop offset="1" stop-color="#0D728A"/></linearGradient></defs></svg>'
  };
  return icons[t] || icons.warning || icons.check || '';
}

function avatarUrl(seed) {
  return 'https://api.dicebear.com/7.x/initials/svg?seed=' + encodeURIComponent(seed) + '&backgroundType=gradientLinear&fontWeight=700';
}
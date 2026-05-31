<?php

use function htmlspecialchars as h;

$base = defined('BASE_PATH') ? BASE_PATH : '';
$calendarRole = $calendarRole ?? 'teacher';
$canSchedule = !empty($canSchedule);
$teachers = $teachers ?? [];
$students = $students ?? [];
$classTypes = $classTypes ?? [];
$eventsUrl = $base . '/calendar/events';
$timezoneOptions = calendarTimezoneOptions(resolveUserTimezone(Auth::user() ?: null, APP_TIMEZONE));
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css">

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <h1 class="h4 mb-1"><?= h($pageTitle ?? 'Calendar') ?></h1>
                <p class="text-muted small mb-2">
                    Calendar displayed in selected timezone.
                    Switching timezone refreshes events immediately.
                </p>
                <div class="d-flex flex-wrap gap-2 align-items-center small">
                    <span class="badge rounded-pill" style="background:#0d6efd;">Scheduled</span>
                    <span class="badge rounded-pill" style="background:#fd7e14;">Ongoing</span>
                    <span class="badge rounded-pill" style="background:#198754;">Completed</span>
                    <span class="badge rounded-pill bg-secondary">Cancelled / other</span>
                </div>
            </div>
            <div class="ms-lg-auto d-flex flex-column align-items-stretch gap-2" style="min-width:min(100%, 280px);">
                <?php if ($canSchedule): ?>
                    <button type="button" class="btn btn-primary btn-sm" id="calOpenScheduleBtn">
                        <i class="fa-solid fa-plus me-1"></i> Schedule class
                    </button>
                <?php endif; ?>
                <div>
                    <label class="form-label form-label-sm mb-1" for="calendarTimezone">Calendar timezone</label>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <select id="calendarTimezone" class="form-select form-select-sm flex-grow-1">
                            <?php foreach ($timezoneOptions as $option): ?>
                                <option value="<?= h($option['value']) ?>"><?= h($option['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="badge text-bg-dark text-truncate d-none d-sm-inline-flex" style="max-width: 200px;" id="calendarTimezoneBadge" title="">UTC</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($calendarRole === 'admin'): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label form-label-sm mb-0" for="calFilterTeacher">Filter by teacher</label>
                    <select id="calFilterTeacher" class="form-select form-select-sm">
                        <option value="0">All teachers</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= (int) $t['id'] ?>"><?= h($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label form-label-sm mb-0" for="calFilterStudent">Filter by student (enrolled)</label>
                    <select id="calFilterStudent" class="form-select form-select-sm">
                        <option value="0">All students</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= h($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="text-muted small">Click a free slot to schedule a teacher-hosted Google Meet.</span>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div id="calendar" class="calendar-shell bg-white p-2 rounded shadow-sm"></div>

<div class="modal fade schedule-class-modal" id="calDetailModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="calDetailTitle">Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body small" id="calDetailBody"></div>
            <div class="modal-footer flex-wrap gap-1" id="calDetailActions"></div>
        </div>
    </div>
</div>

<?php if ($canSchedule): ?>
<div class="modal fade schedule-class-modal" id="calScheduleModal" tabindex="-1" aria-labelledby="calScheduleModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="calScheduleModalLabel">Schedule class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="calScheduleForm" class="row g-2">
                    <input type="hidden" name="calendar_ajax" value="1">
                    <?php if (!empty($classTypes)): ?>
                        <div class="col-12">
                            <label class="form-label" for="cal_class_master_id">Class type (optional)</label>
                            <select name="class_master_id" id="cal_class_master_id" class="form-select form-select-sm">
                                <option value="">- Custom title -</option>
                                <?php foreach ($classTypes as $ct): ?>
                                    <option value="<?= (int) $ct['id'] ?>"><?= h($ct['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="class_master_id" value="0">
                    <?php endif; ?>
                    <div class="col-12">
                        <label class="form-label" for="cal_title">Title</label>
                        <input type="text" name="title" id="cal_title" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="cal_description">Description</label>
                        <textarea name="description" id="cal_description" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="cal_teacher_id">Teacher</label>
                        <select name="teacher_id" id="cal_teacher_id" class="form-select form-select-sm" required>
                            <option value="">Select teacher</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?= (int) $t['id'] ?>"><?= h($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">The selected teacher’s connected Google account (Workspace or Gmail) becomes the Meet organizer and host.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cal_start">Start (selected timezone)</label>
                        <input type="datetime-local" name="start_datetime" id="cal_start" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cal_end">End (selected timezone)</label>
                        <input type="datetime-local" name="end_datetime" id="cal_end" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cal_payout">Payout (INR)</label>
                        <input type="number" step="1" min="0" inputmode="decimal" name="payout_amount" id="cal_payout" class="form-control form-control-sm" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cal_student_fee">Student Fee (INR)</label>
                        <input type="number" step="1" min="0" inputmode="decimal" name="student_fee" id="cal_student_fee" class="form-control form-control-sm" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cal_timezone">Timezone</label>
                        <select name="timezone" id="cal_timezone" class="form-select form-select-sm">
                            <?php foreach (supportedSchedulingTimezones() as $tz): ?>
                                <option value="<?= h($tz['value']) ?>" <?= APP_TIMEZONE === $tz['value'] ? 'selected' : '' ?>><?= h($tz['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Stored in UTC; the event is created on the teacher’s Google Calendar.</div>
                    </div>
                    <div class="col-12 student-picker-panel">
                        <label class="form-label" for="cal_student_search">Students (mapped to teacher)</label>
                        <input type="search" id="cal_student_search" class="form-control form-control-sm mb-2" placeholder="Search by name or email…" autocomplete="off">
                        <div id="cal_student_map_notice" class="alert alert-warning py-2 small mb-2 <?= empty($students) ? '' : 'd-none' ?>">
                            No students mapped to this teacher. Link them under Admin → Teacher-Students, then select the teacher again.
                        </div>
                        <select name="student_ids[]" id="cal_student_ids" class="form-select form-select-sm" multiple size="5" <?= empty($students) ? 'disabled' : '' ?>>
                            <?php foreach ($students as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"
                                    data-search="<?= h(strtolower((string) ($s['name'] ?? '') . ' ' . (string) ($s['email'] ?? ''))) ?>">
                                    <?= h($s['name'] . ' (' . $s['email'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Only mapped students appear. Ctrl/Cmd + click for multiple.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="calScheduleSubmit">Save and create Google Meet</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js"></script>
<script src="<?= h($base . '/assets/js/schedule-class-form.js') ?>"></script>
<script>
(function () {
    var base = <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>;
    var eventsUrl = <?= json_encode($eventsUrl, JSON_UNESCAPED_SLASHES) ?>;
    var calendarRole = <?= json_encode($calendarRole, JSON_UNESCAPED_SLASHES) ?>;
    var canSchedule = <?= $canSchedule ? 'true' : 'false' ?>;

    function escapeHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function pad(n) { return String(n).padStart(2, '0'); }
    function toDatetimeLocal(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }
    function toTimezoneDatetimeLocal(date, timeZone) {
        try {
            var parts = new Intl.DateTimeFormat('en-CA', {
                timeZone: timeZone,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            }).formatToParts(date);
            var map = {};
            parts.forEach(function (part) {
                if (part.type !== 'literal') map[part.type] = part.value;
            });
            if (map.year && map.month && map.day && map.hour && map.minute) {
                return map.year + '-' + map.month + '-' + map.day + 'T' + map.hour + ':' + map.minute;
            }
        } catch (e) {
        }
        return toDatetimeLocal(date);
    }
    function calendarTimezoneValue() {
        var tzEl = document.getElementById('calendarTimezone');
        return tzEl && tzEl.value ? tzEl.value : 'UTC';
    }
    function syncScheduleTimezone() {
        var tzField = document.getElementById('cal_timezone');
        if (tzField) {
            tzField.value = calendarTimezoneValue();
        }
    }

    function buildEventsUrl(info) {
        var q = '?start=' + encodeURIComponent(info.startStr) + '&end=' + encodeURIComponent(info.endStr);
        q += '&timezone=' + encodeURIComponent(calendarTimezoneValue());
        if (calendarRole === 'admin') {
            var ft = document.getElementById('calFilterTeacher');
            var fs = document.getElementById('calFilterStudent');
            if (ft && ft.value !== '0') q += '&teacher_id=' + encodeURIComponent(ft.value);
            if (fs && fs.value !== '0') q += '&student_id=' + encodeURIComponent(fs.value);
        }
        return eventsUrl + q;
    }

    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        height: 'auto',
        navLinks: true,
        nowIndicator: true,
        selectable: canSchedule,
        selectMirror: true,
        events: function (info, successCallback, failureCallback) {
            fetch(buildEventsUrl(info), { credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.error) {
                        failureCallback(new Error(data.error));
                        return;
                    }
                    successCallback(data);
                })
                .catch(function (err) { failureCallback(err); });
        },
        eventDidMount: function (info) {
            var t = info.event.extendedProps.tooltip || '';
            if (t) info.el.setAttribute('title', t);
        },
        eventClick: function (info) {
            info.jsEvent.preventDefault();
            var p = info.event.extendedProps;
            var status = (p.status || '').toUpperCase();
            var html = '';
            html += '<p class="mb-1"><strong>Status:</strong> ' + status + '</p>';
            html += '<p class="mb-1"><strong>Calendar timezone:</strong> ' + escapeHtml(p.selected_timezone || 'UTC') + '</p>';
            html += '<p class="mb-1"><strong>Scheduled timezone:</strong> ' + escapeHtml(p.scheduled_timezone_label || p.scheduled_timezone || 'UTC') + '</p>';
            html += '<p class="mb-1"><strong>Teacher:</strong> ' + escapeHtml(p.teacher_name || '-') + '</p>';
            if (p.teacher_google_email) html += '<p class="mb-1"><strong>Host account:</strong> ' + escapeHtml(p.teacher_google_email) + '</p>';
            html += '<p class="mb-1"><strong>Teacher joined:</strong> ' + (p.teacher_joined ? 'Yes' : 'Not yet') + '</p>';
            if (p.student_names) html += '<p class="mb-1"><strong>Students:</strong> ' + escapeHtml(p.student_names) + '</p>';
            html += '<p class="mb-1"><strong>Scheduled start:</strong> ' + escapeHtml(p.start_local || '') + '</p>';
            html += '<p class="mb-1"><strong>Scheduled end:</strong> ' + escapeHtml(p.end_local || '') + '</p>';
            if (p.actual_start_local) html += '<p class="mb-1"><strong>Actual start:</strong> ' + escapeHtml(p.actual_start_local) + '</p>';
            if (p.actual_end_local) html += '<p class="mb-1"><strong>Actual end:</strong> ' + escapeHtml(p.actual_end_local) + '</p>';
            if (p.actual_duration) html += '<p class="mb-0"><strong>Actual duration:</strong> ' + escapeHtml(p.actual_duration) + '</p>';
            if (p.description) html += '<p class="mb-0 mt-2"><strong>Description:</strong><br>' + escapeHtml(p.description) + '</p>';

            document.getElementById('calDetailTitle').textContent = info.event.title || 'Class';
            document.getElementById('calDetailBody').innerHTML = html;

            var actions = document.getElementById('calDetailActions');
            actions.innerHTML = '';
            var st = (p.status || '').toLowerCase();
            var joinStudent = p.join_student || '';
            var joinTeacher = p.join_teacher || '';
            var track = p.join_track || ((typeof base === 'string' ? base : '') + '/join-class?class_id=' + (p.class_id || ''));
            var directMeetLink = p.direct_meet_link || '';

            function addBtn(label, href, cls) {
                if (!href) return;
                var a = document.createElement('a');
                a.href = href;
                a.target = '_blank';
                a.rel = 'noopener noreferrer';
                a.className = 'btn btn-sm ' + (cls || 'btn-primary');
                a.textContent = label;
                actions.appendChild(a);
            }

            if (st === 'completed' && p.recording_url && (calendarRole !== 'student' || Number(p.recording_enabled) === 1)) {
                addBtn('View Recording', p.recording_url, 'btn-success');
            } else if (st === 'completed') {
                var sp = document.createElement('span');
                sp.className = 'text-muted small';
                sp.textContent = 'No recording available yet';
                actions.appendChild(sp);
            }

            if (st === 'scheduled' || st === 'rescheduled' || st === 'ongoing') {
                if (calendarRole === 'student' && joinStudent) addBtn('Join Class', joinStudent);
                else if (calendarRole === 'teacher' && joinTeacher) addBtn('Start Class', joinTeacher);
                else if (calendarRole === 'admin') {
                    if (directMeetLink) addBtn('Open Meet Link', directMeetLink, 'btn-outline-secondary');
                    var adminNote = document.createElement('span');
                    adminNote.className = 'text-muted small';
                    adminNote.textContent = 'Teacher host flow is available only to the assigned teacher.';
                    actions.appendChild(adminNote);
                } else {
                    addBtn('Join (LMS)', track, 'btn-primary');
                }
            }

            var modal = new bootstrap.Modal(document.getElementById('calDetailModal'));
            modal.show();
        },
        select: function (info) {
            if (!canSchedule) return;
            var start = info.start;
            var end = info.end;
            if (!end || end <= start) {
                end = new Date(start.getTime() + 60 * 60 * 1000);
            }
            syncScheduleTimezone();
            var tz = calendarTimezoneValue();
            document.getElementById('cal_start').value = toTimezoneDatetimeLocal(start, tz);
            document.getElementById('cal_end').value = toTimezoneDatetimeLocal(end, tz);
            document.getElementById('cal_title').value = '';
            document.getElementById('cal_description').value = '';
            var m = new bootstrap.Modal(document.getElementById('calScheduleModal'));
            m.show();
            calendar.unselect();
        },
        dateClick: function (info) {
            if (!canSchedule) return;
            if (info.view.type !== 'dayGridMonth') return;
            var tz = calendarTimezoneValue();
            var baseDate = new Date(info.date.getTime());
            var startValue = toTimezoneDatetimeLocal(baseDate, tz).slice(0, 10) + 'T09:00';
            var endValue = toTimezoneDatetimeLocal(baseDate, tz).slice(0, 10) + 'T10:00';
            syncScheduleTimezone();
            document.getElementById('cal_start').value = startValue;
            document.getElementById('cal_end').value = endValue;
            document.getElementById('cal_title').value = '';
            document.getElementById('cal_description').value = '';
            new bootstrap.Modal(document.getElementById('calScheduleModal')).show();
        }
    });

    function calendarTimezoneBadgeUpdate() {
        var tzEl = document.getElementById('calendarTimezone');
        var badge = document.getElementById('calendarTimezoneBadge');
        if (!badge || !tzEl) return;
        var lbl = tzEl.options && tzEl.selectedIndex >= 0 ? (tzEl.options[tzEl.selectedIndex].text || tzEl.value) : tzEl.value;
        badge.textContent = lbl;
        badge.title = lbl;
    }

    calendar.render();

    if (canSchedule && typeof window.initScheduleClassForm === 'function') {
        window.initScheduleClassForm({
            base: base,
            teacherSelectId: 'cal_teacher_id',
            studentSelectId: 'cal_student_ids',
            searchInputId: 'cal_student_search',
            emptyNoticeId: 'cal_student_map_notice',
            selectedIds: []
        });
    }

    var openScheduleBtn = document.getElementById('calOpenScheduleBtn');
    if (openScheduleBtn && canSchedule) {
        openScheduleBtn.addEventListener('click', function () {
            syncScheduleTimezone();
            var tz = calendarTimezoneValue();
            var now = new Date();
            var end = new Date(now.getTime() + 60 * 60 * 1000);
            document.getElementById('cal_start').value = toTimezoneDatetimeLocal(now, tz);
            document.getElementById('cal_end').value = toTimezoneDatetimeLocal(end, tz);
            document.getElementById('cal_title').value = '';
            document.getElementById('cal_description').value = '';
            var schedModal = document.getElementById('calScheduleModal');
            if (schedModal) {
                bootstrap.Modal.getOrCreateInstance(schedModal).show();
            }
        });
    }

    var ftEl = document.getElementById('calFilterTeacher');
    var fsEl = document.getElementById('calFilterStudent');
    if (ftEl) {
        ftEl.addEventListener('change', function () {
            calendar.refetchEvents();
            if (calendarRole === 'admin' && fsEl && ftEl.value !== '0') {
                fetch(base + '/api/teacher-students?teacher_id=' + encodeURIComponent(ftEl.value), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        var current = fsEl.value;
                        fsEl.innerHTML = '<option value="0">All mapped students</option>';
                        if (data && data.ok && data.students) {
                            data.students.forEach(function (s) {
                                var opt = document.createElement('option');
                                opt.value = String(s.id);
                                opt.textContent = s.label || s.name;
                                fsEl.appendChild(opt);
                            });
                        }
                        if (current !== '0') {
                            fsEl.value = current;
                        }
                    })
                    .catch(function () {});
            }
        });
    }
    if (fsEl) fsEl.addEventListener('change', function () { calendar.refetchEvents(); });

    var tzEl = document.getElementById('calendarTimezone');
    if (tzEl) {
        try {
            tzEl.value = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
        } catch (e) {
            tzEl.value = 'UTC';
        }
        if (!tzEl.value) {
            tzEl.value = 'UTC';
        }
        calendar.setOption('timeZone', tzEl.value || 'UTC');
        syncScheduleTimezone();
        calendarTimezoneBadgeUpdate();
        tzEl.addEventListener('change', function () {
            calendar.setOption('timeZone', tzEl.value || 'UTC');
            syncScheduleTimezone();
            calendarTimezoneBadgeUpdate();
            calendar.refetchEvents();
            if (window.AppUI) {
                window.AppUI.toast('info', 'Calendar timezone switched to ' + (tzEl.value || 'UTC') + '.', 'Timezone updated');
            }
        });
    }

    var submitBtn = document.getElementById('calScheduleSubmit');
    if (submitBtn) {
        submitBtn.addEventListener('click', function () {
            var form = document.getElementById('calScheduleForm');
            var fd = new FormData(form);
            submitBtn.disabled = true;
            if (window.AppUI) {
                window.AppUI.showLoader('Scheduling class...', 'Creating the class and Google Meet link.');
            }
            fetch(base + '/classes', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json().catch(function () { return null; }).then(function (j) { return { ok: r.ok, json: j, status: r.status }; }); })
                .then(function (res) {
                    submitBtn.disabled = false;
                    if (window.AppUI) {
                        window.AppUI.hideLoader();
                    }
                    if (res.json && res.json.ok === true) {
                        var msg = (res.json.messages && res.json.messages.length) ? res.json.messages.join(' ') : 'Class scheduled.';
                        if (res.json.warnings && res.json.warnings.length) {
                            msg += ' ' + res.json.warnings.join(' ');
                        }
                        bootstrap.Modal.getInstance(document.getElementById('calScheduleModal')).hide();
                        calendar.refetchEvents();
                        if (typeof window.showSuccess === 'function') {
                            window.showSuccess(msg);
                        } else if (window.AppUI) {
                            window.AppUI.success(msg, 'Class scheduled');
                        }
                        return;
                    }
                    if (res.json && res.json.errors) {
                        window.AppUI.error(res.json.errors.join(' '), 'Could not schedule class');
                        return;
                    }
                    window.AppUI.error('Could not schedule (HTTP ' + res.status + '). Use Classes > Schedule Class if this persists.', 'Request failed');
                })
                .catch(function () {
                    submitBtn.disabled = false;
                    window.AppUI.hideLoader();
                    window.AppUI.error('Network error while scheduling the class.', 'Network error');
                });
        });
    }
})();
</script>

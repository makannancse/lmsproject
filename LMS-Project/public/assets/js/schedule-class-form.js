/**
 * Schedule class: mapped-student loading, search filter, Bootstrap modal fixes.
 */
(function () {
    'use strict';

    function initModalsToBody() {
        document.querySelectorAll('.modal.schedule-class-modal').forEach(function (modalEl) {
            if (modalEl.parentElement !== document.body) {
                document.body.appendChild(modalEl);
            }
            modalEl.addEventListener('show.bs.modal', function () {
                document.body.classList.add('schedule-modal-open');
            });
            modalEl.addEventListener('hidden.bs.modal', function () {
                if (!document.querySelector('.modal.show')) {
                    document.body.classList.remove('schedule-modal-open');
                }
            });
        });
    }

    /**
     * @param {object} options
     * @param {string} options.base
     * @param {string} options.teacherSelectId
     * @param {string} options.studentSelectId
     * @param {string} [options.searchInputId]
     * @param {string} [options.emptyNoticeId]
     * @param {string[]} [options.selectedIds]
     */
    function initScheduleClassForm(options) {
        var base = options.base || '';
        var teacherSelect = document.getElementById(options.teacherSelectId);
        var studentSelect = document.getElementById(options.studentSelectId);
        var searchInput = options.searchInputId ? document.getElementById(options.searchInputId) : null;
        var emptyNotice = options.emptyNoticeId ? document.getElementById(options.emptyNoticeId) : null;
        if (!teacherSelect || !studentSelect) {
            return;
        }

        var preservedSelected = (options.selectedIds || []).map(String);

        function setEmpty(message, visible) {
            if (!emptyNotice) {
                studentSelect.disabled = !!visible;
                return;
            }
            emptyNotice.textContent = message || '';
            emptyNotice.classList.toggle('d-none', !visible);
            studentSelect.disabled = !!visible;
        }

        function filterBySearch() {
            if (!searchInput) {
                return;
            }
            var q = searchInput.value.trim().toLowerCase();
            Array.from(studentSelect.options).forEach(function (opt) {
                var hay = (opt.dataset.search || opt.textContent || '').toLowerCase();
                opt.hidden = q !== '' && hay.indexOf(q) === -1;
            });
        }

        function renderStudents(students) {
            var prev = preservedSelected.length
                ? preservedSelected.slice()
                : Array.from(studentSelect.selectedOptions).map(function (o) { return o.value; });

            studentSelect.innerHTML = '';
            if (!students || !students.length) {
                setEmpty('No students mapped to this teacher. Assign them under Admin → Teacher-Students, then pick the teacher again.', true);
                return;
            }

            setEmpty('', false);
            students.forEach(function (s) {
                var opt = document.createElement('option');
                opt.value = String(s.id);
                opt.textContent = s.label || ((s.name || 'Student') + ' (' + (s.email || '') + ')');
                opt.dataset.search = ((s.name || '') + ' ' + (s.email || '')).toLowerCase();
                if (prev.indexOf(String(s.id)) >= 0) {
                    opt.selected = true;
                }
                studentSelect.appendChild(opt);
            });
            filterBySearch();
        }

        function loadStudents(teacherId) {
            if (!teacherId) {
                renderStudents([]);
                return;
            }
            setEmpty('Loading mapped students…', true);
            fetch(base + '/api/teacher-students?teacher_id=' + encodeURIComponent(teacherId), {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.ok) {
                        renderStudents(data.students || []);
                    } else {
                        renderStudents([]);
                    }
                })
                .catch(function () {
                    renderStudents([]);
                });
        }

        teacherSelect.addEventListener('change', function () {
            preservedSelected = [];
            if (searchInput) {
                searchInput.value = '';
            }
            loadStudents(teacherSelect.value);
        });

        if (searchInput) {
            searchInput.addEventListener('input', filterBySearch);
        }

        if (teacherSelect.value) {
            loadStudents(teacherSelect.value);
        } else {
            renderStudents([]);
        }
    }

    window.initScheduleClassForm = initScheduleClassForm;
    window.initLmsScheduleModals = initModalsToBody;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModalsToBody);
    } else {
        initModalsToBody();
    }
})();

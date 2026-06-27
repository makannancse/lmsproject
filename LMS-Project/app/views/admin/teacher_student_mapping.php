<?php

use function htmlspecialchars as h;

$base = appWebPath();
$teachers = $teachers ?? [];
$students = $students ?? [];
$teacherId = (int) ($teacherId ?? 0);
$assignedIds = $assignedIds ?? [];
$errors = $errors ?? [];
?>

<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h1 class="h4 mb-1">Teacher–Student mapping</h1>
                <p class="text-muted small mb-4">
                    Link students to each teacher. Homework, report cards, and other tools only show students listed here for that teacher.
                </p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= h((string) $err) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>

                <form method="get" action="<?= h($base . '/admin/teacher-students') ?>" class="row g-2 align-items-end mb-4 pb-3 border-bottom">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="pick_teacher">Teacher</label>
                        <select class="form-select" id="pick_teacher" name="teacher_id" onchange="this.form.submit()">
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?= (int) ($t['id'] ?? 0) ?>" <?= $teacherId === (int) ($t['id'] ?? 0) ? 'selected' : '' ?>>
                                    <?= h((string) ($t['name'] ?? '')) ?> — <?= h((string) ($t['email'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 text-muted small">
                        Changing teacher reloads this page and shows that teacher’s current links.
                    </div>
                </form>

                <?php if ($teachers === []): ?>
                    <div class="alert alert-warning">No teachers in the system yet. Create teacher accounts first.</div>
                <?php elseif ($students === []): ?>
                    <div class="alert alert-warning">No students in the system yet. Create student accounts first.</div>
                <?php else: ?>
                    <form method="post" action="<?= h($base . '/admin/teacher-students') ?>" id="mappingForm">
                        <input type="hidden" name="teacher_id" value="<?= $teacherId ?>">

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="studentSearch">Search students</label>
                                <input type="search" class="form-control" id="studentSearch" placeholder="Type to filter by name or email" autocomplete="off">
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="selectAllStudents">
                                    <label class="form-check-label" for="selectAllStudents">Select all visible</label>
                                </div>
                            </div>
                        </div>

                        <div class="border rounded p-3 bg-light" style="max-height: 420px; overflow-y: auto;">
                            <div class="row g-2" id="studentCheckboxList">
                                <?php foreach ($students as $s): ?>
                                    <?php
                                    $sid = (int) ($s['id'] ?? 0);
                                    $checked = isset($assignedIds[$sid]);
                                    $label = trim((string) ($s['name'] ?? '') . ' ' . (string) ($s['email'] ?? ''));
                                    ?>
                                    <div class="col-12 col-md-6 col-lg-4 student-row" data-search="<?= h(strtolower($label)) ?>">
                                        <div class="form-check">
                                            <input class="form-check-input student-cb" type="checkbox" name="student_ids[]" value="<?= $sid ?>" id="stu_<?= $sid ?>" <?= $checked ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="stu_<?= $sid ?>">
                                                <span class="fw-medium"><?= h((string) ($s['name'] ?? '')) ?></span>
                                                <span class="text-muted small d-block"><?= h((string) ($s['email'] ?? '')) ?></span>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <p class="small text-muted mt-2 mb-3">Saving replaces all existing links for this teacher with your selection (at least one student required).</p>

                        <button type="submit" class="btn btn-primary">Save mapping</button>
                        <a href="<?= h($base . '/admin/users?role=student') ?>" class="btn btn-link">Manage users</a>
                    </form>

                    <script>
                    (function () {
                        var search = document.getElementById('studentSearch');
                        var rows = document.querySelectorAll('#studentCheckboxList .student-row');
                        var selectAll = document.getElementById('selectAllStudents');
                        if (search) {
                            search.addEventListener('input', function () {
                                var q = (search.value || '').toLowerCase().trim();
                                rows.forEach(function (row) {
                                    var hay = row.getAttribute('data-search') || '';
                                    row.style.display = (!q || hay.indexOf(q) !== -1) ? '' : 'none';
                                });
                            });
                        }
                        if (selectAll) {
                            selectAll.addEventListener('change', function () {
                                var on = selectAll.checked;
                                document.querySelectorAll('#studentCheckboxList .student-row').forEach(function (row) {
                                    if (row.style.display === 'none') return;
                                    var cb = row.querySelector('.student-cb');
                                    if (cb) cb.checked = on;
                                });
                            });
                        }
                        var form = document.getElementById('mappingForm');
                    })();
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

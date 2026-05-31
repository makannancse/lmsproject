<?php

use function htmlspecialchars as h;

$errors = $errors ?? [];
$old = $old ?? [];

?>

<div class="row justify-content-center schedule-class-page">
    <div class="col-12 col-lg-9 col-xl-8">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h1 class="h4 mb-3">Schedule Class</h1>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger small">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= h($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <form method="post"
                      id="scheduleClassForm"
                      action="<?= h((defined('BASE_PATH') ? BASE_PATH : '') . '/classes') ?>"
                      class="no-app-loader"
                      data-schedule-ajax="1"
                      data-loader-title="Scheduling class..."
                      data-loader-text="Creating the Google Meet event, saving class timings, and notifying participants.">
                    <input type="hidden" name="calendar_ajax" value="1">
                    <input type="hidden" name="redirect_to" value="calendar">
                    <?php if (!empty($classTypes)): ?>
                        <div class="mb-3">
                            <label class="form-label" for="class_master_id">Class type (optional)</label>
                            <select id="class_master_id" name="class_master_id" class="form-select">
                                <option value="">- Custom title -</option>
                                <?php foreach ($classTypes as $ct): ?>
                                    <option value="<?= (int) $ct['id'] ?>"
                                        <?= isset($old['class_master_id']) && (int)$old['class_master_id'] === (int)$ct['id'] ? 'selected' : '' ?>>
                                        <?= h($ct['class_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Selecting a type can prefill title/description from catalog.</div>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label" for="title">Title</label>
                        <input type="text" id="title" name="title" class="form-control"
                               value="<?= h($old['title'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3"><?= h($old['description'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="teacher_id">Teacher</label>
                        <select id="teacher_id" name="teacher_id" class="form-select" required>
                            <option value="">Select teacher</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?= (int) $t['id'] ?>"
                                    <?= isset($old['teacher_id']) && (int)$old['teacher_id'] === (int)$t['id'] ? 'selected' : '' ?>>
                                    <?= h($t['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">The selected teacher must have a connected Google account (Workspace or Gmail) so Calendar can create Meet links.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="start_datetime">Start (local)</label>
                            <input type="datetime-local" id="start_datetime" name="start_datetime" class="form-control"
                                   value="<?= h($old['start_datetime'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="end_datetime">End (local)</label>
                            <input type="datetime-local" id="end_datetime" name="end_datetime" class="form-control"
                                   value="<?= h($old['end_datetime'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="payout_amount">Teacher payout for this class (INR)</label>
                            <input type="number" step="1" min="0" inputmode="decimal" id="payout_amount" name="payout_amount" class="form-control"
                                   value="<?= h((string) parseInrAmount($old['payout_amount'] ?? 0)) ?>">
                            <div class="form-text">Exact INR amount — no currency conversion applied.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="student_fee">Student fee for this class (INR)</label>
                            <input type="number" step="1" min="0" inputmode="decimal" id="student_fee" name="student_fee" class="form-control"
                                   value="<?= h((string) parseInrAmount($old['student_fee'] ?? 0)) ?>">
                            <div class="form-text">Per enrolled student; stored as entered.</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="timezone">Timezone</label>
                        <?php $timezoneValue = (string) ($old['timezone'] ?? APP_TIMEZONE); ?>
                        <select id="timezone" name="timezone" class="form-select">
                            <?php foreach (supportedSchedulingTimezones() as $tz): ?>
                                <option value="<?= h($tz['value']) ?>" <?= $timezoneValue === $tz['value'] ? 'selected' : '' ?>><?= h($tz['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Selected local time is converted to UTC before saving, and the Meet is created using the assigned teacher's Workspace calendar.</div>
                    </div>
                    <div class="mb-3 student-picker-panel">
                        <label class="form-label" for="student_search">Students (mapped to teacher)</label>
                        <input type="search" id="student_search" class="form-control form-control-sm mb-2" placeholder="Search by name or email…" autocomplete="off">
                        <div id="student_map_notice" class="alert alert-warning py-2 small mb-2 <?= empty($students) ? '' : 'd-none' ?>">
                            No students mapped to this teacher yet. Use <a href="<?= h((defined('BASE_PATH') ? BASE_PATH : '') . '/admin/teacher-students') ?>">Admin → Teacher-Students</a> to link students, then change the teacher above to refresh.
                        </div>
                        <select id="student_ids" name="student_ids[]" class="form-select" multiple size="6" <?= empty($students) ? 'disabled' : '' ?>>
                            <?php foreach ($students as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"
                                    data-search="<?= h(strtolower((string) ($s['name'] ?? '') . ' ' . (string) ($s['email'] ?? ''))) ?>"
                                    <?= isset($old['student_ids']) && in_array((string)$s['id'], (array)$old['student_ids'], true) ? 'selected' : '' ?>>
                                    <?= h($s['name'] . ' (' . $s['email'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Only students linked to the selected teacher are listed. Ctrl/Cmd + click for multiple.</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Create Class</button>
                        <a href="<?= h((defined('BASE_PATH') ? BASE_PATH : '') . '/admin/calendar') ?>" class="btn btn-outline-secondary">Open calendar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
$selectedStudentIds = [];
if (!empty($old['student_ids']) && is_array($old['student_ids'])) {
    $selectedStudentIds = array_map('strval', $old['student_ids']);
}
$scheduleFormBase = defined('BASE_PATH') ? BASE_PATH : '';
?>
<script src="<?= h($scheduleFormBase . '/assets/js/schedule-class-form.js') ?>"></script>
<script src="<?= h($scheduleFormBase . '/assets/js/class-schedule-submit.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var base = <?= json_encode($scheduleFormBase, JSON_UNESCAPED_SLASHES) ?>;
    if (typeof window.initScheduleClassForm === 'function') {
        window.initScheduleClassForm({
            base: base,
            teacherSelectId: 'teacher_id',
            studentSelectId: 'student_ids',
            searchInputId: 'student_search',
            emptyNoticeId: 'student_map_notice',
            selectedIds: <?= json_encode($selectedStudentIds, JSON_UNESCAPED_UNICODE) ?>
        });
    }
    var scheduleForm = document.getElementById('scheduleClassForm');
    if (scheduleForm && window.LmsScheduleClass && typeof window.LmsScheduleClass.bindScheduleForm === 'function') {
        window.LmsScheduleClass.bindScheduleForm(scheduleForm, { base: base });
    }
});
</script>

<?php

use function htmlspecialchars as h;

$base = appWebPath();
$isAdmin = !empty($isAdmin);
$postUrl = $isAdmin ? '/admin/reschedule/new' : '/teacher/reschedule/new';
$backUrl = $isAdmin ? '/admin/reschedule' : '/teacher/reschedule';
$selectedTimezone = resolveUserTimezone(Auth::user() ?: null, APP_TIMEZONE);
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Reschedule Class</h1>
                <form method="post" action="<?= h($base . $postUrl) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="class_student">Class and student</label>
                        <select class="form-select" name="class_student" id="class_student" required>
                            <option value="">Select</option>
                            <?php foreach ($enrollmentRows ?? [] as $row): ?>
                                <option value="<?= (int) ($row['class_id'] ?? 0) . ':' . (int) ($row['student_id'] ?? 0) . ':' . (int) ($row['teacher_id'] ?? 0) ?>">
                                    <?= h((string) ($row['class_title'] ?? '')) ?> - <?= h((string) ($row['student_name'] ?? '')) ?> - <?= h(formatClassScheduledAt($row, 'd M Y h:i A T')) ?><?= $isAdmin ? (' - Teacher: ' . h((string) ($row['teacher_name'] ?? ''))) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="requested_date">Proposed date</label>
                            <input type="date" class="form-control" name="requested_date" id="requested_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="requested_time">Proposed time</label>
                            <input type="time" class="form-control" name="requested_time" id="requested_time" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="new_timezone">Requested timezone</label>
                        <select class="form-select" name="new_timezone" id="new_timezone">
                            <?php foreach (supportedSchedulingTimezones() as $tz): ?>
                                <option value="<?= h($tz['value']) ?>" <?= $selectedTimezone === $tz['value'] ? 'selected' : '' ?>><?= h($tz['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="reason">Note</label>
                        <textarea class="form-control" name="reason" id="reason" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Reschedule Now</button>
                    <a href="<?= h($base . $backUrl) ?>" class="btn btn-link">Back</a>
                </form>
            </div>
        </div>
    </div>
</div>

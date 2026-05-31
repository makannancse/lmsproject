<?php

use function htmlspecialchars as h;

$base = defined('BASE_PATH') ? BASE_PATH : '';
$selectedTimezone = resolveUserTimezone(Auth::user() ?: null, APP_TIMEZONE);
?>

<div class="row g-3">
    <div class="col-12">
        <h1 class="h4">Reschedule</h1>
        <p class="text-muted small">Request a new date and time for an upcoming class. Your teacher will approve or reject it.</p>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-muted mb-3">New Request</h2>
                <form method="post" action="<?= h($base . '/student/reschedule') ?>">
                    <div class="mb-3">
                        <label class="form-label" for="class_id">Class</label>
                        <select class="form-select" name="class_id" id="class_id" required>
                            <option value="">Select class</option>
                            <?php foreach ($classes ?? [] as $c): ?>
                                <option value="<?= (int) ($c['id'] ?? 0) ?>">
                                    <?= h((string) ($c['title'] ?? '')) ?> - <?= h(formatClassScheduledAt($c, 'd M Y h:i A T')) ?> - <?= h(formatClassScheduledTimezoneLabel($c)) ?>
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
                        <label class="form-label" for="reason">Reason (optional)</label>
                        <textarea class="form-control" name="reason" id="reason" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-muted mb-3">Your Requests</h2>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Class</th>
                            <th>Current Schedule</th>
                            <th>Proposed Schedule</th>
                            <th>Status</th>
                            <th>Comment</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($requests)): ?>
                            <tr><td colspan="5" class="text-muted small">No requests yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($requests as $r): ?>
                                <?php $requestedTimezone = normalizeTimezone((string) ($r['new_timezone'] ?? $r['scheduled_timezone'] ?? APP_TIMEZONE), APP_TIMEZONE); ?>
                                <tr>
                                    <td><?= h((string) ($r['class_title'] ?? '')) ?></td>
                                    <td>
                                        <div><?= h(formatClassScheduledAt($r, 'd M Y h:i A T')) ?></div>
                                        <div class="small text-muted"><?= h(formatClassScheduledTimezoneLabel($r)) ?></div>
                                    </td>
                                    <td>
                                        <div><?= h(formatRescheduleLocalDateTime((string) ($r['requested_date'] ?? ''), (string) ($r['requested_time'] ?? ''), $requestedTimezone, 'd M Y h:i A')) ?></div>
                                        <div class="small text-muted"><?= h($requestedTimezone) ?></div>
                                    </td>
                                    <td><span class="badge text-bg-secondary"><?= h((string) ($r['status'] ?? 'pending')) ?></span></td>
                                    <td class="small"><?= h((string) ($r['teacher_comment'] ?? '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

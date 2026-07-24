<?php

use function htmlspecialchars as h;

$base = appWebPath();
$statusFilter = $statusFilter ?? '';
$statusOpts = ['' => 'All', 'scheduled' => 'Scheduled', 'ongoing' => 'Ongoing', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'rescheduled' => 'Rescheduled'];
$viewerTimezone = resolveUserTimezone(Auth::user() ?: null, APP_TIMEZONE);
?>

<div class="classes-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Classes</h1>
            <p class="text-muted small mb-0">Manage live classes, tracking, and recordings.</p>
        </div>
        <a href="<?= h(path('classes/create')) ?>" class="btn btn-primary btn-sm">Schedule Class</a>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="get" action="<?= h($base . '/classes') ?>" class="row g-2 align-items-center no-app-loader">
                <div class="col-auto">
                    <label class="col-form-label col-form-label-sm" for="statusFilter">Filter by status</label>
                </div>
                <div class="col-auto">
                    <select name="status" id="statusFilter" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php foreach ($statusOpts as $val => $label): ?>
                            <option value="<?= h($val) ?>" <?= $statusFilter === $val ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Teacher</th>
                            <th>Student Fee</th>
                            <th>Scheduled Time</th>
                            <th>Status</th>
                            <th>Actual Duration</th>
                            <th>Recording</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($classes)): ?>
                        <tr><td colspan="8" class="text-muted small">No classes scheduled yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($classes as $cls): ?>
                            <tr>
                                <td><?= h((string) ($cls['title'] ?? '')) ?></td>
                                <td><?= h((string) ($cls['teacher_name'] ?? '')) ?></td>
                                <td><?= h(formatCurrency((float) ($cls['student_fee'] ?? 0))) ?></td>
                                <td>
                                    <div><?= h(formatClassScheduledAt($cls, 'd M Y h:i A T')) ?></div>
                                    <div class="small text-muted"><?= h(formatClassScheduledTimezoneLabel($cls)) ?></div>
                                    <?php if (classActualStartUtcValue($cls) !== null || classActualEndUtcValue($cls) !== null): ?>
                                        <div class="small text-muted mt-1">Actual: <?= h(formatClassActualAt($cls, 'start', $viewerTimezone)) ?><?= classActualEndUtcValue($cls) !== null ? (' to ' . formatClassActualAt($cls, 'end', $viewerTimezone)) : '' ?></div>
                                        <div class="small text-muted"><?= h(formatClassActualTimezoneLabel($cls, $viewerTimezone)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= h(classStatusBadgeClass((string) ($cls['status'] ?? 'scheduled'))) ?> text-uppercase"><?= h((string) ($cls['status'] ?? 'scheduled')) ?></span><?= teacherLateJoinBadgeHtml($cls) ?></td>
                                <td class="small text-muted"><?= h(ClassSession::formatActualDuration($cls)) ?></td>
                                <td>
                                    <?php if (Auth::isAdmin()): ?>
                                        <form method="post"
                                              action="<?= h($base . '/classes/recording-toggle') ?>"
                                              class="mb-2 d-flex gap-2 align-items-center"
                                              data-loader-title="Saving recording settings..."
                                              data-loader-text="Updating reminder and sync preferences for this class.">
                                            <input type="hidden" name="class_id" value="<?= (int) ($cls['id'] ?? 0) ?>">
                                            <input type="hidden" name="recording_enabled" value="0">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" id="rec_<?= (int) ($cls['id'] ?? 0) ?>" name="recording_enabled" value="1" <?= (int) ($cls['recording_enabled'] ?? 0) === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label small" for="rec_<?= (int) ($cls['id'] ?? 0) ?>">Recording reminder</label>
                                            </div>
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Save</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (!empty($cls['recording_url'])): ?>
                                        <a href="<?= h((string) $cls['recording_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">View Recording</a>
                                    <?php else: ?>
                                        <?php $recordingStatus = recordingSyncStatusForRow($cls); ?>
                                        <span class="text-muted small"><?= h($recordingStatus === 'disabled' ? 'Recording disabled' : recordingSyncStatusText($cls)) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <form method="post"
                                              action="<?= h($base . '/classes/status') ?>"
                                              class="d-flex gap-1"
                                              data-confirm-status-value="cancelled"
                                              data-confirm-title="Cancel this class?"
                                              data-confirm-text="This class status will change to cancelled."
                                              data-confirm-button="Cancel class"
                                              data-loader-title="Updating class..."
                                              data-loader-text="Saving the latest class status and timing information.">
                                            <input type="hidden" name="class_id" value="<?= (int) ($cls['id'] ?? 0) ?>">
                                            <select name="status" class="form-select form-select-sm">
                                                <?php foreach (['scheduled', 'ongoing', 'completed', 'cancelled', 'rescheduled'] as $st): ?>
                                                    <option value="<?= h($st) ?>" <?= ($cls['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Update</button>
                                        </form>
                                        <form method="post"
                                              action="<?= h($base . '/meeting/track') ?>"
                                              class="d-inline"
                                              data-loader-title="Syncing recording..."
                                              data-loader-text="Searching Google Drive for the matching recording file.">
                                            <input type="hidden" name="class_id" value="<?= (int) ($cls['id'] ?? 0) ?>">
                                            <input type="hidden" name="event" value="sync-recording">
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Sync Recording</button>
                                        </form>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="<?= h($base . '/classes/edit?id=' . (int) ($cls['id'] ?? 0)) ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="<?= h($base . '/admin/reschedule/new') ?>" class="btn btn-sm btn-primary">Reschedule</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php renderPagination($pagination ?? null, $queryParams ?? []); ?>
</div>

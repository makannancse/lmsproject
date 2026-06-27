<?php

use function htmlspecialchars as h;

$base = appWebPath();
$filters = $filters ?? ['q' => '', 'teacher_id' => 0, 'student_id' => 0];
$recordings = $recordings ?? [];
$teachers = $teachers ?? [];
$students = $students ?? [];
$adminTimezone = resolveUserTimezone(Auth::user() ?: null, APP_TIMEZONE);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Recordings</h1>
        <p class="small text-muted mb-0">Manage class recordings, student visibility, and retry sync.</p>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="get" action="<?= h($base . '/admin/recordings') ?>" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label form-label-sm" for="q">Search</label>
                <input type="text" class="form-control form-control-sm" id="q" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" placeholder="Class, teacher, or student">
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm" for="teacher_id">Teacher</label>
                <select class="form-select form-select-sm" id="teacher_id" name="teacher_id">
                    <option value="0">All teachers</option>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?= (int) ($teacher['id'] ?? 0) ?>" <?= (int) ($filters['teacher_id'] ?? 0) === (int) ($teacher['id'] ?? 0) ? 'selected' : '' ?>><?= h((string) ($teacher['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm" for="student_id">Student</label>
                <select class="form-select form-select-sm" id="student_id" name="student_id">
                    <option value="0">All students</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= (int) ($student['id'] ?? 0) ?>" <?= (int) ($filters['student_id'] ?? 0) === (int) ($student['id'] ?? 0) ? 'selected' : '' ?>><?= h((string) ($student['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <h2 class="h6 text-muted text-uppercase mb-3">Manual Recording Save</h2>
        <form method="post"
              action="<?= h($base . '/recordings/manual-save') ?>"
              class="row g-2 align-items-end"
              data-loader-title="Saving recording..."
              data-loader-text="Updating the class recording details and student access.">
            <div class="col-md-2">
                <label class="form-label form-label-sm" for="class_id">Class ID</label>
                <input type="number" class="form-control form-control-sm" id="class_id" name="class_id" min="1" required>
            </div>
            <div class="col-md-4">
                <label class="form-label form-label-sm" for="recording_url">Recording URL</label>
                <input type="url" class="form-control form-control-sm" id="recording_url" name="recording_url" required>
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm" for="recording_title">Title</label>
                <input type="text" class="form-control form-control-sm" id="recording_title" name="recording_title">
            </div>
            <div class="col-md-1">
                <label class="form-label form-label-sm" for="recording_duration">Min</label>
                <input type="number" class="form-control form-control-sm" id="recording_duration" name="recording_duration" min="0">
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm" for="visible_to_student">Visibility</label>
                <select class="form-select form-select-sm" id="visible_to_student" name="visible_to_student">
                    <option value="no">Admin only</option>
                    <option value="yes">Show to student</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-outline-primary btn-sm">Save Recording</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
                <th>Class</th>
                <th>Teacher</th>
                <th>Students</th>
                <th>Status</th>
                <th>Duration</th>
                <th>Visibility</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($recordings === []): ?>
                <tr><td colspan="7" class="text-muted small">No recordings found.</td></tr>
            <?php else: ?>
                <?php foreach ($recordings as $recording): ?>
                    <?php
                    $rid = (int) ($recording['id'] ?? 0);
                    $cid = (int) ($recording['class_id'] ?? 0);
                    $sync = recordingSyncStatusForRow($recording);
                    $visible = (string) ($recording['visible_to_student'] ?? 'no');
                    $badgeClass = recordingSyncStatusBadgeClass($sync);
                    $syncLabel = strtoupper(recordingSyncStatusLabel($sync));
                    $studentAccess = $visible === 'yes' ? 'success' : 'secondary';
                    $studentAccessLabel = $visible === 'yes' ? 'Students' : 'Admin only';
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= h((string) ($recording['recording_title'] ?? $recording['class_title'] ?? 'Recording')) ?></div>
                            <div class="small text-muted">Class #<?= $cid ?> - <?= h((string) ($recording['class_title'] ?? '')) ?></div>
                            <div class="small text-muted">Scheduled: <?= h(formatClassScheduledAt($recording, 'd M Y h:i A T')) ?></div>
                            <div class="small text-muted"><?= h(formatClassScheduledTimezoneLabel($recording)) ?></div>
                            <?php if (classActualStartUtcValue($recording) !== null || classActualEndUtcValue($recording) !== null): ?>
                                <?php if (classActualStartUtcValue($recording) !== null): ?><div class="small text-muted mt-1">Started: <?= h(formatClassActualAt($recording, 'start', $adminTimezone)) ?></div><?php endif; ?>
                                <?php if (classActualEndUtcValue($recording) !== null): ?><div class="small text-muted">Ended: <?= h(formatClassActualAt($recording, 'end', $adminTimezone)) ?></div><?php endif; ?>
                                <div class="small text-muted"><?= h(formatClassActualTimezoneLabel($recording, $adminTimezone)) ?></div>
                                <div class="small text-muted">Actual duration: <?= h(formatDurationMinutes(classActualDurationMinutes($recording))) ?></div>
                            <?php endif; ?>
                            <?php if ($sync !== 'synced'): ?>
                                <div class="small <?= $sync === 'failed' ? 'text-danger' : 'text-muted' ?> mt-1"><?= h(recordingSyncStatusText($recording)) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= h((string) ($recording['teacher_name'] ?? '')) ?></td>
                        <td class="small text-muted"><?= h((string) ($recording['student_names'] ?? '')) ?></td>
                        <td>
                            <span class="badge <?= h($badgeClass) ?> text-uppercase"><?= h($syncLabel) ?></span>
                            <span class="badge text-bg-<?= h($studentAccess) ?>"><?= h($studentAccessLabel) ?></span>
                        </td>
                        <td>
                            <div class="small fw-semibold text-dark"><?= h(formatDurationMinutes(classActualDurationMinutes($recording))) ?></div>
                            <div class="small text-muted">Recording: <?= h(((int) ($recording['recording_duration'] ?? 0) > 0 ? (int) $recording['recording_duration'] . ' min' : ($sync === 'synced' ? '-' : '...'))) ?></div>
                        </td>
                        <td>
                            <form method="post"
                                  action="<?= h($base . '/admin/recordings/visibility') ?>"
                                  class="d-flex gap-2 align-items-center flex-wrap"
                                  data-loader-title="Saving visibility..."
                                  data-loader-text="Updating who can access this recording.">
                                <?php if ($rid > 0): ?>
                                    <input type="hidden" name="recording_id" value="<?= $rid ?>">
                                <?php else: ?>
                                    <input type="hidden" name="class_id" value="<?= $cid ?>">
                                    <span class="badge text-bg-light border">Awaiting Drive row</span>
                                <?php endif; ?>
                                <select class="form-select form-select-sm" name="visible_to_student" style="max-width:140px">
                                    <option value="no" <?= $visible === 'no' ? 'selected' : '' ?>>Admin only</option>
                                    <option value="yes" <?= $visible === 'yes' ? 'selected' : '' ?>>Show to student</option>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Save</button>
                            </form>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if (!empty($recording['recording_url'])): ?>
                                    <a href="<?= h((string) $recording['recording_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">View</a>
                                <?php elseif ($sync === 'processing' || $sync === 'pending'): ?>
                                    <span class="small text-muted"><?= h(recordingSyncStatusText($recording)) ?></span>
                                <?php elseif ($sync === 'disabled'): ?>
                                    <span class="small text-muted">Disabled</span>
                                <?php endif; ?>
                                <?php if ($sync !== 'disabled'): ?>
                                    <form method="post"
                                          action="<?= h($base . '/meeting/track') ?>"
                                          class="d-inline"
                                          data-loader-title="Syncing recording..."
                                          data-loader-text="Searching Google Drive for the matching recording file.">
                                        <input type="hidden" name="class_id" value="<?= $cid ?>">
                                        <input type="hidden" name="event" value="sync-recording">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Retry Sync</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php renderPagination($pagination ?? null, $queryParams ?? []); ?>
</div>

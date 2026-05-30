<?php

use function htmlspecialchars as h;

$base = defined('BASE_PATH') ? BASE_PATH : '';
$classes = $classes ?? [];
$classId = (int) ($classId ?? 0);
$classRow = $classRow ?? null;
$timezoneCheck = $timezoneCheck ?? null;
$debug = $debug ?? null;
$syncResult = $syncResult ?? null;
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Meeting Sync Debug</h1>
        <p class="text-muted small mb-0">Inspect Google Meet completion decisions, timezone storage, and API responses.</p>
    </div>
    <a href="<?= h($base . '/admin') ?>" class="btn btn-sm btn-outline-secondary">Back to Admin</a>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase">Classes with Meet link</h2>
                <div class="list-group list-group-flush small">
                    <?php foreach ($classes as $row): ?>
                        <?php $id = (int) ($row['id'] ?? 0); ?>
                        <a href="<?= h($base . '/admin/meeting-sync-debug?class_id=' . $id) ?>"
                           class="list-group-item list-group-item-action <?= $id === $classId ? 'active' : '' ?>">
                            <div class="fw-semibold">#<?= $id ?> — <?= h((string) ($row['title'] ?? '')) ?></div>
                            <div class="<?= $id === $classId ? 'text-white-50' : 'text-muted' ?>">
                                <?= h((string) ($row['status'] ?? '')) ?> /
                                <?= h((string) ($row['meeting_live_status'] ?? '')) ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <?php if ($classId <= 0): ?>
            <div class="alert alert-info">Select a class to inspect sync behavior.</div>
        <?php elseif ($classRow === null || $classRow === false): ?>
            <div class="alert alert-warning">Class not found.</div>
        <?php else: ?>
            <div class="d-flex gap-2 mb-3">
                <a href="<?= h($base . '/admin/meeting-sync-debug?class_id=' . $classId . '&run=1') ?>"
                   class="btn btn-primary btn-sm">Run live sync now</a>
                <a href="<?= h($base . '/admin/meeting-sync-debug?class_id=' . $classId) ?>"
                   class="btn btn-outline-secondary btn-sm">Refresh without sync</a>
            </div>

            <?php if (is_array($syncResult)): ?>
                <div class="alert alert-secondary small">
                    Cron-style result:
                    <code>status=<?= h((string) ($syncResult['status'] ?? '')) ?></code>,
                    <code>meeting_live_status=<?= h((string) ($syncResult['meeting_live_status'] ?? '')) ?></code>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 text-muted text-uppercase">Class snapshot</h2>
                    <table class="table table-sm mb-0">
                        <tbody>
                        <tr><th>Class ID</th><td><?= (int) ($classRow['id'] ?? 0) ?></td></tr>
                        <tr><th>DB status</th><td><span class="badge text-bg-warning text-uppercase"><?= h((string) ($classRow['status'] ?? '')) ?></span></td></tr>
                        <tr><th>meeting_live_status</th><td><?= h((string) ($classRow['meeting_live_status'] ?? '')) ?></td></tr>
                        <tr><th>Timezone</th><td><?= h((string) ($classRow['scheduled_timezone'] ?? '')) ?></td></tr>
                        <tr><th>start_time_utc</th><td><?= h((string) ($classRow['start_time_utc'] ?? '')) ?></td></tr>
                        <tr><th>end_time_utc</th><td><?= h((string) ($classRow['end_time_utc'] ?? '')) ?></td></tr>
                        <tr><th>Scheduled local start</th><td><?= h((string) ($timezoneCheck['displayed_local_start'] ?? '')) ?></td></tr>
                        <tr><th>Scheduled local end</th><td><?= h((string) ($timezoneCheck['displayed_local_end'] ?? '')) ?></td></tr>
                        <tr><th>actual_start_time</th><td><?= h((string) ($classRow['actual_start_time'] ?? '—')) ?></td></tr>
                        <tr><th>actual_end_time</th><td><?= h((string) ($classRow['actual_end_time'] ?? '—')) ?></td></tr>
                        <tr><th>teacher_joined_at</th><td><?= h((string) ($classRow['teacher_joined_at'] ?? '—')) ?></td></tr>
                        <tr><th>Teacher email</th><td><?= h((string) ($classRow['teacher_google_email'] ?? '')) ?></td></tr>
                        <tr><th>google_conference_id</th><td class="small"><?= h((string) ($classRow['google_conference_id'] ?? '—')) ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (is_array($timezoneCheck)): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-body small">
                        <h2 class="h6 text-muted text-uppercase">Timezone verification</h2>
                        <p class="mb-1"><?= h((string) ($timezoneCheck['note'] ?? '')) ?></p>
                        <p class="mb-0">Example IST from stored UTC:
                            start <code><?= h((string) ($timezoneCheck['example_ist_from_stored_utc']['start'] ?? '')) ?></code>,
                            end <code><?= h((string) ($timezoneCheck['example_ist_from_stored_utc']['end'] ?? '')) ?></code>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (is_array($debug)): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <h2 class="h6 text-muted text-uppercase">Completion decision</h2>
                        <p class="mb-2"><strong>Result:</strong> <code><?= h((string) ($debug['completion_decision'] ?? '')) ?></code></p>
                        <p class="mb-3"><strong>Reason:</strong> <?= h((string) ($debug['reason'] ?? '')) ?></p>
                        <p class="small text-muted mb-3"><?= h((string) ($debug['api_source'] ?? '')) ?></p>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                <tr>
                                    <th>Condition</th>
                                    <th>Result</th>
                                    <th>Detail</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach (($debug['conditions'] ?? []) as $row): ?>
                                    <tr>
                                        <td><?= h((string) ($row['label'] ?? '')) ?></td>
                                        <td>
                                            <?php if (!empty($row['result'])): ?>
                                                <span class="badge text-bg-success">TRUE</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-danger">FALSE</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted"><?= h((string) ($row['detail'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body small">
                    <h2 class="h6 text-muted text-uppercase">Log files</h2>
                    <ul class="mb-0">
                        <li><code>logs/meeting_status_debug.log</code> — per-class conditions</li>
                        <li><code>logs/google_meet_response.log</code> — raw Meet API payload</li>
                        <li><code>logs/google_meet_status.log</code> — host join/leave events</li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

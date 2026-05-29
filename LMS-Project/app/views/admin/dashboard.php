<?php

use function htmlspecialchars as h;

$recentRecordings = $recentRecordings ?? [];
$adminTimezone = resolveUserTimezone(Auth::user() ?: null, APP_TIMEZONE);
?>

<div class="row g-3">
    <div class="col-12">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h4 mb-0">Admin Dashboard</h1>
                <p class="text-muted mb-0 small">Overview of classes, payouts, connections, and recordings.</p>
            </div>
            <span class="badge text-bg-dark text-uppercase">Admin</span>
        </div>
    </div>

    <div class="col-12 col-md-6 col-xl-4">
        <div class="card dashboard-stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon bg-primary text-white"><i class="fa-solid fa-user-graduate"></i></span>
                <div>
                    <h2 class="h6 text-muted text-uppercase mb-1 small">Students</h2>
                    <p class="display-6 fw-semibold mb-0 lh-1"><?= (int) $totalStudents ?></p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card dashboard-stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon bg-info text-white"><i class="fa-solid fa-chalkboard-teacher"></i></span>
                <div>
                    <h2 class="h6 text-muted text-uppercase mb-1 small">Teachers</h2>
                    <p class="display-6 fw-semibold mb-0 lh-1"><?= (int) $totalTeachers ?></p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card dashboard-stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon bg-secondary text-white"><i class="fa-solid fa-calendar-check"></i></span>
                <div>
                    <h2 class="h6 text-muted text-uppercase mb-1 small">Scheduled</h2>
                    <p class="display-6 fw-semibold mb-0 lh-1"><?= (int) ($classStats['scheduled'] ?? 0) ?></p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card dashboard-stat-card h-100 border-warning border-opacity-50">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon bg-warning text-dark"><i class="fa-solid fa-bolt"></i></span>
                <div>
                    <h2 class="h6 text-muted text-uppercase mb-1 small">Ongoing</h2>
                    <p class="display-6 fw-semibold mb-0 lh-1"><?= (int) ($classStats['ongoing'] ?? 0) ?></p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card dashboard-stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon bg-success text-white"><i class="fa-solid fa-circle-check"></i></span>
                <div>
                    <h2 class="h6 text-muted text-uppercase mb-1 small">Completed</h2>
                    <p class="display-6 fw-semibold mb-0 lh-1"><?= (int) ($classStats['completed'] ?? 0) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6">
        <div class="card dashboard-stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="fa-solid fa-hourglass-half"></i></span>
                <div>
                    <h2 class="h6 text-muted text-uppercase mb-1 small">Payouts (Pending)</h2>
                    <p class="display-6 fw-semibold mb-0 lh-1"><?= h(formatCurrency((float) $totalPayoutPending)) ?></p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card dashboard-stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon bg-success bg-opacity-10 text-success"><i class="fa-solid fa-sack-dollar"></i></span>
                <div>
                    <h2 class="h6 text-muted text-uppercase mb-1 small">Payouts (Paid)</h2>
                    <p class="display-6 fw-semibold mb-0 lh-1"><?= h(formatCurrency((float) $totalPayoutPaid)) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card shadow-sm" id="teacher-google-connections">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase mb-3">Teacher Google Connections</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Teacher Email</th>
                            <th>Google Email</th>
                            <th>Account</th>
                            <th>Recording</th>
                            <th>Status</th>
                            <th>Connected At</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($teacherGoogleAccounts)): ?>
                            <tr><td colspan="8" class="text-muted small">No teachers found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($teacherGoogleAccounts as $row): ?>
                                <?php $status = (string) ($row['status'] ?? 'disconnected'); ?>
                                <?php $statusClass = $status === 'active' ? 'text-bg-success' : ($status === 'error' ? 'text-bg-danger' : 'text-bg-secondary'); ?>
                                <?php
                                    $googleEmailDash = strtolower(trim((string) ($row['google_email'] ?? '')));
                                    $isPersonalMail = ($googleEmailDash !== '' && (str_ends_with($googleEmailDash, '@gmail.com') || str_ends_with($googleEmailDash, '@googlemail.com')));
                                    $acctType = strtolower(trim((string) ($row['account_type'] ?? '')));
                                    if ($acctType === '' && $googleEmailDash !== '') {
                                        $acctType = $isPersonalMail ? 'personal' : 'workspace';
                                    }
                                    $recSupported = array_key_exists('recording_supported', $row) && $row['recording_supported'] !== null
                                        ? ((int) $row['recording_supported'] === 1)
                                        : ($googleEmailDash === '' ? false : !$isPersonalMail);
                                ?>
                                <tr>
                                    <td><?= h((string) ($row['teacher_name'] ?? '')) ?></td>
                                    <td><?= h((string) ($row['teacher_email'] ?? '')) ?></td>
                                    <td><?= h((string) ($row['google_email'] ?? 'Not connected')) ?></td>
                                    <td><span class="badge text-bg-light border text-uppercase"><?= $googleEmailDash === '' ? h('—') : h($acctType !== '' ? $acctType : '?') ?></span></td>
                                    <td><?= $recSupported ? '<span class="badge text-bg-success">Yes</span>' : '<span class="badge text-bg-secondary">No</span>' ?></td>
                                    <td><span class="badge <?= h($statusClass) ?> text-uppercase"><?= h($status) ?></span></td>
                                    <td><?= h((string) ($row['connected_at'] ?? '')) ?></td>
                                    <td>
                                        <form method="post" action="<?= h((defined('BASE_PATH') ? BASE_PATH : '') . ($status === 'active' ? '/disconnect-google' : '/connect-google')) ?>" class="d-inline">
                                            <input type="hidden" name="teacher_id" value="<?= (int) ($row['teacher_id'] ?? 0) ?>">
                                            <button type="submit"
                                                    class="btn btn-sm <?= $status === 'active' ? 'btn-outline-danger' : 'btn-outline-primary' ?>"
                                                    <?= $status === 'active' ? 'data-confirm="1" data-confirm-title="Disconnect Google account?" data-confirm-text="This teacher will stop using the connected Google host account for new classes." data-confirm-button="Disconnect"' : '' ?>>
                                                <?= $status === 'active' ? 'Disconnect' : 'Connect' ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 text-muted text-uppercase mb-0">Recent Classes</h2>
                    <a href="<?= h((defined('BASE_PATH') ? BASE_PATH : '') . '/classes') ?>" class="btn btn-sm btn-outline-primary">Open Classes</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Title</th>
                            <th>Teacher</th>
                            <th>Start</th>
                            <th>Status</th>
                            <th>Duration</th>
                            <th>Recording</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recentClasses)): ?>
                            <tr><td colspan="6" class="text-muted small">No classes yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentClasses as $cls): ?>
                                <tr>
                                    <td><?= h((string) ($cls['title'] ?? '')) ?></td>
                                    <td><?= h((string) ($cls['teacher_name'] ?? '')) ?></td>
                                    <td>
                                        <div><?= h(formatUtcForTimezone(classStartUtcValue($cls), $adminTimezone, 'd M Y h:i A T')) ?></div>
                                        <div class="small text-muted">Scheduled: <?= h(formatClassScheduledAt($cls, 'd M Y h:i A T')) ?></div>
                                        <div class="small text-muted"><?= h(formatClassScheduledTimezoneLabel($cls)) ?></div>
                                        <?php if (classActualStartUtcValue($cls) !== null): ?>
                                            <div class="small text-muted mt-1">Actual start: <?= h(formatClassActualAt($cls, 'start', $adminTimezone)) ?></div>
                                        <?php endif; ?>
                                        <?php if (classActualEndUtcValue($cls) !== null): ?>
                                            <div class="small text-muted">Actual end: <?= h(formatClassActualAt($cls, 'end', $adminTimezone)) ?></div>
                                        <?php endif; ?>
                                        <?php if (classActualStartUtcValue($cls) !== null || classActualEndUtcValue($cls) !== null): ?>
                                            <div class="small text-muted"><?= h(formatClassActualTimezoneLabel($cls, $adminTimezone)) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?= h(classStatusBadgeClass((string) ($cls['status'] ?? 'scheduled'))) ?> text-uppercase"><?= h((string) ($cls['status'] ?? 'scheduled')) ?></span></td>
                                    <td class="small text-muted"><?= h(ClassSession::formatActualDuration($cls)) ?></td>
                                    <td>
                                        <?php if (!empty($cls['recording_url'])): ?>
                                            <a href="<?= h((string) $cls['recording_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">View Recording</a>
                                        <?php else: ?>
                                            <?php $recordingStatus = recordingSyncStatusForRow($cls); ?>
                                            <span class="text-muted small"><?= h($recordingStatus === 'disabled' ? 'Recording disabled' : recordingSyncStatusText($cls)) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 text-muted text-uppercase mb-0">Recent Recordings</h2>
                    <a href="<?= h((defined('BASE_PATH') ? BASE_PATH : '') . '/admin/recordings') ?>" class="btn btn-sm btn-outline-primary">Open Recordings</a>
                </div>
                <div class="row g-3">
                    <?php if ($recentRecordings === []): ?>
                        <div class="col-12"><p class="text-muted small mb-0">No recordings synced yet.</p></div>
                    <?php else: ?>
                        <?php foreach ($recentRecordings as $recording): ?>
                            <?php $recordingStatus = recordingSyncStatusForRow($recording); ?>
                            <div class="col-12">
                                <div class="recording-meta-card p-3">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <div>
                                            <div class="fw-semibold"><?= h((string) ($recording['recording_title'] ?? $recording['class_title'] ?? 'Recording')) ?></div>
                                            <div class="small text-muted"><?= h((string) ($recording['teacher_name'] ?? '')) ?></div>
                                        </div>
                                        <span class="badge <?= h(recordingSyncStatusBadgeClass($recordingStatus)) ?> text-uppercase"><?= h(recordingSyncStatusLabel($recordingStatus)) ?></span>
                                    </div>
                                    <?php if (classActualStartUtcValue($recording) !== null || classActualEndUtcValue($recording) !== null): ?>
                                        <?php if (classActualStartUtcValue($recording) !== null): ?><div class="small text-muted mb-1">Started: <?= h(formatClassActualAt($recording, 'start')) ?></div><?php endif; ?>
                                        <?php if (classActualEndUtcValue($recording) !== null): ?><div class="small text-muted mb-1">Ended: <?= h(formatClassActualAt($recording, 'end')) ?></div><?php endif; ?>
                                        <div class="small text-muted mb-1"><?= h(formatClassActualTimezoneLabel($recording)) ?></div>
                                    <?php endif; ?>
                                    <div class="small text-muted mb-2">Students: <?= h((string) ($recording['student_names'] ?? '')) ?></div>
                                    <div class="small text-muted mb-3">Duration: <?= h(((int) ($recording['recording_duration'] ?? 0) > 0 ? (int) $recording['recording_duration'] . ' min' : 'Unknown')) ?></div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php if (!empty($recording['recording_url'])): ?>
                                            <a href="<?= h((string) $recording['recording_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">View</a>
                                        <?php endif; ?>
                                        <?php if ($recordingStatus !== 'synced'): ?>
                                            <span class="small text-muted"><?= h(recordingSyncStatusText($recording)) ?></span>
                                        <?php endif; ?>
                                        <span class="small text-muted"><?= ($recording['visible_to_student'] ?? 'no') === 'yes' ? 'Visible to student' : 'Hidden from student' ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase mb-3">Teacher Payouts</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Pending</th>
                            <th>Paid</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($teacherPayouts)): ?>
                            <tr><td colspan="3" class="text-muted small">No payout data yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($teacherPayouts as $tp): ?>
                                <tr>
                                    <td><?= h((string) ($tp['name'] ?? '')) ?></td>
                                    <td><?= h(formatCurrency((float) ($tp['pending_amount'] ?? 0))) ?></td>
                                    <td><?= h(formatCurrency((float) ($tp['paid_amount'] ?? 0))) ?></td>
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

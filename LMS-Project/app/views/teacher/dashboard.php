<?php

use function htmlspecialchars as h;

$base = defined('BASE_PATH') ? BASE_PATH : '';
$pb = $payoutBreakdown ?? ['pending' => 0, 'paid' => 0, 'total' => 0, 'completed_classes' => 0];
$googleAccount = $googleAccount ?? null;
$googleStatus = is_array($googleAccount) ? (string) ($googleAccount['status'] ?? 'disconnected') : 'disconnected';
$googleEmail = is_array($googleAccount) ? (string) ($googleAccount['google_email'] ?? '') : '';
$teacherGoogleAccountKind = (string) ($teacherGoogleAccountKind ?? 'workspace');
$teacherGoogleRecordingCapability = (bool) ($teacherGoogleRecordingCapability ?? true);
$recordings = $recordings ?? [];
$teacherTimezone = resolveUserTimezone(Auth::user() ?: null, APP_TIMEZONE);
?>

<div class="row g-3">
    <div class="col-12">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h4 mb-0">Teacher Dashboard</h1>
                <p class="text-muted mb-0 small">Your live classes, payouts, and recordings.</p>
            </div>
            <span class="badge text-bg-info text-uppercase">Teacher</span>
        </div>
    </div>

    <div class="col-12 col-md-4">
        <div class="card dashboard-stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon bg-primary text-white"><i class="fa-solid fa-calendar-days"></i></span>
                <div>
                    <h2 class="h6 text-muted text-uppercase mb-1 small">Upcoming Classes</h2>
                    <p class="display-6 fw-semibold mb-0 lh-1"><?= count($upcomingClasses ?? []) ?></p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card dashboard-stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon bg-success text-white"><i class="fa-solid fa-circle-check"></i></span>
                <div>
                    <h2 class="h6 text-muted text-uppercase mb-1 small">Completed</h2>
                    <p class="display-6 fw-semibold mb-0 lh-1"><?= count($completedClasses ?? []) ?></p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card dashboard-stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon bg-info text-white"><i class="fa-solid fa-sack-dollar"></i></span>
                <div>
                    <h2 class="h6 text-muted text-uppercase mb-1 small">Total Payout</h2>
                    <p class="display-6 fw-semibold mb-0 lh-1"><?= h(formatCurrency((float) ($totalPayout ?? 0))) ?></p>
                    <p class="small text-muted mb-0">Pending: <?= h(formatCurrency((float) ($pb['pending'] ?? 0))) ?> | Paid: <?= h(formatCurrency((float) ($pb['paid'] ?? 0))) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h2 class="h6 text-muted text-uppercase mb-2">Google Calendar Connection</h2>
                    <?php if ($googleStatus === 'active' && $googleEmail !== ''): ?>
                        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                            <span class="badge text-bg-secondary text-uppercase"><?= h($teacherGoogleAccountKind === 'personal' ? 'Personal Gmail' : 'Workspace / custom domain') ?></span>
                            <?php if ($teacherGoogleRecordingCapability): ?>
                                <span class="badge text-bg-success">Recording / Drive sync eligible</span>
                            <?php else: ?>
                                <span class="badge text-bg-warning text-dark">Cloud recording sync N/A</span>
                            <?php endif; ?>
                        </div>
                        <p class="mb-1"><strong>Connected:</strong> <?= h($googleEmail) ?></p>
                        <p class="small text-muted mb-0">This account creates Google Meet meetings as organizer. <?= $teacherGoogleRecordingCapability ? 'Recording reminders and Drive sync are enabled.' : 'Personal Gmail hosts Meet normally; Workspace is required for cloud recording sync.' ?></p>
                    <?php elseif ($googleStatus === 'error'): ?>
                        <p class="mb-1"><strong>Connection Error:</strong> <?= h($googleEmail !== '' ? $googleEmail : 'Invalid account') ?></p>
                        <p class="small text-danger mb-0">Reconnect your Google account. If your school enforces Workspace-only logins, set <code class="small">GOOGLE_REQUIRE_WORKSPACE_DOMAIN=1</code> and <code class="small">GOOGLE_WORKSPACE_DOMAIN</code> accordingly.</p>
                    <?php else: ?>
                        <p class="mb-1">No Google account connected.</p>
                        <p class="small text-muted mb-0">Connect Google (Workspace <em>or</em> Gmail) to host Meet. Recording sync needs Workspace-style domains.</p>
                    <?php endif; ?>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <form method="post" action="<?= h($base . '/connect-google') ?>">
                        <button type="submit" class="btn btn-primary btn-sm">Connect Google Account</button>
                    </form>
                    <?php if ($googleStatus === 'active'): ?>
                        <form method="post" action="<?= h($base . '/disconnect-google') ?>"
                              data-confirm="1"
                              data-confirm-title="Disconnect Google account?"
                              data-confirm-text="New classes will stop using this account until it is connected again."
                              data-confirm-button="Disconnect">
                            <button type="submit" class="btn btn-outline-danger btn-sm">Disconnect Google</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase mb-3">Upcoming Classes</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Title</th>
                            <th>Start</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($upcomingClasses)): ?>
                            <tr><td colspan="4" class="text-muted small">No upcoming or in-progress classes.</td></tr>
                        <?php else: ?>
                            <?php foreach ($upcomingClasses as $cls): ?>
                                <tr>
                                    <td><?= h((string) ($cls['title'] ?? '')) ?></td>
                                    <td>
                                        <div><?= h(formatUtcForTimezone(classStartUtcValue($cls), $teacherTimezone, 'd M Y h:i A T')) ?></div>
                                        <div class="small text-muted"><?= h(formatClassScheduledAt($cls, 'd M Y h:i A T')) ?></div>
                                        <div class="small text-muted"><?= h(formatClassScheduledTimezoneLabel($cls)) ?></div>
                                        <?php if (classActualStartUtcValue($cls) !== null): ?>
                                            <div class="small text-muted mt-1">Actual start: <?= h(formatClassActualAt($cls, 'start', $teacherTimezone)) ?></div>
                                            <div class="small text-muted"><?= h(formatClassActualTimezoneLabel($cls, $teacherTimezone)) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?= h(classStatusBadgeClass((string) ($cls['status'] ?? 'scheduled'))) ?> text-uppercase"><?= h((string) ($cls['status'] ?? 'scheduled')) ?></span></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="<?= h($base . '/join-class?class_id=' . (int) ($cls['id'] ?? 0)) ?>" class="btn btn-sm btn-primary" target="_blank" rel="noopener">Start as Host</a>
                                            <form method="post"
                                                  action="<?= h($base . '/meeting/track') ?>"
                                                  class="d-inline"
                                                  data-confirm="1"
                                                  data-confirm-title="End this class?"
                                                  data-confirm-text="The class will be marked completed using the actual meeting start and end time."
                                                  data-confirm-button="End class"
                                                  data-loader-title="Ending class..."
                                                  data-loader-text="Saving actual end time and calculating real class duration.">
                                                <input type="hidden" name="class_id" value="<?= (int) ($cls['id'] ?? 0) ?>">
                                                <input type="hidden" name="event" value="leave">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">End Class</button>
                                            </form>
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
    </div>

    <div class="col-12 col-xl-5">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase mb-3">Completed Classes</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Title</th>
                            <th>Duration</th>
                            <th>Recording</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($completedClasses)): ?>
                            <tr><td colspan="3" class="text-muted small">No completed classes yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($completedClasses as $cls): ?>
                                <?php $recordingStatus = recordingSyncStatusForRow($cls); ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= h((string) ($cls['title'] ?? '')) ?></div>
                                        <?php if (classActualStartUtcValue($cls) !== null || classActualEndUtcValue($cls) !== null): ?>
                                            <div class="small text-muted"><?= h(formatClassActualAt($cls, 'start', $teacherTimezone)) ?><?= classActualEndUtcValue($cls) !== null ? (' to ' . formatClassActualAt($cls, 'end', $teacherTimezone)) : '' ?></div>
                                            <div class="small text-muted"><?= h(formatClassActualTimezoneLabel($cls, $teacherTimezone)) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= h(ClassSession::formatActualDuration($cls)) ?></td>
                                    <td>
                                        <?php if (!empty($cls['recording_url'])): ?>
                                            <a href="<?= h((string) $cls['recording_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">View</a>
                                        <?php elseif ($recordingStatus === 'processing' || $recordingStatus === 'failed'): ?>
                                            <span class="text-muted small"><?= h(recordingSyncStatusText($cls)) ?></span>
                                        <?php elseif ($recordingStatus === 'disabled'): ?>
                                            <span class="text-muted small">Recording disabled</span>
                                        <?php else: ?>
                                            <form method="post"
                                                  action="<?= h($base . '/meeting/track') ?>"
                                                  class="d-inline"
                                                  data-loader-title="Syncing recording..."
                                                  data-loader-text="Checking Google Drive for the latest recording file.">
                                                <input type="hidden" name="class_id" value="<?= (int) ($cls['id'] ?? 0) ?>">
                                                <input type="hidden" name="event" value="sync-recording">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Sync</button>
                                            </form>
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

    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase mb-3">My Recordings</h2>
                <div class="row g-3">
                    <?php if ($recordings === []): ?>
                        <div class="col-12"><p class="text-muted small mb-0">No recordings synced yet.</p></div>
                    <?php else: ?>
                        <?php foreach ($recordings as $recording): ?>
                            <div class="col-12 col-md-6 col-xl-4">
                                <div class="recording-meta-card p-3 h-100">
                                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                        <div>
                                            <div class="fw-semibold"><?= h((string) ($recording['recording_title'] ?? $recording['class_title'] ?? 'Recording')) ?></div>
                                            <div class="small text-muted"><?= h((string) ($recording['class_title'] ?? '')) ?></div>
                                        </div>
                                        <i class="fa-solid fa-video text-primary"></i>
                                    </div>
                                    <?php if (classActualStartUtcValue($recording) !== null || classActualEndUtcValue($recording) !== null): ?>
                                        <?php if (classActualStartUtcValue($recording) !== null): ?><div class="small text-muted mb-1">Started: <?= h(formatClassActualAt($recording, 'start', $teacherTimezone)) ?></div><?php endif; ?>
                                        <?php if (classActualEndUtcValue($recording) !== null): ?><div class="small text-muted mb-1">Ended: <?= h(formatClassActualAt($recording, 'end', $teacherTimezone)) ?></div><?php endif; ?>
                                        <div class="small text-muted mb-1"><?= h(formatClassActualTimezoneLabel($recording, $teacherTimezone)) ?></div>
                                    <?php endif; ?>
                                    <div class="small text-muted mb-1">Class duration: <?= h(formatDurationMinutes(classActualDurationMinutes($recording))) ?></div>
                                    <div class="small text-muted mb-3">Recording runtime: <?= h(((int) ($recording['recording_duration'] ?? 0) > 0 ? (int) $recording['recording_duration'] . ' min' : 'Unknown')) ?></div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php if (!empty($recording['recording_url'])): ?>
                                            <a href="<?= h((string) $recording['recording_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">View</a>
                                        <?php else: ?>
                                            <span class="text-muted small"><?= h(recordingSyncStatusText($recording)) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

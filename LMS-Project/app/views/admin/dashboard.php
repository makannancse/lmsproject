<?php

use function htmlspecialchars as h;

$recentRecordings = $recentRecordings ?? [];
$adminTimezone = resolveUserTimezone(Auth::user() ?: null, APP_TIMEZONE);
unset($recentRecordings);
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
                                        <form method="post" action="<?= h(appWebPath() . ($status === 'active' ? '/disconnect-google' : '/connect-google')) ?>" class="d-inline">
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
                <?php renderPagination($googlePagination ?? null, []); ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase mb-3">Current Late Teacher Joins</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Class</th>
                            <th>Teacher</th>
                            <th>Scheduled</th>
                            <th>Late Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $currentLateClasses = $currentLateClasses ?? []; ?>
                        <?php if ($currentLateClasses === []): ?>
                            <tr><td colspan="4" class="text-muted small">No teachers are currently late.</td></tr>
                        <?php else: ?>
                            <?php foreach ($currentLateClasses as $cls): ?>
                                <tr>
                                    <td><?= h((string) ($cls['title'] ?? '')) ?></td>
                                    <td><?= h((string) ($cls['teacher_name'] ?? '')) ?></td>
                                    <td class="small"><?= h(formatClassScheduledAt($cls, 'd M Y h:i A T')) ?></td>
                                    <td><?= teacherLateJoinNoticeHtml($cls, 'mb-0') ?></td>
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
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 text-muted text-uppercase mb-0">Recent Completed Classes</h2>
                    <a href="<?= h(path('classes/completed')) ?>" class="btn btn-sm btn-outline-secondary">View all</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Class</th>
                            <th>Teacher</th>
                            <th>Scheduled</th>
                            <th>Join Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $recentCompletedClasses = $recentCompletedClasses ?? []; ?>
                        <?php if ($recentCompletedClasses === []): ?>
                            <tr><td colspan="4" class="text-muted small">No completed classes yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentCompletedClasses as $cls): ?>
                                <tr>
                                    <td><?= h((string) ($cls['title'] ?? '')) ?></td>
                                    <td><?= h((string) ($cls['teacher_name'] ?? '')) ?></td>
                                    <td class="small"><?= h(formatClassScheduledAt($cls, 'd M Y h:i A T')) ?></td>
                                    <td>
                                        <?php if (teacherLateJoinNoticeText($cls) !== null): ?>
                                            <?= teacherLateJoinNoticeHtml($cls, 'mb-0') ?>
                                        <?php else: ?>
                                            <span class="small text-success">On time</span>
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

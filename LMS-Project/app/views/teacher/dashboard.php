<?php

use function htmlspecialchars as h;

$base = appWebPath();
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

    <!-- Teaching Guidelines / Rubric -->
    <div class="col-12">
        <div class="accordion shadow-sm mb-3" id="teachingStandardsAccordion">
            <div class="accordion-item border-0 rounded">
                <h2 class="accordion-header" id="headingStandards">
                    <button class="accordion-button collapsed fw-semibold rounded bg-white text-dark border shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#collapseStandards" aria-expanded="false" aria-controls="collapseStandards">
                        <i class="fa-solid fa-chalkboard-user me-2 text-primary"></i> Online Teaching Standards &amp; Best Practices
                    </button>
                </h2>
                <div id="collapseStandards" class="accordion-collapse collapse" aria-labelledby="headingStandards" data-bs-parent="#teachingStandardsAccordion">
                    <div class="accordion-body bg-light rounded-bottom border-top shadow-sm">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-3">
                                        <div class="fw-bold">🎯 Classroom Environment</div>
                                        <div class="text-muted small ms-4">Create a positive, inclusive, and engaging space where students feel safe, valued, and motivated to learn.</div>
                                    </li>
                                    <li class="mb-3">
                                        <div class="fw-bold">💬 Communication</div>
                                        <div class="text-muted small ms-4">Be clear, respectful, and professional. Ensure students and parents always understand expectations.</div>
                                    </li>
                                    <li class="mb-3">
                                        <div class="fw-bold">🤝 Student &amp; Parent Relationships</div>
                                        <div class="text-muted small ms-4">Build strong rapport through friendliness, respect, and confident, solution-oriented interactions.</div>
                                    </li>
                                    <li class="mb-3">
                                        <div class="fw-bold">📗 Lesson Delivery</div>
                                        <div class="text-muted small ms-4">Be well-prepared, use simple examples, and adapt teaching pace to suit every learner's needs.</div>
                                    </li>
                                    <li class="mb-3">
                                        <div class="fw-bold">🧠 Student Engagement</div>
                                        <div class="text-muted small ms-4">Encourage participation through active listening, age-appropriate interaction, and flexible teaching methods.</div>
                                    </li>
                                    <li class="mb-3">
                                        <div class="fw-bold">👔 Professional Discipline</div>
                                        <div class="text-muted small ms-4">Be punctual, manage time effectively, and follow structured protocols for attendance and delays.</div>
                                    </li>
                                    <li class="mb-3">
                                        <div class="fw-bold">📱 Communication Protocols</div>
                                        <div class="text-muted small ms-4">Stay responsive on groups, share updates transparently, and keep coordination smooth.</div>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-3">
                                        <div class="fw-bold">⚙️ Accountability</div>
                                        <div class="text-muted small ms-4">Track sessions, handle issues responsibly, and maintain high professional standards at all times.</div>
                                    </li>
                                    <li class="mb-3">
                                        <div class="fw-bold">🧩 Background</div>
                                        <div class="text-muted small ms-4">Maintain a clean, distraction-free, and professional background during all classes. Ensure proper lighting and clear visibility.</div>
                                    </li>
                                    <li class="mb-3">
                                        <div class="fw-bold">⏰ Punctuality</div>
                                        <div class="text-muted small ms-4">Join sessions on time. Be ready before the scheduled start with all materials prepared.</div>
                                    </li>
                                    <li class="mb-3">
                                        <div class="fw-bold">🚫 Avoid No-Show</div>
                                        <div class="text-muted small ms-4">Inform in advance in case of any absence. No-shows without prior notice are strictly discouraged and will affect performance evaluation.</div>
                                    </li>
                                    <li class="mb-3">
                                        <div class="fw-bold">📅 Notice Period</div>
                                        <div class="text-muted small ms-4">Follow proper notice period guidelines before taking leave or discontinuing sessions. Ensure smooth transition for students.</div>
                                    </li>
                                    <li class="mb-3">
                                        <div class="fw-bold">👔 Dress Code</div>
                                        <div class="text-muted small ms-4">Maintain a neat, professional appearance. Dress appropriately to reflect a respectful and serious teaching environment.</div>
                                    </li>
                                    <li class="mb-0">
                                        <div class="fw-bold">🏆 Mission</div>
                                        <div class="text-muted small ms-4">Deliver consistent, high-quality learning experiences with professionalism, care, and impact.</div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-4">
        <div class="card dashboard-stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon bg-primary text-white"><i class="fa-solid fa-calendar-days"></i></span>
                <div>
                    <h2 class="h6 text-muted text-uppercase mb-1 small">Upcoming Classes</h2>
                    <p class="display-6 fw-semibold mb-0 lh-1"><?= (int) ($upcomingCount ?? count($upcomingClasses ?? [])) ?></p>
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

    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase mb-3">Assigned Students</h2>
                <?php $assignedStudents = $assignedStudents ?? []; ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class Name</th>
                            <th>Timezone</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($assignedStudents === []): ?>
                            <tr><td colspan="4" class="text-muted small">No students mapped to you yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($assignedStudents as $student): ?>
                                <tr>
                                    <td><?= h((string) ($student['name'] ?? '')) ?></td>
                                    <td><?= h((string) ($student['class_name'] ?? '—')) ?></td>
                                    <td><?= h((string) ($student['timezone'] ?? '—')) ?></td>
                                    <td>
                                        <?php if (strtolower((string) ($student['status'] ?? 'active')) === 'active'): ?>
                                            <span class="badge text-bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary">Inactive</span>
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

    <div class="col-12 col-xl-7">
        <div class="card shadow-sm h-100" id="upcoming-classes">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h2 class="h6 text-muted text-uppercase mb-0">Upcoming Classes</h2>
                    <span class="badge text-bg-light text-dark border"><?= (int) ($upcomingPagination['total'] ?? count($upcomingClasses ?? [])) ?> total</span>
                </div>
                <form method="get" action="<?= h(path('teacher')) ?>" class="row g-2 align-items-end mb-3 no-app-loader">
                    <div class="col-md-8">
                        <label class="form-label form-label-sm mb-0" for="upcomingSearch">Search class or student</label>
                        <input type="search" class="form-control form-control-sm" id="upcomingSearch" name="upcoming_q"
                               value="<?= h((string) ($upcomingSearch ?? '')) ?>" placeholder="Class title or student name">
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary">Search</button>
                        <a href="<?= h(path('teacher')) ?>#upcoming-classes" class="btn btn-sm btn-outline-secondary">Reset</a>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class Title</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Timezone</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($upcomingClasses)): ?>
                            <tr><td colspan="7" class="text-muted small">No upcoming or in-progress classes.</td></tr>
                        <?php else: ?>
                            <?php foreach ($upcomingClasses as $cls): ?>
                                <tr>
                                    <td class="small"><?= h((string) ($cls['student_names'] ?? '—')) ?></td>
                                    <td><?= h((string) ($cls['title'] ?? '')) ?></td>
                                    <td class="small"><?= h(formatClassScheduledAt($cls, 'd M Y')) ?></td>
                                    <td class="small"><?= h(formatClassScheduledAt($cls, 'h:i A T')) ?></td>
                                    <td class="small"><?= h(formatClassScheduledTimezoneLabel($cls)) ?></td>
                                    <td><span class="badge <?= h(classStatusBadgeClass((string) ($cls['status'] ?? 'scheduled'))) ?> text-uppercase"><?= h((string) ($cls['status'] ?? 'scheduled')) ?></span><?= teacherLateJoinBadgeHtml($cls, 'teacher') ?></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="<?= h(path('join-class?class_id=' . (int) ($cls['id'] ?? 0))) ?>" class="btn btn-sm btn-primary" target="_blank" rel="noopener">Join Class</a>
                                            <?php $meetLink = trim((string) ($cls['meeting_link'] ?? '')); ?>
                                            <?php if ($meetLink !== ''): ?>
                                                <a href="<?= h($meetLink) ?>" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">Open Meet</a>
                                            <?php endif; ?>
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
                <?php
                $pagination = $upcomingPagination ?? null;
                $queryParams = $upcomingQueryParams ?? [];
                $pageParam = 'upcoming_page';
                $perPageParam = 'upcoming_per_page';
                $perPageOptions = [10, 25, 50];
                $showFirstLast = false;
                require dirname(__DIR__) . '/partials/pagination.php';
                ?>
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
                                        <?= teacherLateJoinNoticeHtml($cls, '', 'teacher') ?>
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

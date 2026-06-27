<?php

use function htmlspecialchars as h;

$base = appWebPath();
$studentBannerFsPath = dirname(__DIR__, 3) . '/public/assets/images/student-banner.jpg';
$defaultBannerFsPath = dirname(__DIR__, 3) . '/public/assets/images/banner.jpg';
$hasBanner = is_file($studentBannerFsPath) || is_file($defaultBannerFsPath);
$bannerSrc = is_file($studentBannerFsPath) ? ((defined('BASE_URL') ? BASE_URL : '/') . 'assets/images/student-banner.jpg') : BANNER_PATH;
$recordings = $recordings ?? [];
$studentTimezone = resolveUserTimezone(Auth::user() ?: null, APP_TIMEZONE);
?>

<div class="row g-3">
    <div class="col-12">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h4 mb-0">Student Dashboard</h1>
                <p class="text-muted mb-0 small">Your classes and approved recordings.</p>
            </div>
            <span class="badge text-bg-success text-uppercase">Student</span>
        </div>
    </div>


    <div class="col-12 col-lg-4">
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase mb-3">Assigned Teacher</h2>
                <?php $assignedTeachers = $assignedTeachers ?? []; ?>
                <?php if ($assignedTeachers === []): ?>
                    <p class="text-muted small mb-0">No teacher has been assigned yet.</p>
                <?php else: ?>
                    <?php foreach ($assignedTeachers as $teacher): ?>
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="fw-semibold"><?= h((string) ($teacher['name'] ?? '')) ?></div>
                            <div class="small text-muted">Subject: <?= h((string) ($teacher['subject'] ?? '—')) ?></div>
                            <div class="small text-muted">Class: <?= h((string) ($teacher['class_name'] ?? '—')) ?></div>
                            <div class="small"><a href="mailto:<?= h((string) ($teacher['email'] ?? '')) ?>"><?= h((string) ($teacher['email'] ?? '')) ?></a></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="banner-card mb-3">
            <?php if ($hasBanner): ?>
                <img src="<?= h($bannerSrc) ?>" alt="Student learning banner">
            <?php else: ?>
                <div class="student-dashboard-banner-fallback"></div>
            <?php endif; ?>
            <div class="overlay">
                <h2>Welcome Back</h2>
                <p>Continue your learning journey</p>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase mb-3">Completed Classes</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Title</th>
                            <th>Ended</th>
                            <th>Duration</th>
                            <th>Recording</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($completedClasses)): ?>
                            <tr><td colspan="4" class="text-muted small">No completed classes yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($completedClasses as $done): ?>
                                <?php $recordingStatus = recordingSyncStatusForRow($done); ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= h((string) ($done['title'] ?? '')) ?></div>
                                        <?php if (classActualStartUtcValue($done) !== null): ?>
                                            <div class="small text-muted">Started: <?= h(formatClassActualAt($done, 'start', $studentTimezone)) ?></div>
                                            <div class="small text-muted"><?= h(formatClassActualTimezoneLabel($done, $studentTimezone)) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?php if (classActualEndUtcValue($done) !== null): ?>
                                            <div><?= h(formatClassActualAt($done, 'end', $studentTimezone)) ?></div>
                                            <div class="text-muted"><?= h(formatClassActualTimezoneLabel($done, $studentTimezone)) ?></div>
                                        <?php else: ?>
                                            <div class="text-muted">Waiting for actual Meet end time</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= h(ClassSession::formatActualDuration($done)) ?></td>
                                    <td>
                                        <?php if (!empty($done['recording_url']) && (string) ($done['visible_to_student'] ?? 'no') === 'yes'): ?>
                                            <a href="<?= h((string) $done['recording_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">View Recording</a>
                                        <?php elseif ($recordingStatus === 'processing' || $recordingStatus === 'failed'): ?>
                                            <span class="text-muted small"><?= h(recordingSyncStatusText($done)) ?></span>
                                        <?php elseif ($recordingStatus === 'disabled'): ?>
                                            <span class="text-muted small">Recording disabled.</span>
                                        <?php else: ?>
                                            <span class="text-muted small">Recording not available.</span>
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

    <div class="col-12 col-lg-4">

        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase mb-3">Approved Recordings</h2>
                <div class="row g-3">
                    <?php if ($recordings === []): ?>
                        <div class="col-12"><p class="text-muted small mb-0">No recordings are available for you yet.</p></div>
                    <?php else: ?>
                        <?php foreach ($recordings as $recording): ?>
                            <div class="col-12">
                                <div class="recording-meta-card p-3">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <div>
                                            <div class="fw-semibold"><?= h((string) ($recording['recording_title'] ?? $recording['class_title'] ?? 'Recording')) ?></div>
                                            <div class="small text-muted"><?= h((string) ($recording['teacher_name'] ?? 'Teacher')) ?></div>
                                        </div>
                                        <i class="fa-solid fa-video text-primary"></i>
                                    </div>
                                    <?php if (classActualStartUtcValue($recording) !== null || classActualEndUtcValue($recording) !== null): ?>
                                        <?php if (classActualStartUtcValue($recording) !== null): ?><div class="small text-muted mb-1">Started: <?= h(formatClassActualAt($recording, 'start', $studentTimezone)) ?></div><?php endif; ?>
                                        <?php if (classActualEndUtcValue($recording) !== null): ?><div class="small text-muted mb-1">Ended: <?= h(formatClassActualAt($recording, 'end', $studentTimezone)) ?></div><?php endif; ?>
                                        <div class="small text-muted mb-1"><?= h(formatClassActualTimezoneLabel($recording, $studentTimezone)) ?></div>
                                    <?php endif; ?>
                                    <div class="small text-muted mb-1">Class duration: <?= h(formatDurationMinutes(classActualDurationMinutes($recording))) ?></div>
                                    <div class="small text-muted mb-3">Recording runtime: <?= h(((int) ($recording['recording_duration'] ?? 0) > 0 ? (int) $recording['recording_duration'] . ' min' : 'Unknown')) ?></div>
                                    <a href="<?= h((string) ($recording['recording_url'] ?? '')) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">View Recording</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

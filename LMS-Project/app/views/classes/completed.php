<?php

use function htmlspecialchars as h;

$viewerTimezone = resolveUserTimezone(Auth::user() ?: null, APP_TIMEZONE);
?>

<div class="classes-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Completed Classes</h1>
            <p class="text-muted small mb-0">History of all completed sessions.</p>
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
                        <th>Scheduled Start</th>
                        <th>Actual Timing</th>
                        <th>Completed At</th>
                        <th>Duration</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($classes)): ?>
                        <tr><td colspan="7" class="text-muted small">No completed classes yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($classes as $cls): ?>
                            <tr>
                                <td><?= h($cls['title']) ?></td>
                                <td><?= h($cls['teacher_name']) ?></td>
                                <td>
                                    <div><?= h(formatUtcForTimezone(classStartUtcValue($cls), $viewerTimezone, 'd M Y h:i A T')) ?></div>
                                    <div class="small text-muted"><?= h(formatClassScheduledAt($cls, 'd M Y h:i A T')) ?></div>
                                    <div class="small text-muted"><?= h(formatClassScheduledTimezoneLabel($cls)) ?></div>
                                </td>
                                <td>
                                    <?php if (classActualStartUtcValue($cls) !== null): ?>
                                        <div class="small text-muted">Started: <?= h(formatClassActualAt($cls, 'start', $viewerTimezone)) ?></div>
                                    <?php endif; ?>
                                    <?php if (classActualEndUtcValue($cls) !== null): ?>
                                        <div class="small text-muted">Ended: <?= h(formatClassActualAt($cls, 'end', $viewerTimezone)) ?></div>
                                    <?php endif; ?>
                                    <?php if (classActualStartUtcValue($cls) !== null || classActualEndUtcValue($cls) !== null): ?>
                                        <div class="small text-muted"><?= h(formatClassActualTimezoneLabel($cls, $viewerTimezone)) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted small">No actual meeting activity captured.</span>
                                    <?php endif; ?>
                                    <?= teacherLateJoinNoticeHtml($cls) ?>
                                </td>
                                <td><?= h(formatUtcForTimezone((string) ($cls['completed_at'] ?? ''), $viewerTimezone, 'd M Y h:i A T')) ?></td>
                                <td><?= h(ClassSession::formatActualDuration($cls)) ?></td>
                                <td><span class="badge text-bg-success text-uppercase"><?= h($cls['status']) ?></span><?= teacherLateJoinBadgeHtml($cls) ?></td>
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

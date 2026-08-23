<?php

use function htmlspecialchars as h;

$viewerTimezone = resolveUserTimezone(Auth::user() ?: null, APP_TIMEZONE);
$filters = $filters ?? ['teacher_id' => 0, 'date_from' => '', 'date_to' => '', 'q' => ''];
$teachers = $teachers ?? [];
?>

<div class="classes-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Completed Classes</h1>
            <p class="text-muted small mb-0">History of all completed sessions.</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="get" action="<?= h(path('classes/completed')) ?>" class="row g-2 align-items-end no-app-loader">
                <div class="col-12 col-sm-4 col-md-3">
                    <label for="q" class="form-label form-label-sm fw-semibold mb-1">Search</label>
                    <input type="text" name="q" id="q" class="form-control form-control-sm" placeholder="Title or teacher..." value="<?= h((string) ($filters['q'] ?? '')) ?>">
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <label for="teacher_id" class="form-label form-label-sm fw-semibold mb-1">Teacher</label>
                    <select name="teacher_id" id="teacher_id" class="form-select form-select-sm">
                        <option value="">All Teachers</option>
                        <?php foreach ($teachers as $tc): ?>
                            <option value="<?= (int) $tc['id'] ?>" <?= (int) ($filters['teacher_id'] ?? 0) === (int) $tc['id'] ? 'selected' : '' ?>>
                                <?= h((string) $tc['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-sm-4 col-md-2">
                    <label for="date_from" class="form-label form-label-sm fw-semibold mb-1">From Date</label>
                    <input type="date" name="date_from" id="date_from" class="form-control form-control-sm" value="<?= h((string) ($filters['date_from'] ?? '')) ?>">
                </div>
                <div class="col-6 col-sm-4 col-md-2">
                    <label for="date_to" class="form-label form-label-sm fw-semibold mb-1">To Date</label>
                    <input type="date" name="date_to" id="date_to" class="form-control form-control-sm" value="<?= h((string) ($filters['date_to'] ?? '')) ?>">
                </div>
                <div class="col-12 col-md-auto ms-auto d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fa-solid fa-magnifying-glass me-1"></i>Filter
                    </button>
                    <a href="<?= h(path('classes/completed')) ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
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

<?php

use function htmlspecialchars as h;

$base = appWebPath();
$class = $class ?? [];
$displayTimezone = $displayTimezone ?? APP_TIMEZONE;
$startLabel = formatUtcForTimezone(classStartUtcValue($class), $displayTimezone, 'd M Y h:i A T');
$endLabel = formatUtcForTimezone(classEndUtcValue($class), $displayTimezone, 'd M Y h:i A T');
?>

<div class="row justify-content-center" data-auto-refresh-seconds="15">
    <div class="col-12 col-xl-7">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                    <div>
                        <h1 class="h4 mb-1"><?= h((string) ($class['title'] ?? 'Class')) ?></h1>
                        <p class="text-muted mb-0 small">Waiting for the teacher to open Google Meet.</p>
                    </div>
                    <span class="badge rounded-pill text-bg-warning px-3 py-2">Waiting Room</span>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                            <div class="small text-muted text-uppercase mb-1">Your Timezone</div>
                            <div class="fw-semibold"><?= h($displayTimezone) ?></div>
                            <div class="small text-muted"><?= h($startLabel) ?> to <?= h($endLabel) ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                            <div class="small text-muted text-uppercase mb-1">Meet Flow</div>
                            <div class="fw-semibold">Teacher joins first</div>
                            <div class="small text-muted">You will be redirected automatically when the teacher opens class.</div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($earlyNotice)): ?>
                    <div class="alert alert-warning mb-0">
                        <i class="fa-solid fa-clock me-2"></i><?= h($earlyNotice) ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        LearnWise checks Google Meet for the teacher host join in real time. Once the teacher is in the meeting, you will be redirected automatically. Use the same Google account email that is registered in this LMS.
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= h($base . '/join-class?class_id=' . (int) ($class['id'] ?? 0)) ?>" class="btn btn-primary">Check Again</a>
                    <a href="<?= h($base . '/student') ?>" class="btn btn-outline-secondary">Back to Dashboard</a>
                </div>

                <p class="text-muted small mt-3 mb-0">This page refreshes every 15 seconds while you wait.</p>
            </div>
        </div>
    </div>
</div>

<?php

use function htmlspecialchars as h;

$base = appWebPath();
$class = $class ?? [];
$errors = $errors ?? [];

$durationMinutes = 60;
if (!empty($class['start_datetime']) && !empty($class['end_datetime'])) {
    try {
        $start = new DateTimeImmutable((string) $class['start_datetime'], new DateTimeZone('UTC'));
        $end = new DateTimeImmutable((string) $class['end_datetime'], new DateTimeZone('UTC'));
        $durationMinutes = max(1, (int) round(($end->getTimestamp() - $start->getTimestamp()) / 60));
    } catch (Throwable $e) {
        $durationMinutes = 60;
    }
}

$startValue = '';
if (!empty($class['start_datetime'])) {
    try {
        $tz = new DateTimeZone(classScheduledTimezone($class, APP_TIMEZONE));
        $startValue = (new DateTimeImmutable((string) classStartUtcValue($class), new DateTimeZone('UTC')))
            ->setTimezone($tz)
            ->format('Y-m-d\TH:i');
    } catch (Throwable $e) {
        $startValue = '';
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Edit Class</h1>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger small"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= h((string) $error) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>

                <form method="post" action="<?= h($base . '/classes/update') ?>">
                    <input type="hidden" name="class_id" value="<?= (int) ($class['id'] ?? 0) ?>">

                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" value="<?= h((string) ($class['title'] ?? '')) ?>" disabled>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="start_datetime">Date &amp; Time</label>
                            <input type="datetime-local" class="form-control" name="start_datetime" id="start_datetime" value="<?= h($startValue) ?>" required>
                            <div class="form-text">Editing in <?= h(classScheduledTimezone($class, APP_TIMEZONE)) ?>. Saved back to UTC.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="duration_minutes">Duration (minutes)</label>
                            <input type="number" class="form-control" name="duration_minutes" id="duration_minutes" min="1" value="<?= (int) $durationMinutes ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="payout_amount">Payout Amount (INR)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="payout_amount" id="payout_amount" value="<?= h((string) ($class['payout_amount'] ?? '0')) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="student_fee">Student Fee (INR)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="student_fee" id="student_fee" value="<?= h((string) ($class['student_fee'] ?? '0')) ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="meeting_link">Meeting Link (optional)</label>
                            <input type="url" class="form-control" name="meeting_link" id="meeting_link" value="<?= h((string) ($class['meeting_link'] ?? '')) ?>">
                        </div>
                    </div>

                    <?php if (!empty($isRecurringSeries)): ?>
                        <div class="mb-3 border rounded p-3 bg-light">
                            <label class="form-label fw-semibold">Apply changes to</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="edit_scope" id="edit_scope_current" value="current" checked>
                                <label class="form-check-label" for="edit_scope_current">This occurrence only</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="edit_scope" id="edit_scope_all" value="all_future">
                                <label class="form-check-label" for="edit_scope_all">This and future occurrences</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="edit_scope" id="edit_scope_series" value="entire_series">
                                <label class="form-check-label" for="edit_scope_series">Entire series</label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="<?= h($base . '/classes') ?>" class="btn btn-link">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

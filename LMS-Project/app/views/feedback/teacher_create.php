<?php

use function htmlspecialchars as h;

$base = defined('BASE_PATH') ? BASE_PATH : '';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-2">Feedback for <?= h($studentName ?? 'Student') ?></h1>
                <p class="text-muted small">Completed classes together: <?= (int)($completedCount ?? 0) ?></p>
                <form method="post" action="<?= h($base . '/teacher/feedback') ?>">
                    <input type="hidden" name="student_id" value="<?= (int)($studentId ?? 0) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="rating">Rating (1–5)</label>
                        <select class="form-select" name="rating" id="rating">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="comments">Comments</label>
                        <textarea class="form-control" name="comments" id="comments" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save feedback</button>
                    <a href="<?= h($base . '/teacher/feedback') ?>" class="btn btn-link">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

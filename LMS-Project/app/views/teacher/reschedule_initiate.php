<?php

use function htmlspecialchars as h;

$base = defined('BASE_PATH') ? BASE_PATH : '';
$isAdmin = !empty($isAdmin);
$postUrl = $isAdmin ? '/admin/reschedule/new' : '/teacher/reschedule/new';
$backUrl = $isAdmin ? '/admin/reschedule' : '/teacher/reschedule';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Reschedule Class</h1>
                <form method="post" action="<?= h($base . $postUrl) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="class_student">Class &amp; student</label>
                        <select class="form-select" name="class_student" id="class_student" required>
                            <option value="">Select</option>
                            <?php foreach ($enrollmentRows ?? [] as $row): ?>
                                <option value="<?= (int)$row['class_id'] . ':' . (int)$row['student_id'] . ':' . (int)($row['teacher_id'] ?? 0) ?>">
                                    <?= h($row['class_title']) ?> — <?= h($row['student_name']) ?><?= $isAdmin ? (' (Teacher: ' . h((string)($row['teacher_name'] ?? '')) . ')') : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="requested_date">Proposed date</label>
                            <input type="date" class="form-control" name="requested_date" id="requested_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="requested_time">Proposed time</label>
                            <input type="time" class="form-control" name="requested_time" id="requested_time" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="reason">Note</label>
                        <textarea class="form-control" name="reason" id="reason" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Reschedule Now</button>
                    <a href="<?= h($base . $backUrl) ?>" class="btn btn-link">Back</a>
                </form>
            </div>
        </div>
    </div>
</div>

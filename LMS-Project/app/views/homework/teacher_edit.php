<?php

use function htmlspecialchars as h;

$base = defined('BASE_PATH') ? BASE_PATH : '';
$h = $homework ?? [];
$students = $students ?? [];
$assignedStudentIds = $assignedStudentIds ?? [];
$attachments = $attachments ?? [];
$errors = $errors ?? [];

$dueValue = '';
if (!empty($h['due_date'])) {
    try {
        $dueValue = (new DateTime((string) $h['due_date'], new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone(APP_TIMEZONE))
            ->format('Y-m-d\\TH:i');
    } catch (Throwable $e) {
        $dueValue = '';
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-9">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-2">Edit Homework</h1>
                <p class="text-muted small">Teacher: <strong><?= h((string) ($h['teacher_name'] ?? '')) ?></strong></p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger small">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= h((string) $error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= h($base . '/teacher/homework/update') ?>" enctype="multipart/form-data">
                    <input type="hidden" name="homework_id" value="<?= (int) ($h['id'] ?? 0) ?>">

                    <div class="mb-3">
                        <label class="form-label" for="title">Title</label>
                        <input type="text" class="form-control" name="title" id="title" required
                               value="<?= h((string) ($h['title'] ?? '')) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="4"><?= h((string) ($h['description'] ?? '')) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="due_date">Due date (optional)</label>
                        <input type="datetime-local" class="form-control" name="due_date" id="due_date" value="<?= h($dueValue) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="student_ids">Assigned students</label>
                        <select id="student_ids" name="student_ids[]" class="form-select" multiple size="8" required>
                            <?php foreach ($students as $s): ?>
                                <option value="<?= (int) ($s['id'] ?? 0) ?>" <?= in_array((int) ($s['id'] ?? 0), array_map('intval', $assignedStudentIds), true) ? 'selected' : '' ?>>
                                    <?= h((string) ($s['name'] ?? '')) ?> (<?= h((string) ($s['email'] ?? '')) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Existing attachments</label>
                        <?php if (empty($attachments)): ?>
                            <div class="text-muted small">No attachments.</div>
                        <?php else: ?>
                            <?php foreach ($attachments as $a): ?>
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" value="<?= (int) ($a['id'] ?? 0) ?>" id="remove_attach_<?= (int) ($a['id'] ?? 0) ?>" name="remove_attachment_ids[]">
                                    <label class="form-check-label" for="remove_attach_<?= (int) ($a['id'] ?? 0) ?>">
                                        Remove <?= h((string) ($a['file_name'] ?? 'file')) ?>
                                        (<a href="<?= h($base . '/homework/download?kind=attachment&id=' . (int) ($a['id'] ?? 0)) ?>">download</a>)
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="attachments">Add attachments</label>
                        <input type="file" class="form-control" name="attachments[]" id="attachments" multiple>
                    </div>

                    <button type="submit" class="btn btn-primary">Update homework</button>
                    <a href="<?= h($base . '/teacher/homework') ?>" class="btn btn-link">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

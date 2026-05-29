<?php

use function htmlspecialchars as h;

$base = defined('BASE_PATH') ? BASE_PATH : '';
$homework = $homework ?? [];
$students = $students ?? [];
$assignedStudentIds = array_map('intval', (array) ($assignedStudentIds ?? []));
$attachments = $attachments ?? [];
$errors = $errors ?? [];
$dueTimezoneValue = homeworkDueTimezone($homework, APP_TIMEZONE);
$dueValue = '';
if (!empty($homework['due_date'])) {
    $dueValue = formatUtcForTimezone((string) $homework['due_date'], $dueTimezoneValue, 'Y-m-d\TH:i');
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-9">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-2">Edit Homework</h1>
                <p class="text-muted small">Teacher: <strong><?= h((string) ($homework['teacher_name'] ?? '')) ?></strong></p>

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
                    <input type="hidden" name="homework_id" value="<?= (int) ($homework['id'] ?? 0) ?>">

                    <div class="mb-3">
                        <label class="form-label" for="title">Title</label>
                        <input type="text" class="form-control" name="title" id="title" required value="<?= h((string) ($homework['title'] ?? '')) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="4"><?= h((string) ($homework['description'] ?? '')) ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <label class="form-label" for="due_date">Due date (optional)</label>
                            <input type="datetime-local" class="form-control" name="due_date" id="due_date" value="<?= h($dueValue) ?>">
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label" for="due_timezone">Due timezone</label>
                            <select class="form-select" name="due_timezone" id="due_timezone">
                                <?php foreach (supportedSchedulingTimezones() as $timezone): ?>
                                    <option value="<?= h($timezone['value']) ?>" <?= $dueTimezoneValue === $timezone['value'] ? 'selected' : '' ?>><?= h($timezone['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="student_ids">Assigned students</label>
                        <select id="student_ids" name="student_ids[]" class="form-select" multiple size="8" required>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= (int) ($student['id'] ?? 0) ?>" <?= in_array((int) ($student['id'] ?? 0), $assignedStudentIds, true) ? 'selected' : '' ?>>
                                    <?= h((string) ($student['name'] ?? '')) ?> (<?= h((string) ($student['email'] ?? '')) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Existing attachments</label>
                        <?php if (empty($attachments)): ?>
                            <div class="text-muted small">No attachments.</div>
                        <?php else: ?>
                            <?php foreach ($attachments as $attachment): ?>
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" value="<?= (int) ($attachment['id'] ?? 0) ?>" id="remove_attach_<?= (int) ($attachment['id'] ?? 0) ?>" name="remove_attachment_ids[]">
                                    <label class="form-check-label" for="remove_attach_<?= (int) ($attachment['id'] ?? 0) ?>">
                                        Remove <?= h((string) ($attachment['file_name'] ?? 'file')) ?>
                                        (<a href="<?= h($base . '/homework/download?kind=attachment&id=' . (int) ($attachment['id'] ?? 0)) ?>">download</a>)
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

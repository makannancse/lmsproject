<?php

use function htmlspecialchars as h;

$base = appWebPath();
$homework = $homework ?? [];
$attachments = $attachments ?? [];
$assignedStudents = $assignedStudents ?? [];
?>

<div class="row g-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h4 mb-0">Homework Details</h1>
            <p class="text-muted small mb-0">Created by <?= h((string) ($homework['teacher_name'] ?? 'Teacher')) ?></p>
        </div>
        <a href="<?= h($base . '/teacher/homework') ?>" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="col-12">
        <div class="card shadow-sm"><div class="card-body">
            <h5 class="mb-2"><?= h((string) ($homework['title'] ?? '')) ?></h5>
            <div class="small text-muted mb-2">
                <?php if (!empty($homework['due_date'])): ?>
                    Due: <?= h(formatHomeworkDueAt($homework, 'd M Y h:i A T')) ?> | <?= h(formatHomeworkDueTimezoneLabel($homework)) ?>
                <?php else: ?>
                    Due: Not set
                <?php endif; ?>
                | Status: <?= h((string) ($homework['status'] ?? 'pending')) ?>
            </div>
            <p><?= nl2br(h((string) ($homework['description'] ?? ''))) ?></p>
        </div></div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card shadow-sm"><div class="card-body">
            <h6>Attachments</h6>
            <?php if (empty($attachments)): ?>
                <div class="text-muted small">No files.</div>
            <?php else: ?>
                <?php foreach ($attachments as $attachment): ?>
                    <a class="btn btn-sm btn-outline-primary mb-1" href="<?= h($base . '/homework/download?kind=attachment&id=' . (int) ($attachment['id'] ?? 0)) ?>"><?= h((string) ($attachment['file_name'] ?? 'file')) ?></a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div></div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card shadow-sm"><div class="card-body">
            <h6>Assigned Students</h6>
            <?php if (empty($assignedStudents)): ?>
                <div class="text-muted small">No students assigned.</div>
            <?php else: ?>
                <ul class="small mb-0">
                    <?php foreach ($assignedStudents as $student): ?>
                        <li><?= h((string) ($student['name'] ?? '')) ?> (<?= h((string) ($student['email'] ?? '')) ?>)</li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div></div>
    </div>
</div>

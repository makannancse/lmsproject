<?php

use function htmlspecialchars as h;

$base = defined('BASE_PATH') ? BASE_PATH : '';
$h = $homework ?? [];
$attachments = $attachments ?? [];
$assignedStudents = $assignedStudents ?? [];
?>

<div class="row g-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h4 mb-0">Homework Details</h1>
            <p class="text-muted small mb-0">Created by <?= h((string) ($h['teacher_name'] ?? 'Teacher')) ?></p>
        </div>
        <a href="<?= h($base . '/teacher/homework') ?>" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="col-12">
        <div class="card shadow-sm"><div class="card-body">
            <h5 class="mb-2"><?= h((string) ($h['title'] ?? '')) ?></h5>
            <div class="small text-muted mb-2">Due: <?= h((string) ($h['due_date'] ?? '—')) ?> | Status: <?= h((string) ($h['status'] ?? 'pending')) ?></div>
            <p><?= nl2br(h((string) ($h['description'] ?? ''))) ?></p>
        </div></div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card shadow-sm"><div class="card-body">
            <h6>Attachments</h6>
            <?php if (empty($attachments)): ?>
                <div class="text-muted small">No files.</div>
            <?php else: ?>
                <?php foreach ($attachments as $a): ?>
                    <a class="btn btn-sm btn-outline-primary mb-1" href="<?= h($base . '/homework/download?kind=attachment&id=' . (int) ($a['id'] ?? 0)) ?>"><?= h((string) ($a['file_name'] ?? 'file')) ?></a>
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
                    <?php foreach ($assignedStudents as $s): ?>
                        <li><?= h((string) ($s['name'] ?? '')) ?> (<?= h((string) ($s['email'] ?? '')) ?>)</li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div></div>
    </div>
</div>

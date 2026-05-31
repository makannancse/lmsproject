<?php

use function htmlspecialchars as h;

$base = defined('BASE_PATH') ? BASE_PATH : '';
$h = $homework ?? [];
$submissions = $submissions ?? [];
?>

<div class="row g-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h4 mb-0">Homework Submissions</h1>
            <p class="text-muted small mb-0">
                <?= h((string) ($h['title'] ?? '')) ?>
            </p>
        </div>
        <a href="<?= h($base . '/teacher/homework') ?>" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Submitted at</th>
                            <th>File</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($submissions)): ?>
                            <tr><td colspan="4" class="text-muted small p-3">No submissions yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($submissions as $s): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= h((string) ($s['student_name'] ?? '')) ?></div>
                                        <div class="small text-muted"><?= h((string) ($s['student_email'] ?? '')) ?></div>
                                    </td>
                                    <td class="small"><?= h((string) ($s['submitted_at'] ?? $s['uploaded_at'] ?? '')) ?></td>
                                    <td><?= h((string) ($s['file_name'] ?? 'submission')) ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="<?= h($base . '/homework/download?kind=submission&id=' . (int) ($s['id'] ?? 0)) ?>">Download</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

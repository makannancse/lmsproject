<?php

use function htmlspecialchars as h;

$base = appWebPath();
$items = $items ?? [];
$attachmentsByHomework = $attachmentsByHomework ?? [];
$filters = $filters ?? ['status' => '', 'date_from' => '', 'date_to' => '', 'q' => ''];
?>

<div class="row g-3">
    <div class="col-12">
        <h1 class="h4 mb-0">My Homework</h1>
        <p class="text-muted small mb-0">Homework assigned directly by teachers.</p>
    </div>

    <!-- Filter Bar -->
    <div class="col-12">
        <div class="card shadow-sm mb-1">
            <div class="card-body py-2">
                <form method="get" action="<?= h($base . '/student/homework') ?>" class="row g-2 align-items-end no-app-loader">
                    <div class="col-12 col-sm-4 col-md-4">
                        <label for="q" class="form-label form-label-sm fw-semibold mb-1">Search</label>
                        <input type="text" name="q" id="q" class="form-control form-control-sm" placeholder="Title, description, teacher..." value="<?= h((string) ($filters['q'] ?? '')) ?>">
                    </div>
                    <div class="col-6 col-sm-3 col-md-2">
                        <label for="status" class="form-label form-label-sm fw-semibold mb-1">Status</label>
                        <select name="status" id="status" class="form-select form-select-sm">
                            <option value="" <?= ($filters['status'] ?? '') === '' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="completed" <?= ($filters['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-6 col-sm-3 col-md-2">
                        <label for="date_from" class="form-label form-label-sm fw-semibold mb-1">From Date</label>
                        <input type="date" name="date_from" id="date_from" class="form-control form-control-sm" value="<?= h((string) ($filters['date_from'] ?? '')) ?>">
                    </div>
                    <div class="col-6 col-sm-3 col-md-2">
                        <label for="date_to" class="form-label form-label-sm fw-semibold mb-1">To Date</label>
                        <input type="date" name="date_to" id="date_to" class="form-control form-control-sm" value="<?= h((string) ($filters['date_to'] ?? '')) ?>">
                    </div>
                    <div class="col-12 col-md-auto ms-auto d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>Filter
                        </button>
                        <a href="<?= h($base . '/student/homework') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
        <div class="card shadow-sm"><div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                    <tr><th>Teacher</th><th>Title</th><th>Due</th><th>Status</th><th>Attachments</th><th>Your submissions</th><th>Upload</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="7" class="text-muted small p-3">No homework assigned.</td></tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <?php $homeworkId = (int) ($item['id'] ?? 0); ?>
                            <tr>
                                <td><?= h((string) ($item['teacher_name'] ?? '')) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= h((string) ($item['title'] ?? '')) ?></div>
                                    <?php if (!empty($item['description'])): ?><div class="small text-muted"><?= nl2br(h((string) $item['description'])) ?></div><?php endif; ?>
                                </td>
                                <td class="small">
                                    <?php if (!empty($item['due_date'])): ?>
                                        <div><?= h(formatHomeworkDueAt($item, 'd M Y h:i A T')) ?></div>
                                        <div class="text-muted"><?= h(formatHomeworkDueTimezoneLabel($item)) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge text-bg-<?= (($item['status'] ?? 'pending') === 'completed') ? 'success' : 'secondary' ?>"><?= h((string) ($item['status'] ?? 'pending')) ?></span></td>
                                <td>
                                    <?php foreach (($attachmentsByHomework[$homeworkId] ?? []) as $attachment): ?>
                                        <a class="btn btn-sm btn-outline-primary mb-1" href="<?= h($base . '/homework/download?kind=attachment&id=' . (int) ($attachment['id'] ?? 0)) ?>">Download</a>
                                    <?php endforeach; ?>
                                    <?php if (empty($attachmentsByHomework[$homeworkId] ?? [])): ?><span class="text-muted small">No files</span><?php endif; ?>
                                </td>
                                <td>
                                    <?php foreach (($submissionsByHomework[$homeworkId] ?? []) as $submission): ?>
                                        <a class="btn btn-sm btn-outline-secondary mb-1" href="<?= h($base . '/homework/download?kind=submission&id=' . (int) ($submission['id'] ?? 0)) ?>"><?= h((string) ($submission['file_name'] ?? 'submission')) ?></a>
                                    <?php endforeach; ?>
                                    <?php if (empty($submissionsByHomework[$homeworkId] ?? [])): ?><span class="text-muted small">Not submitted</span><?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" action="<?= h($base . '/student/homework/upload') ?>" enctype="multipart/form-data" class="d-grid gap-1">
                                        <input type="hidden" name="homework_id" value="<?= $homeworkId ?>">
                                        <input type="file" name="files[]" class="form-control form-control-sm" multiple required>
                                        <button type="submit" class="btn btn-sm btn-primary">Upload</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
        <?php renderPagination($pagination ?? null, $queryParams ?? []); ?>
    </div>
</div>

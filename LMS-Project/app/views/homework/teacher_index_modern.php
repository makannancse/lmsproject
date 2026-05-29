<?php

use function htmlspecialchars as h;

$base = defined('BASE_PATH') ? BASE_PATH : '';
$homeworks = $homeworks ?? [];
$attachmentsByHomework = $attachmentsByHomework ?? [];
$isAdmin = !empty($isAdmin);
?>

<div class="row g-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h4 mb-0">Homework Management</h1>
            <p class="text-muted small mb-0">Independent homework assignment and submission review.</p>
        </div>
        <a href="<?= h($base . '/teacher/homework/create') ?>" class="btn btn-primary btn-sm">Assign Homework</a>
    </div>

    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Due</th>
                            <?php if ($isAdmin): ?><th>Teacher</th><?php endif; ?>
                            <th>Status</th>
                            <th>Assigned</th>
                            <th>Submitted</th>
                            <th>Attachments</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($homeworks)): ?>
                            <tr><td colspan="<?= $isAdmin ? 8 : 7 ?>" class="text-muted small p-3">No homework assigned yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($homeworks as $h): ?>
                                <?php $homeworkId = (int) ($h['id'] ?? 0); ?>
                                <tr>
                                    <td><?= h((string) ($h['title'] ?? '')) ?></td>
                                    <td class="small">
                                        <?php if (!empty($h['due_date'])): ?>
                                            <div><?= h(formatHomeworkDueAt($h, 'd M Y h:i A T')) ?></div>
                                            <div class="text-muted"><?= h(formatHomeworkDueTimezoneLabel($h)) ?></div>
                                        <?php else: ?>
                                            <span class="text-muted">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($isAdmin): ?><td><?= h((string) ($h['teacher_name'] ?? '')) ?></td><?php endif; ?>
                                    <td><span class="badge text-bg-<?= (($h['status'] ?? 'pending') === 'completed') ? 'success' : 'secondary' ?>"><?= h((string) ($h['status'] ?? 'pending')) ?></span></td>
                                    <td><?= (int) ($h['assigned_count'] ?? 0) ?></td>
                                    <td><?= (int) ($h['submitted_count'] ?? 0) ?></td>
                                    <td>
                                        <?php foreach (($attachmentsByHomework[$homeworkId] ?? []) as $attachment): ?>
                                            <a class="btn btn-sm btn-outline-primary mb-1" href="<?= h($base . '/homework/download?kind=attachment&id=' . (int) ($attachment['id'] ?? 0)) ?>">File</a>
                                        <?php endforeach; ?>
                                        <?php if (empty($attachmentsByHomework[$homeworkId] ?? [])): ?><span class="text-muted small">No files</span><?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= h($base . '/teacher/homework/view?homework_id=' . $homeworkId) ?>">View</a>
                                        <a class="btn btn-sm btn-outline-primary" href="<?= h($base . '/teacher/homework/submissions?homework_id=' . $homeworkId) ?>">Submissions</a>
                                        <a class="btn btn-sm btn-outline-dark" href="<?= h($base . '/teacher/homework/edit?homework_id=' . $homeworkId) ?>">Edit</a>
                                        <?php if (($h['status'] ?? 'pending') !== 'completed'): ?>
                                            <form method="post" action="<?= h($base . '/teacher/homework/complete') ?>" class="d-inline">
                                                <input type="hidden" name="homework_id" value="<?= $homeworkId ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success">Mark Completed</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" action="<?= h($base . '/teacher/homework/delete') ?>" class="d-inline"
                                              data-confirm="1"
                                              data-confirm-title="Delete homework?"
                                              data-confirm-text="This will remove the homework, attachments, and related submissions."
                                              data-confirm-button="Delete">
                                            <input type="hidden" name="homework_id" value="<?= $homeworkId ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
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

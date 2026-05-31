<?php

use function htmlspecialchars as h;

$base = defined('BASE_PATH') ? BASE_PATH : '';
$requests = $requests ?? [];
$canManageAll = !empty($canManageAll);
?>

<div class="row g-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h4 mb-0">Reschedule Requests</h1>
            <p class="text-muted small mb-0">Track, approve, reject, and manage class reschedules.</p>
        </div>
        <a href="<?= h($base . ($canManageAll ? '/admin/reschedule/new' : '/teacher/reschedule/new')) ?>" class="btn btn-primary btn-sm">Reschedule Class</a>
    </div>

    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Current Schedule</th>
                            <th>Requested Date/Time</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($requests)): ?>
                            <tr><td colspan="7" class="text-muted p-3">No reschedule requests found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($requests as $r): ?>
                                <?php
                                $st = (string) ($r['status'] ?? 'pending');
                                $badge = 'bg-warning text-dark';
                                if ($st === 'approved') {
                                    $badge = 'bg-success';
                                } elseif ($st === 'rejected') {
                                    $badge = 'bg-danger';
                                }
                                $decideUrl = $canManageAll ? '/admin/reschedule/decide' : '/teacher/reschedule/decide';
                                ?>
                                <tr>
                                    <td><?= h((string) ($r['student_name'] ?? '')) ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= h((string) ($r['class_title'] ?? '')) ?></div>
                                        <?php if ($canManageAll): ?><div class="small text-muted">Teacher: <?= h((string) ($r['teacher_name'] ?? '')) ?></div><?php endif; ?>
                                    </td>
                                    <td><?= h((string) ($r['start_datetime'] ?? '—')) ?></td>
                                    <td><?= h((string) (($r['requested_date'] ?? '') . ' ' . ($r['requested_time'] ?? ''))) ?></td>
                                    <td class="small"><?= h((string) ($r['reason'] ?? '—')) ?></td>
                                    <td><span class="badge <?= $badge ?> text-capitalize"><?= h($st) ?></span></td>
                                    <td>
                                        <?php if ($st === 'pending'): ?>
                                            <form method="post" action="<?= h($base . $decideUrl) ?>" class="d-inline">
                                                <input type="hidden" name="request_id" value="<?= (int) ($r['id'] ?? 0) ?>">
                                                <input type="hidden" name="decision" value="approved">
                                                <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                            </form>
                                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rej<?= (int) ($r['id'] ?? 0) ?>">Reject</button>
                                            <div class="modal fade" id="rej<?= (int) ($r['id'] ?? 0) ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <form method="post" action="<?= h($base . $decideUrl) ?>">
                                                        <div class="modal-content">
                                                            <div class="modal-header"><h5 class="modal-title">Reject Request</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="request_id" value="<?= (int) ($r['id'] ?? 0) ?>">
                                                                <input type="hidden" name="decision" value="rejected">
                                                                <label class="form-label">Comment</label>
                                                                <textarea class="form-control" name="<?= $canManageAll ? 'admin_comment' : 'teacher_comment' ?>" rows="3" placeholder="Optional comment"></textarea>
                                                            </div>
                                                            <div class="modal-footer"><button type="submit" class="btn btn-danger">Reject</button></div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">Decision saved</span>
                                        <?php endif; ?>
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

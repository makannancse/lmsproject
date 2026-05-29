<?php

use function htmlspecialchars as h;

$base = defined('BASE_PATH') ? BASE_PATH : '';
?>

<div class="row g-3">
    <div class="col-12">
        <h1 class="h4">Reschedule</h1>
        <p class="text-muted small">Request a new date/time for an upcoming class. Your teacher will approve or reject.</p>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-muted mb-3">New request</h2>
                <form method="post" action="<?= h($base . '/student/reschedule') ?>">
                    <div class="mb-3">
                        <label class="form-label" for="class_id">Class</label>
                        <select class="form-select" name="class_id" id="class_id" required>
                            <option value="">Select class</option>
                            <?php foreach ($classes ?? [] as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= h($c['title']) ?> — <?= h($c['start_datetime']) ?></option>
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
                        <label class="form-label" for="reason">Reason (optional)</label>
                        <textarea class="form-control" name="reason" id="reason" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit request</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-muted mb-3">Your requests</h2>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Class</th><th>Proposed</th><th>Status</th><th>Comment</th></tr></thead>
                        <tbody>
                        <?php if (empty($requests)): ?>
                            <tr><td colspan="4" class="text-muted small">No requests yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($requests as $r): ?>
                                <tr>
                                    <td><?= h($r['class_title'] ?? '') ?></td>
                                    <td><?= h($r['requested_date'] . ' ' . $r['requested_time']) ?></td>
                                    <td><span class="badge text-bg-secondary"><?= h($r['status']) ?></span></td>
                                    <td class="small"><?= h($r['teacher_comment'] ?? '—') ?></td>
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

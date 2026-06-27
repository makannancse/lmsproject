<?php

use function htmlspecialchars as h;

$base = appWebPath();
$statusFilter = $statusFilter ?? '';
$payments = $payments ?? [];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Student Payments (INR)</h1>
        <p class="small text-muted mb-0">Admin can mark pending payments as paid.</p>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="get" action="<?= h($base . '/admin/student-payments') ?>" class="row g-2 align-items-center no-app-loader">
            <div class="col-auto">
                <label for="status" class="col-form-label col-form-label-sm">Filter</label>
            </div>
            <div class="col-auto">
                <select name="status" id="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>All</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
                <th>Student</th>
                <th>Class</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Payment Date</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($payments)): ?>
                <tr><td colspan="6" class="text-muted small">No student payments found.</td></tr>
            <?php else: ?>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= h((string) $p['student_name']) ?></div>
                            <div class="small text-muted"><?= h((string) $p['student_email']) ?></div>
                        </td>
                        <td><?= h((string) $p['class_title']) ?></td>
                        <td><?= h(formatCurrency((float) ($p['amount'] ?? 0))) ?></td>
                        <td>
                            <span class="badge <?= ($p['status'] ?? 'pending') === 'paid' ? 'text-bg-success' : 'text-bg-warning' ?> text-uppercase">
                                <?= h((string) $p['status']) ?>
                            </span>
                        </td>
                        <td><?= h((string) ($p['payment_date'] ?? '-')) ?></td>
                        <td>
                            <?php if (($p['status'] ?? 'pending') !== 'paid'): ?>
                                <form method="post"
                                      action="<?= h($base . '/admin/student-payments/mark-paid') ?>"
                                      class="d-inline"
                                      data-loader-title="Updating payment..."
                                      data-loader-text="Marking this student payment as paid.">
                                    <input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">Mark Paid</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted small">Completed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php renderPagination($pagination ?? null, $queryParams ?? []); ?>
</div>

<?php

use function htmlspecialchars as h;

$statusFilter = $statusFilter ?? '';
$payments = $payments ?? [];
$base = appWebPath();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">My Class Payments</h1>
        <p class="small text-muted mb-0">View-only payment status for enrolled classes.</p>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="get" action="<?= h($base . '/student/payments') ?>" class="row g-2 align-items-center no-app-loader">
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
                <th>Class</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Payment Date</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($payments)): ?>
                <tr><td colspan="4" class="text-muted small">No payment records found.</td></tr>
            <?php else: ?>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= h((string) $p['class_title']) ?></td>
                        <td><?= h(formatCurrency((float) ($p['amount'] ?? 0))) ?></td>
                        <td>
                            <span class="badge <?= ($p['status'] ?? 'pending') === 'paid' ? 'text-bg-success' : 'text-bg-warning' ?> text-uppercase">
                                <?= h((string) $p['status']) ?>
                            </span>
                        </td>
                        <td><?= h((string) ($p['payment_date'] ?? '-')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php renderPagination($pagination ?? null, $queryParams ?? []); ?>
</div>

<?php

use function htmlspecialchars as h;

$base = appWebPath();
$rows = $rows ?? [];
$statusFilter = $statusFilter ?? '';
$flashCode = (string) ($_GET['success'] ?? '');
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="h4 mb-0">Teacher Payment Dashboard</h1>
        <p class="text-muted small mb-0">Mark payouts as paid or record manual payments.</p>
    </div>
    <a href="<?= h(path('admin')) ?>" class="btn btn-outline-secondary btn-sm">Back to Admin</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
        <div class="card dashboard-stat-card h-100">
            <div class="card-body">
                <div class="small text-muted text-uppercase">Total Payout</div>
                <div class="h4 mb-0"><?= h(formatCurrency((float) $totalPayout)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card dashboard-stat-card h-100">
            <div class="card-body">
                <div class="small text-muted text-uppercase">Total Paid</div>
                <div class="h4 mb-0"><?= h(formatCurrency((float) $totalPaid)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card dashboard-stat-card h-100">
            <div class="card-body">
                <div class="small text-muted text-uppercase">Total Pending</div>
                <div class="h4 mb-0"><?= h(formatCurrency((float) $totalPending)) ?></div>
            </div>
        </div>
    </div>
</div>

<form class="row g-2 mb-3" method="get" action="<?= h(path('admin/payments')) ?>">
    <div class="col-md-4">
        <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <?php foreach (['paid', 'pending', 'advance', 'partial'] as $s): ?>
                <option value="<?= h($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= h(ucfirst($s)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-striped table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Teacher</th>
                <th>Total Earnings</th>
                <th>Paid</th>
                <th>Pending</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="6" class="text-muted text-center p-4">No payment data available.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $status = (string) ($r['status'] ?? 'pending');
                    $badge = match ($status) {
                        'paid' => 'text-bg-success',
                        'partial' => 'text-bg-warning text-dark',
                        'advance' => 'text-bg-primary',
                        default => 'text-bg-danger',
                    };
                    $delayBadge = '';
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= h((string) $r['name']) ?></div>
                            <div class="text-muted small"><?= h((string) $r['email']) ?></div>
                        </td>
                        <td><?= h(formatCurrency((float) $r['total_earnings'])) ?></td>
                        <td><?= h(formatCurrency((float) $r['paid_amount'])) ?></td>
                        <td><?= h(formatCurrency((float) $r['pending_amount'])) ?></td>
                        <td><span class="badge <?= h($badge) ?> text-capitalize"><?= h($status) ?></span></td>
                        <td class="text-end">
                            <a href="<?= h(path('admin/payments/details?teacher_id=' . (int) $r['teacher_id'])) ?>" class="btn btn-sm btn-outline-secondary">Details</a>
                            <form method="post" action="<?= h(path('admin/payments/process')) ?>" class="d-inline"
                                  data-confirm="1"
                                  data-confirm-title="Mark as Paid?"
                                  data-confirm-text="This will record the full pending amount as paid for this teacher."
                                  data-confirm-button="Mark as Paid">
                                <input type="hidden" name="teacher_id" value="<?= (int) $r['teacher_id'] ?>">
                                <input type="hidden" name="status" value="<?= h($statusFilter) ?>">
                                <button type="submit" name="mark_paid" value="1" class="btn btn-sm btn-primary" <?= (float) $r['pending_amount'] <= 0 ? 'disabled' : '' ?>>Mark as Paid</button>
                            </form>
                            <form method="post" action="<?= h(path('admin/payments/process')) ?>" class="d-inline-flex gap-1 ms-1 align-items-center">
                                <input type="hidden" name="teacher_id" value="<?= (int) $r['teacher_id'] ?>">
                                <input type="hidden" name="status" value="<?= h($statusFilter) ?>">
                                <input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="advance_amount" placeholder="Amount" required style="width: 100px;">
                                <input type="text" class="form-control form-control-sm" name="remarks" placeholder="Remarks" style="width: 120px;">
                                <button type="submit" name="add_payment" value="1" class="btn btn-sm btn-outline-primary">Add</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($flashCode === 'paid'): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.Swal) {
        Swal.fire({ icon: 'success', title: 'Marked as Paid', text: 'The teacher payout was updated successfully.', timer: 2500, showConfirmButton: false });
    }
});
</script>
<?php elseif ($flashCode === 'invalid_teacher'): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: 'Invalid teacher selected.' });
});
</script>
<?php endif; ?>

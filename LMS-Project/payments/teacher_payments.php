<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/lib/Auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/payment_helper.php';

Auth::requireRole(['admin']);

$statusFilter = (string) ($_GET['status'] ?? '');
$flash = (string) ($_GET['success'] ?? '');

$appBase = rtrim((string) (defined('BASE_PATH') ? BASE_PATH : ''), '/');
$paymentsRouteBase = $appBase . '/admin/payments';

$teacherRows = db()->query('SELECT id FROM users WHERE role = "teacher"')->fetchAll() ?: [];
foreach ($teacherRows as $tr) {
    refreshTeacherPaymentLogs((int) ($tr['id'] ?? 0));
}

$rows = getAllTeacherPayoutSummaries($statusFilter);
$totalPayout = 0.0;
$totalPaid = 0.0;
$totalPending = 0.0;
foreach ($rows as $row) {
    $totalPayout += (float) ($row['total_earnings'] ?? 0);
    $totalPaid += (float) ($row['paid_amount'] ?? 0);
    $totalPending += (float) ($row['pending_amount'] ?? 0);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teacher Payments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .payments-table tbody tr { transition: background-color .15s ease-in-out; }
        .payments-table tbody tr:hover { background-color: #f8f9fa; }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Teacher Payment Dashboard</h1>
        <a href="<?= htmlspecialchars($appBase . '/admin') ?>" class="btn btn-outline-secondary btn-sm">Back to Admin</a>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="alert alert-success py-2 small">Action completed: <?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Total Payout</div>
                    <div class="h4 mb-0"><?= htmlspecialchars(formatCurrency((float) $totalPayout)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Total Paid</div>
                    <div class="h4 mb-0"><?= htmlspecialchars(formatCurrency((float) $totalPaid)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Total Pending</div>
                    <div class="h4 mb-0"><?= htmlspecialchars(formatCurrency((float) $totalPending)) ?></div>
                </div>
            </div>
        </div>
    </div>

    <form class="row g-2 mb-3" method="get">
        <div class="col-md-4">
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach (['paid', 'pending', 'advance', 'partial'] as $s): ?>
                    <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover table-bordered mb-0 align-middle payments-table">
                <thead class="table-light">
                <tr>
                    <th>Teacher</th>
                    <th>Total Earnings</th>
                    <th>Paid Amount</th>
                    <th>Pending Amount</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6" class="text-muted text-center p-4">No payment data available</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars((string) $r['name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars((string) $r['email']) ?></div>
                            </td>
                            <td><?= htmlspecialchars(formatCurrency((float) $r['total_earnings'])) ?></td>
                            <td><?= htmlspecialchars(formatCurrency((float) $r['paid_amount'])) ?></td>
                            <td><?= htmlspecialchars(formatCurrency((float) $r['pending_amount'])) ?></td>
                            <td>
                                <?php $status = (string) ($r['status'] ?? 'pending'); ?>
                                <?php
                                $badge = 'bg-danger';
                                if ($status === 'paid') {
                                    $badge = 'bg-success';
                                } elseif ($status === 'partial') {
                                    $badge = 'bg-warning text-dark';
                                } elseif ($status === 'advance') {
                                    $badge = 'bg-primary';
                                }
                                ?>
                                <span class="badge <?= $badge ?> text-capitalize"><?= htmlspecialchars($status) ?></span>
                            </td>
                            <td class="text-end">
                                <a href="<?= htmlspecialchars($paymentsRouteBase . '/details?teacher_id=' . (int) $r['teacher_id']) ?>" class="btn btn-sm btn-outline-secondary">View Details</a>
                                <form method="post" action="<?= htmlspecialchars($paymentsRouteBase . '/process') ?>" class="d-inline">
                                    <input type="hidden" name="teacher_id" value="<?= (int) $r['teacher_id'] ?>">
                                    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                                    <button type="submit" name="mark_paid" value="1" class="btn btn-sm btn-primary" <?= (float) $r['pending_amount'] <= 0 ? 'disabled' : '' ?>>Mark as Paid</button>
                                </form>
                                <form method="post" action="<?= htmlspecialchars($paymentsRouteBase . '/process') ?>" class="d-inline-flex gap-1 ms-1">
                                    <input type="hidden" name="teacher_id" value="<?= (int) $r['teacher_id'] ?>">
                                    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                                    <input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="advance_amount" placeholder="Payment" required style="width: 110px;">
                                    <input type="text" class="form-control form-control-sm" name="remarks" placeholder="Remarks" style="width: 140px;">
                                    <button type="submit" name="add_payment" value="1" class="btn btn-sm btn-outline-primary">Add Payment</button>
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
</body>
</html>

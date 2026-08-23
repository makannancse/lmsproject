<?php

use function htmlspecialchars as h;

$base = appWebPath();
$filters = $filters ?? [
    'status' => '',
    'student_id' => 0,
    'teacher_id' => 0,
    'date_from' => '',
    'date_to' => '',
    'q' => '',
];
$summary = $summary ?? [
    'total_amount' => 0.0,
    'pending_amount' => 0.0,
    'paid_amount' => 0.0,
    'total_count' => 0,
];
$payments = $payments ?? [];
$students = $students ?? [];
$teachers = $teachers ?? [];

$pdfExportUrl = $base . '/admin/student-payments/export-pdf?' . http_build_query($queryParams ?? []);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0"><i class="fa-solid fa-receipt me-2 text-primary"></i>Student Payments (INR)</h1>
        <p class="small text-muted mb-0">Filter student fee records, view live amount totals, and export PDF statements.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= h($pdfExportUrl) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-danger shadow-sm">
            <i class="fa-solid fa-file-pdf me-1"></i>Export PDF Statement
        </a>
    </div>
</div>

<!-- Live Summary Cards -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 bg-primary bg-opacity-10 h-100">
            <div class="card-body p-3">
                <div class="small text-uppercase fw-semibold text-primary mb-1">Filtered Amount</div>
                <div class="h4 mb-0 fw-bold text-primary"><?= h(formatCurrency($summary['total_amount'])) ?></div>
                <div class="small text-muted mt-1"><?= (int) $summary['total_count'] ?> class record<?= $summary['total_count'] === 1 ? '' : 's' ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 bg-warning bg-opacity-10 h-100">
            <div class="card-body p-3">
                <div class="small text-uppercase fw-semibold text-warning-emphasis mb-1">Total Pending (Due)</div>
                <div class="h4 mb-0 fw-bold text-warning-emphasis"><?= h(formatCurrency($summary['pending_amount'])) ?></div>
                <div class="small text-muted mt-1">Outstanding fee sum</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 bg-success bg-opacity-10 h-100">
            <div class="card-body p-3">
                <div class="small text-uppercase fw-semibold text-success mb-1">Total Paid</div>
                <div class="h4 mb-0 fw-bold text-success"><?= h(formatCurrency($summary['paid_amount'])) ?></div>
                <div class="small text-muted mt-1">Collected fee sum</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 bg-secondary bg-opacity-10 h-100">
            <div class="card-body p-3">
                <div class="small text-uppercase fw-semibold text-secondary mb-1">Total Records</div>
                <div class="h4 mb-0 fw-bold text-secondary"><?= (int) $summary['total_count'] ?></div>
                <div class="small text-muted mt-1">Classes in filter scope</div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-light-subtle py-2">
        <div class="fw-semibold small"><i class="fa-solid fa-filter me-1 text-secondary"></i>Filter Payment Records</div>
    </div>
    <div class="card-body py-3">
        <form method="get" action="<?= h($base . '/admin/student-payments') ?>" class="row g-2 align-items-end no-app-loader">
            <div class="col-12 col-sm-6 col-md-3">
                <label for="student_id" class="form-label form-label-sm fw-semibold mb-1">Student</label>
                <select name="student_id" id="student_id" class="form-select form-select-sm">
                    <option value="">All Students</option>
                    <?php foreach ($students as $st): ?>
                        <option value="<?= (int) $st['id'] ?>" <?= (int) ($filters['student_id'] ?? 0) === (int) $st['id'] ? 'selected' : '' ?>>
                            <?= h((string) $st['name']) ?> (<?= h((string) $st['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-6 col-sm-3 col-md-2">
                <label for="status" class="form-label form-label-sm fw-semibold mb-1">Payment Status</label>
                <select name="status" id="status" class="form-select form-select-sm">
                    <option value="" <?= ($filters['status'] ?? '') === '' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="paid" <?= ($filters['status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option>
                </select>
            </div>

            <div class="col-6 col-sm-3 col-md-2">
                <label for="class_status" class="form-label form-label-sm fw-semibold mb-1">Class Completion</label>
                <select name="class_status" id="class_status" class="form-select form-select-sm">
                    <option value="" <?= ($filters['class_status'] ?? '') === '' ? 'selected' : '' ?>>All Classes</option>
                    <option value="completed" <?= ($filters['class_status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="pending" <?= ($filters['class_status'] ?? '') === 'pending' ? 'selected' : '' ?>>Scheduled / Pending</option>
                </select>
            </div>

            <div class="col-6 col-sm-3 col-md-2">
                <label for="teacher_id" class="form-label form-label-sm fw-semibold mb-1">Teacher</label>
                <select name="teacher_id" id="teacher_id" class="form-select form-select-sm">
                    <option value="">All Teachers</option>
                    <?php foreach ($teachers as $tc): ?>
                        <option value="<?= (int) $tc['id'] ?>" <?= (int) ($filters['teacher_id'] ?? 0) === (int) $tc['id'] ? 'selected' : '' ?>>
                            <?= h((string) $tc['name']) ?>
                        </option>
                    <?php endforeach; ?>
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

            <div class="col-12 col-md-3">
                <label for="q" class="form-label form-label-sm fw-semibold mb-1">Search Keyword</label>
                <input type="text" name="q" id="q" class="form-control form-control-sm" placeholder="Class title or student/parent email..." value="<?= h((string) ($filters['q'] ?? '')) ?>">
            </div>

            <div class="col-12 col-md-auto ms-auto d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fa-solid fa-magnifying-glass me-1"></i>Filter
                </button>
                <a href="<?= h($base . '/admin/student-payments') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Student Payment Records Table -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Student</th>
                <th>Class Title &amp; Date</th>
                <th>Class Status</th>
                <th>Teacher</th>
                <th>Amount</th>
                <th>Payment Status</th>
                <th>Payment Date</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($payments)): ?>
                <tr><td colspan="9" class="text-muted text-center py-4">No student payment records found matching your filters.</td></tr>
            <?php else: ?>
                <?php foreach ($payments as $idx => $p): ?>
                    <tr>
                        <td class="text-muted small"><?= (int) ($pagination['offset'] ?? 0) + $idx + 1 ?></td>
                        <td>
                            <div class="fw-semibold"><?= h((string) $p['student_name']) ?></div>
                            <div class="small text-muted"><?= h((string) $p['student_email']) ?></div>
                            <?php if (!empty($p['parent_email'])): ?>
                                <div class="small text-primary-emphasis" title="Parent Email"><i class="fa-solid fa-user-shield me-1"></i><?= h((string) $p['parent_email']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= h((string) $p['class_title']) ?></div>
                            <div class="small text-muted"><i class="fa-regular fa-clock me-1"></i><?= h(formatClassScheduledAt($p, 'd M Y h:i A T')) ?></div>
                        </td>
                        <td>
                            <?php $csStatus = strtolower(trim((string) ($p['class_status'] ?? 'scheduled'))); ?>
                            <?php if ($csStatus === 'completed'): ?>
                                <span class="badge text-bg-success"><i class="fa-solid fa-circle-check me-1"></i>Completed</span>
                            <?php elseif ($csStatus === 'rescheduled'): ?>
                                <span class="badge text-bg-warning"><i class="fa-solid fa-clock-rotate-left me-1"></i>Rescheduled</span>
                            <?php elseif ($csStatus === 'cancelled'): ?>
                                <span class="badge text-bg-danger"><i class="fa-solid fa-ban me-1"></i>Cancelled</span>
                            <?php else: ?>
                                <span class="badge text-bg-info text-white"><i class="fa-regular fa-calendar me-1"></i>Scheduled</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h((string) ($p['teacher_name'] ?? '—')) ?></td>
                        <td class="fw-bold text-dark"><?= h(formatCurrency((float) ($p['amount'] ?? 0))) ?></td>
                        <td>
                            <span class="badge <?= ($p['status'] ?? 'pending') === 'paid' ? 'text-bg-success' : 'text-bg-warning' ?> text-uppercase">
                                <?= h((string) $p['status']) ?>
                            </span>
                        </td>
                        <td class="small text-muted"><?= h((string) ($p['payment_date'] ?? '—')) ?></td>
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
                                <span class="badge text-bg-light border text-muted">Completed</span>
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

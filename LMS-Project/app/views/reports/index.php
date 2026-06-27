<?php

use function htmlspecialchars as h;

$base = appWebPath();
$rows = $rows ?? [];
$role = (string) ($role ?? 'student');
$filters = $filters ?? [];
$students = $students ?? [];
$teachers = $teachers ?? [];

$listUrl = $role === 'admin' ? '/admin/reports' : ($role === 'teacher' ? '/teacher/reports' : '/student/reports');
$createUrl = $role === 'admin' ? '/admin/reports/create' : '/teacher/reports/create';
?>

<div class="row g-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h4 mb-0"><?= h($role === 'student' ? 'My Report Cards' : 'Student Report Cards') ?></h1>
            <p class="text-muted small mb-0">Reports captured and stored inside LMS.</p>
        </div>
        <?php if ($role !== 'student'): ?><a href="<?= h($base . $createUrl) ?>" class="btn btn-primary btn-sm">Create Report</a><?php endif; ?>
    </div>

    <?php if ($role !== 'student'): ?>
        <div class="col-12">
            <div class="card shadow-sm"><div class="card-body py-2">
                <form method="get" action="<?= h($base . $listUrl) ?>" class="row g-2 align-items-center no-app-loader">
                    <?php if ($role === 'admin'): ?>
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" name="student_id">
                                <option value="0">All Students</option>
                                <?php foreach ($students as $s): ?><option value="<?= (int) ($s['id'] ?? 0) ?>" <?= (int) ($filters['student_id'] ?? 0) === (int) ($s['id'] ?? 0) ? 'selected' : '' ?>><?= h((string) ($s['name'] ?? '')) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" name="teacher_id">
                                <option value="0">All Teachers</option>
                                <?php foreach ($teachers as $t): ?><option value="<?= (int) ($t['id'] ?? 0) ?>" <?= (int) ($filters['teacher_id'] ?? 0) === (int) ($t['id'] ?? 0) ? 'selected' : '' ?>><?= h((string) ($t['name'] ?? '')) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-2"><input type="text" class="form-control form-control-sm" name="subject" value="<?= h((string) ($filters['subject'] ?? '')) ?>" placeholder="Subject"></div>
                    <div class="col-md-2"><input type="date" class="form-control form-control-sm" name="from_date" value="<?= h((string) ($filters['from_date'] ?? '')) ?>"></div>
                    <div class="col-md-2"><input type="date" class="form-control form-control-sm" name="to_date" value="<?= h((string) ($filters['to_date'] ?? '')) ?>"></div>
                    <div class="col-md-12 d-flex gap-1">
                        <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
                        <?php if ($role === 'admin'): ?><a href="<?= h($base . '/admin/reports/import') ?>" class="btn btn-sm btn-outline-secondary">Import CSV</a><?php endif; ?>
                    </div>
                </form>
            </div></div>
        </div>
    <?php endif; ?>

    <div class="col-12">
        <div class="card shadow-sm"><div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>Subject</th><th>Student Name</th><th>Teacher Name</th><th>Performance</th><th>Date</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="text-muted p-3">No report cards available.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <?php
                        $perf = strtolower((string) ($r['overall_performance'] ?? 'average'));
                        $badge = 'bg-warning text-dark';
                        if ($perf === 'excellent' || $perf === 'good') { $badge = 'bg-success'; }
                        elseif ($perf === 'need improvement') { $badge = 'bg-danger'; }
                        ?>
                        <tr>
                            <td><?= h((string) ($r['subject'] ?? '')) ?></td>
                            <td><?= h((string) ($r['student_name'] ?? '')) ?></td>
                            <td><?= h((string) ($r['teacher_name'] ?? '')) ?></td>
                            <td><span class="badge <?= $badge ?>"><?= h((string) ($r['overall_performance'] ?? '')) ?></span></td>
                            <td><?= h((string) ($r['report_date'] ?? '')) ?></td>
                            <td class="text-end">
                                <a href="<?= h($base . '/reports/view?id=' . (int) ($r['id'] ?? 0)) ?>" class="btn btn-sm btn-outline-primary">View</a>
                                <?php
                                $rid = (int) ($r['id'] ?? 0);
                                $pdfRel = (string) ($r['pdf_path'] ?? '');
                                $pdfAbs = $pdfRel !== '' ? dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($pdfRel, '/\\')) : '';
                                $pdfOk = $pdfRel !== '' && is_file($pdfAbs);
                                ?>
                                <?php if ($pdfOk): ?>
                                    <a href="<?= h($base . '/reports/download?id=' . $rid) ?>" class="btn btn-sm btn-outline-secondary">Download PDF</a>
                                <?php elseif ($pdfRel !== ''): ?>
                                    <span class="small text-muted">PDF missing</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
</div>


<?php

use function htmlspecialchars as h;

$base = appWebPath();
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Import Reports from CSV</h1>
                <p class="small text-muted">Export Google Form responses to CSV and import here. Required headers: <code>student_id,teacher_id,performance_rating,understanding_level,report_date</code>. Optional: <code>strengths,improvements,comments</code>.</p>
                <div class="mb-3">
                    <a href="<?= h((string) ($googleFormUrl ?? '#')) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">Open Google Form</a>
                </div>
                <form method="post" action="<?= h($base . '/admin/reports/import') ?>" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">CSV File</label>
                        <input type="file" class="form-control" name="csv" accept=".csv" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Import</button>
                    <a href="<?= h($base . '/admin/reports') ?>" class="btn btn-link">Back</a>
                </form>
            </div>
        </div>
    </div>
</div>

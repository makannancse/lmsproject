<?php

use function htmlspecialchars as h;

$lines = $lines ?? [];
$logFiles = $logFiles ?? [];
$logFile = (string) ($logFile ?? 'mail.log');
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-0">Email Logs</h1>
        <p class="text-muted small mb-0">SMTP delivery and credential email logs (newest first).</p>
    </div>
    <a href="<?= h(path('admin/logs/audit')) ?>" class="btn btn-sm btn-outline-secondary">Audit Logs</a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end no-app-loader">
            <div class="col-auto">
                <label class="form-label form-label-sm mb-0" for="logFile">Log file</label>
                <select name="file" id="logFile" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($logFiles as $file): ?>
                        <option value="<?= h($file) ?>" <?= $logFile === $file ? 'selected' : '' ?>><?= h($file) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if ($lines === []): ?>
            <p class="text-muted small p-3 mb-0">No entries found in <?= h($logFile) ?>.</p>
        <?php else: ?>
            <?php foreach ($lines as $line): ?>
                <div class="app-log-line"><?= h($line) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php renderPagination($pagination ?? null, $queryParams ?? []); ?>
    </div>
</div>

<?php

use function htmlspecialchars as h;

$lines = $lines ?? [];
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-0">Audit Logs</h1>
        <p class="text-muted small mb-0">User management and system audit entries (newest first).</p>
    </div>
    <a href="<?= h(path('admin/logs/email')) ?>" class="btn btn-sm btn-outline-primary">Email Logs</a>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if ($lines === []): ?>
            <p class="text-muted small p-3 mb-0">No audit log entries found in logs/user_management.log.</p>
        <?php else: ?>
            <?php foreach ($lines as $line): ?>
                <div class="app-log-line"><?= h($line) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php renderPagination($pagination ?? null, $queryParams ?? []); ?>
    </div>
</div>

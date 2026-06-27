<?php

use function htmlspecialchars as h;

$items = $items ?? [];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">My Feedback</h1>
        <p class="text-muted small mb-0">Feedback shared by your teachers.</p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($items === []): ?>
            <p class="text-muted small mb-0">No feedback has been shared yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Teacher</th>
                        <th>Subject</th>
                        <th>Notes</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="small"><?= h((string) ($item['created_at'] ?? '')) ?></td>
                            <td><?= h((string) ($item['teacher_name'] ?? '')) ?></td>
                            <td><?= h((string) ($item['subject'] ?? '—')) ?></td>
                            <td><?= h((string) ($item['comments'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

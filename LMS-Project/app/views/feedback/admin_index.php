<?php

use function htmlspecialchars as h;

$items = $items ?? [];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Student Feedback</h1>
        <p class="text-muted small mb-0">Feedback submitted by teachers for their students.</p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($items === []): ?>
            <p class="text-muted small mb-0">No feedback has been submitted yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Teacher</th>
                        <th>Subject</th>
                        <th>Rating</th>
                        <th>Comments</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="small text-nowrap"><?= h((string) ($item['created_at'] ?? '')) ?></td>
                            <td><?= h((string) ($item['student_name'] ?? '')) ?></td>
                            <td><?= h((string) ($item['teacher_name'] ?? '')) ?></td>
                            <td><?= h((string) ($item['subject'] ?? '—')) ?></td>
                            <td><?= h((string) ($item['rating'] ?? '—')) ?>/5</td>
                            <td class="small"><?= h((string) ($item['comments'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

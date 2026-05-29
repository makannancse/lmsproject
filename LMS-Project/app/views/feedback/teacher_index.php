<?php

use function htmlspecialchars as h;

$base = defined('BASE_PATH') ? BASE_PATH : '';
?>

<div class="row g-3">
    <div class="col-12">
        <h1 class="h4">Student feedback</h1>
        <p class="text-muted small">Create performance feedback for any student you share a class roster with—no minimum class count required.</p>
    </div>
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                        <tr><th>Student</th><th>Completed classes</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php if (empty($eligible)): ?>
                            <tr><td colspan="3" class="text-muted small p-3">No students on your roster yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($eligible as $e): ?>
                                <tr>
                                    <td><?= h($e['student_name']) ?></td>
                                    <td><?= (int) $e['completed_count'] ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-primary" href="<?= h($base . '/teacher/feedback/create?student_id=' . (int) $e['student_id']) ?>">Give feedback</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

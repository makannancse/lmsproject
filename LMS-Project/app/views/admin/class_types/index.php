<?php

use function htmlspecialchars as h;

$base = appWebPath();
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-10">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Class Types</h1>
            <a href="<?= h($base . '/admin/class-types/create') ?>" class="btn btn-primary btn-sm">Add type</a>
        </div>
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($types)): ?>
                            <tr><td colspan="3" class="text-muted small p-3">No class types yet. Run DB migration and add records.</td></tr>
                        <?php else: ?>
                            <?php foreach ($types as $t): ?>
                                <tr>
                                    <td><?= h($t['class_name']) ?></td>
                                    <td><span class="badge text-bg-secondary"><?= h($t['status']) ?></span></td>
                                    <td class="text-end">
                                        <a href="<?= h($base . '/admin/class-types/edit?id=' . (int)$t['id']) ?>" class="btn btn-outline-primary btn-sm">Edit</a>
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

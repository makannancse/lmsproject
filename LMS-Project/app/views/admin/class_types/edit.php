<?php

use function htmlspecialchars as h;

$base = appWebPath();
$t = $type ?? [];
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Edit Class Type</h1>
                <form method="post" action="<?= h($base . '/admin/class-types/update') ?>">
                    <input type="hidden" name="id" value="<?= (int)($t['id'] ?? 0) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="class_name">Name</label>
                        <input type="text" class="form-control" id="class_name" name="class_name"
                               value="<?= h($t['class_name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?= h($t['description'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?= (($t['status'] ?? '') === 'active') ? 'selected' : '' ?>>active</option>
                            <option value="inactive" <?= (($t['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>inactive</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Update</button>
                    <a href="<?= h($base . '/admin/class-types') ?>" class="btn btn-link">Back</a>
                </form>
            </div>
        </div>
    </div>
</div>

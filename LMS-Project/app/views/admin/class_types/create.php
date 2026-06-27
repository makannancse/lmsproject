<?php

use function htmlspecialchars as h;

$base = appWebPath();
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Add Class Type</h1>
                <form method="post" action="<?= h($base . '/admin/class-types') ?>">
                    <div class="mb-3">
                        <label class="form-label" for="class_name">Name</label>
                        <input type="text" class="form-control" id="class_name" name="class_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active">active</option>
                            <option value="inactive">inactive</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Save</button>
                    <a href="<?= h($base . '/admin/class-types') ?>" class="btn btn-link">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

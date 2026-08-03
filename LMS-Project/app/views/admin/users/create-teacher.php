<?php

use function htmlspecialchars as h;

$errors = $errors ?? [];
$old = $old ?? [];
$base = appWebPath();

?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Create Teacher</h1>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger small">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= h($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?= h($base . '/admin/users') ?>">
                    <input type="hidden" name="role" value="teacher">
                    <div class="mb-3">
                        <label class="form-label" for="name">Name</label>
                        <input type="text" id="name" name="name" class="form-control"
                               value="<?= h($old['name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control"
                               value="<?= h($old['email'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" class="form-control" minlength="8" required>
                            <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password">Show</button>
                        </div>
                        <div class="form-text">Minimum 8 characters. Sent to the user by email after creation.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirm_password">Confirm Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="8" required>
                            <button class="btn btn-outline-secondary" type="button" data-toggle-password="#confirm_password">Show</button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <?php
                        $fieldId = 'timezone';
                        $fieldName = 'timezone';
                        $adminTz = resolveUserTimezone(Auth::user() ?: null, APP_TIMEZONE);
                        $selectedValue = (string) ($old['timezone'] ?? $adminTz);
                        $required = false;
                        require dirname(__DIR__, 2) . '/partials/timezone-select.php';
                        ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="employment_type">Employment Type</label>
                        <select id="employment_type" name="employment_type" class="form-select">
                            <option value="full_time" <?= ($old['employment_type'] ?? '') === 'full_time' ? 'selected' : '' ?>>Full-time</option>
                            <option value="part_time" <?= ($old['employment_type'] ?? 'part_time') === 'part_time' ? 'selected' : '' ?>>Part-time</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Teacher</button>
                    <a href="<?= h($base . '/admin/users?role=teacher') ?>" class="btn btn-link">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = document.querySelector(btn.getAttribute('data-toggle-password'));
            if (!target) return;
            target.type = target.type === 'password' ? 'text' : 'password';
            btn.textContent = target.type === 'password' ? 'Show' : 'Hide';
        });
    });
});
</script>

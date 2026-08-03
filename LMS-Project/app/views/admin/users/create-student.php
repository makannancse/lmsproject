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
                <h1 class="h4 mb-3">Create Student</h1>
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
                    <input type="hidden" name="role" value="student">
                    <div class="mb-3">
                        <label class="form-label" for="name">Name</label>
                        <input type="text" id="name" name="name" class="form-control"
                               value="<?= h($old['name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Student email <span class="text-danger">*</span></label>
                        <input type="email" id="email" name="email" class="form-control"
                               value="<?= h($old['email'] ?? '') ?>" required autocomplete="email">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="parent_email">Parent / Guardian email <span class="text-danger">*</span></label>
                        <input type="email" id="parent_email" name="parent_email" class="form-control"
                               value="<?= h($old['parent_email'] ?? '') ?>" required
                               placeholder="Used for report cards and notifications" autocomplete="email">
                        <div class="form-text">Required so report PDFs can be emailed to the family.</div>
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
                        <label class="form-label" for="country">Country</label>
                        <input type="text" id="country" name="country" class="form-control"
                               value="<?= h($old['country'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Create Student</button>
                    <a href="<?= h($base . '/admin/users?role=student') ?>" class="btn btn-link">Cancel</a>
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

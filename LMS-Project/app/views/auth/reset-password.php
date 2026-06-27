<?php

use function htmlspecialchars as h;

$valid = !empty($valid);
$error = $error ?? ($_SESSION['error'] ?? null);
unset($_SESSION['error']);
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Reset Password</h1>
                <?php if (!$valid): ?>
                    <div class="alert alert-danger small"><?= h((string) ($error ?: 'This reset link is invalid or has expired.')) ?></div>
                    <a href="<?= h(path('forgot-password')) ?>" class="btn btn-outline-primary">Request a new link</a>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger small"><?= h($error) ?></div>
                    <?php endif; ?>
                    <form method="post" action="<?= h(path('reset-password')) ?>">
                        <input type="hidden" name="token" value="<?= h((string) ($token ?? '')) ?>">
                        <div class="mb-3">
                            <label class="form-label" for="password">New Password</label>
                            <input type="password" class="form-control" id="password" name="password" minlength="8" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="confirm_password">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Update Password</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php

use function htmlspecialchars as h;

$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Forgot Password</h1>
                <p class="text-muted small">Enter your account email and we will send a secure reset link (valid for 1 hour).</p>
                <?php if ($error): ?>
                    <div class="alert alert-danger small"><?= h($error) ?></div>
                <?php endif; ?>
                <form method="post" action="<?= h(path('forgot-password')) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
                </form>
                <div class="text-center mt-3">
                    <a href="<?= h(path('login')) ?>" class="small">Back to login</a>
                </div>
            </div>
        </div>
    </div>
</div>

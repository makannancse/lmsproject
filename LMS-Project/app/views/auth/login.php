<?php

use function htmlspecialchars as h;

$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);
$timedOut = !empty($timedOut);
$roleHint = strtolower(trim((string) ($roleHint ?? '')));
$isStudentLogin = $roleHint === 'student';
$logoFsPath = dirname(__DIR__, 3) . '/public/assets/images/logo.png';
$studentBannerPath = dirname(__DIR__, 3) . '/public/assets/images/student-banner.jpg';
$defaultBannerPath = dirname(__DIR__, 3) . '/public/assets/images/banner.jpg';
$hasLogo = is_file($logoFsPath);
$bannerSrc = is_file($studentBannerPath) ? ((defined('BASE_URL') ? BASE_URL : '/') . 'assets/images/student-banner.jpg') : BANNER_PATH;
$hasBanner = is_file($studentBannerPath) || is_file($defaultBannerPath);
?>

<div class="container-fluid login-page-shell vh-100">
    <div class="row h-100 g-0">
        <div class="col-12 <?= $isStudentLogin ? 'col-md-5' : 'col-md-12' ?> d-flex align-items-center justify-content-center px-3 px-lg-5">
            <div class="login-box w-100">
                <?php if ($hasLogo): ?>
                    <div class="text-center mb-4">
                        <img src="<?= h(LOGO_PATH) ?>" alt="Logo" class="app-brand-logo img-fluid">
                    </div>
                <?php endif; ?>
                <div class="text-center mb-4">
                    <h1 class="login-title mb-1">Welcome Back 👋</h1>
                    <p class="login-subtitle mb-0">Access your learning dashboard</p>
                </div>
                <?php if ($timedOut): ?>
                    <div class="alert alert-warning small">Your session ended after 15 minutes of inactivity. Please sign in again.</div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger small"><?= h($error) ?></div>
                <?php endif; ?>
                <form method="post" action="<?= h(path('login')) ?>" class="needs-validation login-form" novalidate>
                    <div class="mb-3 input-group input-group-lg">
                        <span class="input-group-text"><i class="fa-regular fa-envelope"></i></span>
                        <input type="email" name="email" id="email" class="form-control form-control-lg" placeholder="Email address" required autofocus>
                    </div>
                    <div class="mb-4 input-group input-group-lg">
                        <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="password" id="password" class="form-control form-control-lg" placeholder="Password" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill">Login</button>
                </form>
                <div class="text-center mt-3">
                    <a href="<?= h(path('forgot-password')) ?>" class="small text-decoration-none">Forgot Password?</a>
                </div>
            </div>
        </div>
        <?php if ($isStudentLogin): ?>
            <div class="col-md-7 d-none d-md-block login-banner-section position-relative">
                <?php if ($hasBanner): ?>
                    <img src="<?= h($bannerSrc) ?>" class="img-fluid h-100 w-100 login-banner-image" alt="Learning banner">
                <?php else: ?>
                    <div class="h-100 w-100 login-banner-fallback"></div>
                <?php endif; ?>
                <div class="login-banner-overlay">
                    <h2 class="mb-1">Welcome Back</h2>
                    <p class="mb-0">Continue your learning journey.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

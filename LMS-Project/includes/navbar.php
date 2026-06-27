<?php

use function htmlspecialchars as h;

$base = appWebPath();
$user = Auth::user();
$userName = is_array($user) ? (string) ($user['name'] ?? 'User') : 'User';
$userRole = is_array($user) ? (string) ($user['role'] ?? '') : '';
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom app-topbar sticky-top">
    <div class="container-fluid px-3 px-lg-4">
        <div class="d-flex align-items-center w-100 gap-3">
            <button class="btn btn-link d-lg-none text-dark p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Open menu">
                <i class="fa-solid fa-bars fa-lg"></i>
            </button>
            <div class="flex-grow-1 min-w-0">
                <a class="d-flex align-items-center gap-2 text-decoration-none text-dark min-w-0" href="<?= h(path('dashboard')) ?>">
                    <?php if (is_file(dirname(__DIR__) . '/public/assets/images/logo.png')): ?>
                        <img src="<?= h(LOGO_PATH) ?>" alt="<?= h(APP_NAME) ?>" class="app-brand-logo-sm d-none">
                    <?php endif; ?>
                    <span class="fw-semibold text-truncate"><?= h(APP_NAME) ?></span>
                </a>
            </div>
            <div class="dropdown">
                <button class="btn btn-light btn-sm dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="app-user-dot rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center">
                        <i class="fa-solid fa-user"></i>
                    </span>
                    <span class="app-user-name text-truncate d-none d-sm-inline"><?= h($userName) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                    <?php if ($userRole !== ''): ?>
                        <li><span class="dropdown-item-text small text-muted"><?= h(ucfirst($userRole)) ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                    <?php endif; ?>
                    <?php if (Auth::isAdmin()): ?>
                        <li><a class="dropdown-item rounded-2" href="<?= h(path('settings')) ?>"><i class="fa-solid fa-gear fa-fw me-2"></i>System Settings</a></li>
                    <?php endif; ?>
                    <li><a class="dropdown-item rounded-2 text-danger" href="<?= h(path('logout')) ?>"><i class="fa-solid fa-right-from-bracket fa-fw me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

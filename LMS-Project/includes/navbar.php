<?php

use function htmlspecialchars as h;

$base = $base ?? (defined('BASE_PATH') ? BASE_PATH : '');
?>
<header class="app-topbar bg-white border-bottom shadow-sm">
    <div class="container-fluid px-3 px-lg-4">
        <div class="d-flex align-items-center justify-content-between app-navbar-height">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <button class="btn btn-light border d-lg-none rounded-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Open menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <a class="d-flex align-items-center gap-2 text-decoration-none text-dark min-w-0" href="<?= h($base . '/dashboard') ?>">
                    <img src="<?= h(LOGO_PATH) ?>" alt="Logo" width="40" height="40" class="rounded-3 border flex-shrink-0 object-fit-cover">
                    <span class="fw-semibold text-truncate"><?= h(APP_NAME) ?></span>
                </a>
            </div>
            <div class="dropdown">
                <button class="btn btn-light border rounded-pill px-3 d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="rounded-circle bg-light text-primary border d-inline-flex align-items-center justify-content-center app-user-dot"><i class="fa-solid fa-user"></i></span>
                    <span class="d-none d-sm-inline small fw-medium text-truncate app-user-name"><?= h(Auth::user()['name'] ?? 'Account') ?></span>
                    <i class="fa-solid fa-chevron-down small text-muted"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 mt-2">
                    <li><span class="dropdown-item-text small text-muted"><?= h(Auth::user()['email'] ?? '') ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <?php if (Auth::isAdmin()): ?>
                        <li><a class="dropdown-item rounded-2" href="<?= h($base . '/settings') ?>"><i class="fa-solid fa-gear fa-fw me-2"></i>Settings</a></li>
                    <?php endif; ?>
                    <li><a class="dropdown-item rounded-2 text-danger" href="<?= h($base . '/logout') ?>"><i class="fa-solid fa-right-from-bracket fa-fw me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</header>


<?php
$sidebarNavPath = dirname(__DIR__) . '/app/views/layouts/partials/app-nav.php';
?>
<aside class="app-sidebar d-none d-lg-flex flex-column bg-white border-end">
    <div class="p-3 border-bottom text-center app-sidebar-brand">
        <a href="<?= htmlspecialchars(path('dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="d-inline-block">
            <img src="<?= htmlspecialchars(LOGO_PATH, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?>" class="app-brand-logo img-fluid">
        </a>
    </div>
    <div class="p-2 flex-grow-1 overflow-auto">
        <?php require $sidebarNavPath; ?>
    </div>
</aside>

<div class="offcanvas offcanvas-start d-lg-none app-offcanvas" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-semibold" id="appSidebarLabel">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <?php require $sidebarNavPath; ?>
    </div>
</div>


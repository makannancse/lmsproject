<?php
$base = defined('BASE_PATH') ? BASE_PATH : '';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$loginPath = rtrim($base . '/login', '/');
$isLoginPage = rtrim($currentPath, '/') === $loginPath;
$includeRoot = dirname(__DIR__, 3) . '/includes';
require $includeRoot . '/header.php';
?>

<?php if (Auth::check()): ?>
    <?php require $includeRoot . '/navbar.php'; ?>
    <div class="app-container">
        <?php require $includeRoot . '/sidebar.php'; ?>
        <div class="main-content">
            <div class="page-content p-3 p-md-4">
                <?= $content ?? '' ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php if ($isLoginPage): ?>
        <main class="p-0 m-0">
            <?= $content ?? '' ?>
        </main>
    <?php else: ?>
        <main class="container py-4">
            <?= $content ?? '' ?>
        </main>
    <?php endif; ?>
<?php endif; ?>

<?php require $includeRoot . '/footer.php'; ?>

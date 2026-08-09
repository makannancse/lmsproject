<?php

use function htmlspecialchars as h;

$base = appWebPath();
$localBootstrapJs = '/assets/js/bootstrap.bundle.min.js';
$bootstrapJs = file_exists(dirname(__DIR__) . '/public' . $localBootstrapJs)
    ? path('assets/js/bootstrap.bundle.min.js')
    : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
$flashSuccess = $_SESSION['flash_success'] ?? null;
if ($flashSuccess !== null) {
    unset($_SESSION['flash_success']);
}
$flashWarning = $_SESSION['flash_warning'] ?? null;
if ($flashWarning !== null) {
    unset($_SESSION['flash_warning']);
}
$flashError = $_SESSION['flash_error'] ?? null;
if ($flashError !== null) {
    unset($_SESSION['flash_error']);
}
$flashInfo = $_SESSION['flash_info'] ?? null;
if ($flashInfo !== null) {
    unset($_SESSION['flash_info']);
}
$flashSessionQueue = $_SESSION['flash_queue'] ?? [];
if ($flashSessionQueue !== []) {
    unset($_SESSION['flash_queue']);
}
$flashQueue = [];
if (!empty($flashSuccess)) {
    $flashQueue[] = [
        'type' => 'success',
        'title' => 'Success',
        'text' => (string) $flashSuccess,
        'mode' => 'toast',
    ];
}
if (!empty($flashWarning)) {
    $flashQueue[] = [
        'type' => 'warning',
        'title' => 'Warning',
        'text' => (string) $flashWarning,
    ];
}
if (!empty($flashError)) {
    $flashQueue[] = [
        'type' => 'error',
        'title' => 'Error',
        'text' => (string) $flashError,
    ];
}
if (!empty($flashInfo)) {
    $flashQueue[] = [
        'type' => 'info',
        'title' => 'Notice',
        'text' => (string) $flashInfo,
        'mode' => 'toast',
    ];
}
if (is_array($flashSessionQueue)) {
    foreach ($flashSessionQueue as $flashItem) {
        if (!is_array($flashItem) || (empty($flashItem['text']) && empty($flashItem['html']))) {
            continue;
        }
        $flashQueue[] = [
            'type' => (string) ($flashItem['type'] ?? 'info'),
            'title' => (string) ($flashItem['title'] ?? ''),
            'text' => (string) ($flashItem['text'] ?? ''),
            'html' => (string) ($flashItem['html'] ?? ''),
            'mode' => (string) ($flashItem['mode'] ?? ''),
        ];
    }
}
$appUrlJs = rtrim((string) (defined('APP_URL') ? APP_URL : ''), '/');
?>
<div id="appLoader" class="app-loader-overlay d-none" aria-hidden="true" aria-live="polite">
    <div class="app-loader-card card shadow-lg border-0 p-4 text-center">
        <div class="spinner-border text-primary mb-3" role="status" aria-label="Loading"></div>
        <p class="mb-0 fw-semibold text-dark" data-app-loader-text>Working on it...</p>
        <p class="small text-muted mb-0 mt-1" data-app-loader-detail>Please wait a moment.</p>
    </div>
</div>
<script src="<?= h($bootstrapJs) ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>window.__APP_FLASHES__ = <?= json_encode($flashQueue, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
<script>window.APP_URL = <?= json_encode($appUrlJs, JSON_UNESCAPED_SLASHES) ?>;</script>
<script>window.__APP_BASE__ = <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= h(asset('js/app.js')) ?>"></script>
<script src="<?= h(asset('js/alerts.js')) ?>"></script>
<?php
if (!class_exists('Auth', false)) {
    require_once dirname(__DIR__) . '/app/lib/Auth.php';
}
if (Auth::check()):
    $meetPollInterval = max(15, (int) env('GOOGLE_MEET_AUTO_POLL_SECONDS', 20));
?>
<script>
window.__SESSION_KEEPALIVE__ = <?= json_encode([
    'base' => $base,
    'appUrl' => $appUrlJs,
    'timeoutSeconds' => Auth::SESSION_TIMEOUT_SECONDS,
], JSON_UNESCAPED_SLASHES) ?>;
window.__MEET_STATUS_POLL__ = <?= json_encode([
    'url' => path('meeting/sync-ongoing'),
    'intervalSeconds' => $meetPollInterval,
], JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= h(asset('js/session-keepalive.js')) ?>"></script>
<script src="<?= h(asset('js/meet-status-poll.js')) ?>"></script>
<?php endif; ?>
</body>
</html>

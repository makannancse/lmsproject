<?php

use function htmlspecialchars as h;

/** @var array<string, mixed>|null $pagination */
/** @var array<string, scalar|null> $queryParams */

if (empty($pagination) || (int) ($pagination['total'] ?? 0) <= 0) {
    return;
}

$queryParams = $queryParams ?? [];
$page = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? Pagination::DEFAULT_PER_PAGE);
$totalPages = (int) ($pagination['total_pages'] ?? 1);
$from = (int) ($pagination['from'] ?? 0);
$to = (int) ($pagination['to'] ?? 0);
$total = (int) ($pagination['total'] ?? 0);
$hasPrev = !empty($pagination['has_prev']);
$hasNext = !empty($pagination['has_next']);
$pageParam = $pageParam ?? 'page';
$perPageParam = $perPageParam ?? 'per_page';
$perPageOptions = $perPageOptions ?? Pagination::PER_PAGE_OPTIONS;
$showFirstLast = $showFirstLast ?? true;

$urlForPage = static function (int $targetPage) use ($pageParam, $queryParams): string {
    $basePath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

    return $basePath . Pagination::urlForParams($targetPage, $pageParam, $queryParams);
};
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3 pt-3 border-top app-pagination-bar">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="text-muted small">
            Showing <?= (int) $from ?>–<?= (int) $to ?> of <?= (int) $total ?> records
        </span>
        <form method="get" class="d-flex align-items-center gap-2 no-app-loader">
            <?php foreach ($queryParams as $key => $value): ?>
                <?php if ($key !== $pageParam && $key !== $perPageParam && $value !== null && $value !== ''): ?>
                    <input type="hidden" name="<?= h((string) $key) ?>" value="<?= h((string) $value) ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <label class="text-muted small mb-0" for="perPageSelect">Rows</label>
            <select name="<?= h($perPageParam) ?>" id="perPageSelect" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                <?php foreach ($perPageOptions as $option): ?>
                    <option value="<?= (int) $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= (int) $option ?></option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="<?= h($pageParam) ?>" value="1">
        </form>
    </div>
    <nav aria-label="Pagination">
        <ul class="pagination pagination-sm mb-0">
            <?php if ($showFirstLast): ?>
            <li class="page-item <?= !$hasPrev ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= h($urlForPage(1)) ?>" aria-label="First">First</a>
            </li>
            <?php endif; ?>
            <li class="page-item <?= !$hasPrev ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= h($urlForPage(max(1, $page - 1))) ?>" aria-label="Previous">Previous</a>
            </li>
            <li class="page-item disabled">
                <span class="page-link text-muted"><?= (int) $page ?> / <?= (int) $totalPages ?></span>
            </li>
            <li class="page-item <?= !$hasNext ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= h($urlForPage(min($totalPages, $page + 1))) ?>" aria-label="Next">Next</a>
            </li>
            <?php if ($showFirstLast): ?>
            <li class="page-item <?= !$hasNext ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= h($urlForPage($totalPages)) ?>" aria-label="Last">Last</a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>

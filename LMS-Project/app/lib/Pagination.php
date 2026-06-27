<?php

declare(strict_types=1);

/**
 * Shared pagination for list screens.
 */
class Pagination
{
    public const DEFAULT_PER_PAGE = 10;

    /** @var list<int> */
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * @return array{page: int, per_page: int, offset: int}
     */
    public static function fromRequest(?int $page = null, ?int $perPage = null): array
    {
        $page = max(1, $page ?? (int) ($_GET['page'] ?? 1));
        $perPage = $perPage ?? (int) ($_GET['per_page'] ?? self::DEFAULT_PER_PAGE);
        if (!in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];
    }

    /**
     * @return array{
     *   total: int,
     *   page: int,
     *   per_page: int,
     *   total_pages: int,
     *   from: int,
     *   to: int,
     *   has_prev: bool,
     *   has_next: bool
     * }
     */
    public static function meta(int $total, int $page, int $perPage): array
    {
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min(max(1, $page), $totalPages);
        $from = $total === 0 ? 0 : (($page - 1) * $perPage + 1);
        $to = min($total, $page * $perPage);

        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'from' => $from,
            'to' => $to,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages,
        ];
    }

    /**
     * Build a query string preserving filters and pagination.
     *
     * @param array<string, scalar|null> $baseParams
     */
    public static function url(int $page, ?int $perPage = null, array $baseParams = []): string
    {
        $params = array_merge($_GET, $baseParams);
        $params['page'] = max(1, $page);
        if ($perPage !== null) {
            $params['per_page'] = $perPage;
        }

        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                unset($params[$key]);
            }
        }

        $query = http_build_query($params);

        return $query === '' ? '?' : ('?' . $query);
    }

    /**
     * @param list<int> $allowedOptions
     * @return array{page: int, per_page: int, offset: int}
     */
    public static function fromRequestParams(
        string $pageKey = 'page',
        string $perPageKey = 'per_page',
        array $allowedOptions = self::PER_PAGE_OPTIONS,
        int $defaultPerPage = self::DEFAULT_PER_PAGE
    ): array {
        $page = max(1, (int) ($_GET[$pageKey] ?? 1));
        $perPage = (int) ($_GET[$perPageKey] ?? $defaultPerPage);
        if (!in_array($perPage, $allowedOptions, true)) {
            $perPage = $defaultPerPage;
        }

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];
    }

    /**
     * @param array<string, scalar|null> $baseParams
     */
    public static function urlForParams(
        int $page,
        string $pageKey,
        array $baseParams = [],
        ?int $perPage = null,
        ?string $perPageKey = null
    ): string {
        $params = array_merge($_GET, $baseParams);
        $params[$pageKey] = max(1, $page);
        if ($perPage !== null && $perPageKey !== null) {
            $params[$perPageKey] = $perPage;
        }

        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                unset($params[$key]);
            }
        }

        $query = http_build_query($params);

        return $query === '' ? '?' : ('?' . $query);
    }
}

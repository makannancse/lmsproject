<?php

declare(strict_types=1);

class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
	if ($method === 'HEAD') {
    	$method = 'GET';
	}
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';

    // Remove BASE_PATH safely
    if (defined('BASE_PATH') && BASE_PATH !== '') {
        $base = BASE_PATH;
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }
    }

    // Normalize multiple slashes
    $path = preg_replace('#/+#', '/', $path);

    // Remove trailing slash except root
    if ($path !== '/' && str_ends_with($path, '/')) {
        $path = rtrim($path, '/');
    }

    // Always default to root
    if ($path === '') {
        $path = '/';
    }

    $handler = $this->routes[$method][$path] ?? null;

    if ($handler && is_callable($handler)) {
        call_user_func($handler);
        return;
    }

    http_response_code(404);
    echo '404 Not Found';
   }
}

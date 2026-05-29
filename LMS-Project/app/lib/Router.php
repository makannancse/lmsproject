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
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // If the app is served from a subdirectory (BASE_PATH), normalize the path
        // so that routes can still be registered as /login, /dashboard, etc.
        if (defined('BASE_PATH') && BASE_PATH !== '' && $path !== '/') {
            $base = BASE_PATH;
            if (strpos($path, $base) === 0) {
                $path = substr($path, strlen($base)) ?: '/';
            }
        }

        if ($path !== '/') {
            $path = rtrim($path, '/');
            if ($path === '') {
                $path = '/';
            }
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

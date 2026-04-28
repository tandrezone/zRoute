<?php

declare(strict_types=1);

namespace zRoute;

/**
 * Simple PHP router.
 *
 * Usage:
 *
 *   $router = new Router();
 *
 *   $router->get('/', fn($p) => 'Home');
 *   $router->get('/products/$product-slug', fn($p) => 'Product: '.$p['product-slug']);
 *   $router->post('/users', fn($p) => 'Create user');
 *
 *   $router->notFound(fn($path) => '404');
 *   $router->methodNotAllowed(fn($method, $path) => '405');
 *
 *   // In a web context:
 *   $router->run();
 *
 *   // Or dispatch manually (useful in tests / CLI):
 *   $router->dispatch('GET', '/products/my-widget');
 */
class Router
{
    /** @var Route[] */
    private array $routes = [];

    private mixed $notFoundHandler = null;

    private mixed $methodNotAllowedHandler = null;

    // -----------------------------------------------------------------------
    // Route registration helpers
    // -----------------------------------------------------------------------

    public function get(string $pattern, callable $handler): static
    {
        return $this->addRoute('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): static
    {
        return $this->addRoute('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable $handler): static
    {
        return $this->addRoute('PUT', $pattern, $handler);
    }

    public function patch(string $pattern, callable $handler): static
    {
        return $this->addRoute('PATCH', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): static
    {
        return $this->addRoute('DELETE', $pattern, $handler);
    }

    /**
     * Register the same handler for all common HTTP methods.
     */
    public function any(string $pattern, callable $handler): static
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->addRoute($method, $pattern, $handler);
        }

        return $this;
    }

    /**
     * Register a route for an arbitrary HTTP method.
     */
    public function addRoute(string $method, string $pattern, callable $handler): static
    {
        $this->routes[] = new Route(strtoupper($method), $pattern, $handler);

        return $this;
    }

    // -----------------------------------------------------------------------
    // Error handlers
    // -----------------------------------------------------------------------

    /**
     * Set a handler for requests that match no route (HTTP 404).
     *
     * The handler receives the requested path as its first argument.
     */
    public function notFound(callable $handler): static
    {
        $this->notFoundHandler = $handler;

        return $this;
    }

    /**
     * Set a handler for requests where the path exists but the method does
     * not match any route (HTTP 405).
     *
     * The handler receives the HTTP method and the requested path.
     */
    public function methodNotAllowed(callable $handler): static
    {
        $this->methodNotAllowedHandler = $handler;

        return $this;
    }

    // -----------------------------------------------------------------------
    // Accessors
    // -----------------------------------------------------------------------

    /** @return Route[] */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    // -----------------------------------------------------------------------
    // Dispatching
    // -----------------------------------------------------------------------

    /**
     * Dispatch a request and return whatever the matched handler returns.
     *
     * @param string $method HTTP method (GET, POST, …)
     * @param string $path   URL path (query string is stripped automatically)
     */
    public function dispatch(string $method, string $path): mixed
    {
        $method = strtoupper($method);
        $path   = $this->normalizePath($path);

        // 1. Try to find a route that matches both method and path.
        foreach ($this->routes as $route) {
            $params = $route->matches($method, $path);
            if ($params !== null) {
                return ($route->getHandler())($params);
            }
        }

        // 2. Check whether the path exists under a *different* method → 405.
        foreach ($this->routes as $route) {
            if ($route->matchPath($path) !== null) {
                if ($this->methodNotAllowedHandler !== null) {
                    return ($this->methodNotAllowedHandler)($method, $path);
                }

                return null;
            }
        }

        // 3. Nothing matched → 404.
        if ($this->notFoundHandler !== null) {
            return ($this->notFoundHandler)($path);
        }

        return null;
    }

    /**
     * Dispatch the current HTTP request (uses $_SERVER superglobal).
     */
    public function run(): mixed
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';

        return $this->dispatch($method, $uri);
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    /**
     * Normalise a raw URL path:
     *  – strip query string / fragment
     *  – ensure leading slash
     *  – remove trailing slash (except for root "/")
     *  – resolve "." and ".." segments to prevent path-traversal
     */
    private function normalizePath(string $path): string
    {
        // Strip query string and fragment.
        $parsed = parse_url($path, PHP_URL_PATH);
        $path   = is_string($parsed) ? $parsed : '/';

        // Ensure leading slash.
        $path = '/' . ltrim($path, '/');

        // Resolve dot-segments to prevent path traversal.
        $segments = explode('/', $path);
        $resolved = [];
        foreach ($segments as $segment) {
            if ($segment === '..') {
                array_pop($resolved);
            } elseif ($segment !== '.') {
                $resolved[] = $segment;
            }
        }
        $path = implode('/', $resolved);
        if ($path === '') {
            $path = '/';
        }

        // Remove trailing slash (except root).
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path;
    }
}

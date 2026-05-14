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
 *   $router->get('home', '/', fn($p) => 'Home');
 *   $router->get('products.show', '/products/$product-slug', fn($p) => 'Product: '.$p['product-slug']);
 *   $router->post('users.create', '/users', fn($p) => 'Create user');
 *
 *   $router->notFound(fn($path) => '404');
 *   $router->methodNotAllowed(fn($method, $path) => '405');
 *
 *   // In a web context:
 *   $router->run();
 *
 *   // Or dispatch by route name statically:
 *   Router::dispatch('products.show', ['product-slug' => 'my-widget']);
 */
class Router
{
    /** @var Route[] */
    private array $routes = [];

    /**
     * @var array<string, callable>
     *
     * Named route handlers are kept globally to support Router::dispatch().
     */
    private static array $namedHandlers = [];

    private mixed $notFoundHandler = null;

    private mixed $methodNotAllowedHandler = null;

    // -----------------------------------------------------------------------
    // Route registration helpers
    // -----------------------------------------------------------------------

    public function get(string $name, string $pattern, callable $handler): static
    {
        return $this->addRoute('GET', $name, $pattern, $handler);
    }

    public function post(string $name, string $pattern, callable $handler): static
    {
        return $this->addRoute('POST', $name, $pattern, $handler);
    }

    public function put(string $name, string $pattern, callable $handler): static
    {
        return $this->addRoute('PUT', $name, $pattern, $handler);
    }

    public function patch(string $name, string $pattern, callable $handler): static
    {
        return $this->addRoute('PATCH', $name, $pattern, $handler);
    }

    public function delete(string $name, string $pattern, callable $handler): static
    {
        return $this->addRoute('DELETE', $name, $pattern, $handler);
    }

    /**
     * Register the same handler for all common HTTP methods.
     * Names are generated as "{$name}.get", "{$name}.post", etc.
     */
    public function any(string $name, string $pattern, callable $handler): static
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->addRoute($method, $name . '.' . strtolower($method), $pattern, $handler);
        }

        return $this;
    }

    /**
     * Register a route for an arbitrary HTTP method.
     */
    public function addRoute(string $method, string $name, string $pattern, callable $handler): static
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Route name cannot be empty.');
        }
        if (isset(self::$namedHandlers[$name])) {
            throw new \InvalidArgumentException('Route name already exists (names are globally unique): ' . $name);
        }

        $route = new Route(strtoupper($method), $name, $pattern, $handler);
        $this->routes[] = $route;
        self::$namedHandlers[$name] = $route->getHandler();

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
     * Dispatch a route handler statically by route name.
     */
    public static function dispatch(string $name, array $params = []): mixed
    {
        if (!isset(self::$namedHandlers[$name])) {
            return null;
        }

        return (self::$namedHandlers[$name])($params);
    }

    /**
     * Dispatch a request by HTTP method/path and return matched handler result.
     *
     * @param string $method HTTP method (GET, POST, …)
     * @param string $path   URL path (query string is stripped automatically)
     */
    public function dispatchRequest(string $method, string $path): mixed
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

        return $this->dispatchRequest($method, $uri);
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

# zRoute

A simple, lightweight PHP routing system available as a Composer package.

## Features

- **Static routes** – `/about`, `/contact`, `/api/v1/status`
- **Dynamic routes** – `/products/$product-slug`, `/users/$id`, `/users/{id}/posts/{postId}`
- **Dual parameter syntax** – legacy `$param` or curly-brace `{param}` (both fully supported)
- **Per-parameter regex constraints** – e.g. `{id}` constrained to `[0-9]+`
- **Array-based route loading** – define routes as plain PHP arrays via `loadFromArray()` / `loadFromFile()`
- **Named routes** – look up any registered route by its logical name
- **Middleware pipeline** – sequential PSR-like middleware execution before the handler
- **Parameter validation** – type-casting, required/optional checks, default values, structured arrays
- **Config-driven paths** – resolve paths from a config array at load time (`config[namespace.key]`)
- All common HTTP methods: `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, and `any()`
- Fluent (chainable) API
- Customisable 404 (Not Found) and 405 (Method Not Allowed) handlers
- Path normalisation: trailing slashes, query strings, and dot-segments (`.`, `..`) are handled automatically

---

## Requirements

- PHP 8.0 or higher

---

## Installation

```bash
composer require tandrezone/zroute
```

---

## Quick Start

```php
<?php

require 'vendor/autoload.php';

use zRoute\Router;

$router = new Router();

// Static route
$router->get('/', fn($p) => print "Home page\n");

// Dynamic route — parameter syntax: $paramName or {paramName}
$router->get('/products/$product-slug', function (array $params) {
    echo "Product: " . htmlspecialchars($params['product-slug']) . "\n";
});

// 404 handler
$router->notFound(function (string $path) {
    http_response_code(404);
    echo "404 – Not found: " . htmlspecialchars($path) . "\n";
});

// Dispatch the current HTTP request
$router->run();
```

---

## Route Syntax

Dynamic segments start with `$` (legacy) or are wrapped in `{` `}` (recommended for array definitions).
The parameter name may contain letters, digits, underscores `_`, and hyphens `-`.

| Pattern                          | Example URL                     | Extracted params                          |
|----------------------------------|---------------------------------|-------------------------------------------|
| `/about`                         | `/about`                        | `[]`                                      |
| `/users/$id`                     | `/users/42`                     | `['id' => '42']`                          |
| `/users/{id}`                    | `/users/42`                     | `['id' => '42']`                          |
| `/products/$product-slug`        | `/products/red-sneakers`        | `['product-slug' => 'red-sneakers']`      |
| `/users/{userId}/posts/{postId}` | `/users/7/posts/99`             | `['userId' => '7', 'postId' => '99']`     |

Dynamic segments match **one path segment only** (they never cross a `/`).

---

## Array-Based Route Loading

Define routes as a PHP array and load them all at once.
This enables separation of route definitions from bootstrap code and is the
recommended approach for Composer packages.

### Route Definition Schema

```php
[
    'name'       => 'resource.show',          // optional logical name
    'method'     => 'GET',                    // HTTP verb
    'path'       => '/resources/{id}',        // URI pattern
    'callback'   => [MyController::class, 'show'],
    'middleware' => [
        MyAuthMiddleware::class,
        MyCsrfMiddleware::class,
    ],
    'parameters' => [
        'id' => [
            'type'   => 'integer',
            'source' => 'path',   // 'path' | 'query' | 'body' | 'auto'
            'regex'  => '[0-9]+', // optional regex constraint (path params only)
        ],
        'page' => [
            'type'     => 'integer',
            'required' => false,
            'default'  => 1,
        ],
        'meta_data' => [
            'type'      => 'array',
            'required'  => false,
            'structure' => [
                'reference_key' => 'string',
                'retry_count'   => 'integer',
            ],
        ],
    ],
]
```

### Loading from an Array

```php
$router->loadFromArray($definitions);
```

### Loading from a File

The file must `return` a PHP array of route definitions:

```php
// routes.php
return [
    ['name' => 'home', 'method' => 'GET', 'path' => '/', 'callback' => [HomeController::class, 'index'], ...],
];

// bootstrap.php
$router->loadFromFile(__DIR__ . '/routes.php');
```

### Config-Driven Paths

A path like `config[namespace.key]` is resolved against a `$config` array supplied at load time:

```php
$router->loadFromFile(__DIR__ . '/routes.php', [
    'package_config_namespace' => [
        'redirect_target_route' => '/dashboard',
    ],
]);
```

---

## Middleware

Each middleware class must implement `zRoute\Contracts\MiddlewareInterface`:

```php
use zRoute\Contracts\MiddlewareInterface;
use zRoute\Request;

class AuthenticateUser implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): mixed
    {
        if (!$request->getHeader('Authorization')) {
            throw new \RuntimeException('Unauthorized', 401);
        }
        return $next($request);
    }
}
```

Middleware declared first in the array wraps middleware declared later (standard onion model).

---

## Parameter Validation

Route parameters are validated and type-cast automatically when a `parameters` block is present.
The validated, typed values are available on the `Request` object passed to the controller.

Supported types: `string`, `integer`, `float`, `boolean`, `array`.

A `zRoute\Exceptions\ValidationException` is thrown if validation fails.  Catch it in your
error handler to return a structured HTTP 422 response.

---

## Request Object

Controllers loaded via `loadFromArray()` / `loadFromFile()` receive a `zRoute\Request` instance:

```php
public function show(Request $request): mixed
{
    $id      = $request->getParam('id');        // path > query > body
    $page    = $request->getParam('page', 1);   // with default
    $all     = $request->all();                 // merged array
    $accept  = $request->getHeader('Accept');
    $method  = $request->getMethod();

    // …
}
```

---

## API Reference

### Registering routes (fluent API)

```php
$router->get(string $pattern, callable $handler): static
$router->post(string $pattern, callable $handler): static
$router->put(string $pattern, callable $handler): static
$router->patch(string $pattern, callable $handler): static
$router->delete(string $pattern, callable $handler): static
$router->any(string $pattern, callable $handler): static          // all common methods
$router->addRoute(string $method, string $pattern, callable $handler): static
```

### Array-based loading

```php
$router->loadFromArray(array $definitions, array $config = []): static
$router->loadFromFile(string $filePath, array $config = []): static
```

### Named routes

```php
$router->getNamedRoute(string $name): ?Route
```

### Error handlers

```php
$router->notFound(function (string $path): void { ... });
$router->methodNotAllowed(function (string $method, string $path): void { ... });
```

### Dispatching

```php
$router->run(): mixed          // dispatch from $_SERVER
$router->dispatch(string $method, string $path): mixed
```

---

## Full Example

See [`examples/index.php`](examples/index.php) for the classic fluent API and
[`examples/routes.php`](examples/routes.php) for a complete array-definition example.

---

## Web Server Configuration

### Apache (`.htaccess`)

```apacheconf
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

### Nginx

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

---

## Running the Tests

```bash
composer install
./vendor/bin/phpunit
```

---

## License

MIT

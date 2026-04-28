# zRoute

A simple, lightweight PHP routing system available as a Composer package.

## Features

- **Static routes** – `/about`, `/contact`, `/api/v1/status`
- **Dynamic routes** – `/products/$product-slug`, `/users/$id`, `/users/$userId/posts/$postId`
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

// Dynamic route — parameter syntax: $paramName
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

Dynamic segments start with `$`. The name may contain letters, digits, underscores `_`, and hyphens `-`.

| Pattern                          | Example URL                     | `$params`                                 |
|----------------------------------|---------------------------------|-------------------------------------------|
| `/about`                         | `/about`                        | `[]`                                      |
| `/users/$id`                     | `/users/42`                     | `['id' => '42']`                          |
| `/products/$product-slug`        | `/products/red-sneakers`        | `['product-slug' => 'red-sneakers']`      |
| `/users/$userId/posts/$postId`   | `/users/7/posts/99`             | `['userId' => '7', 'postId' => '99']`     |

Dynamic segments match **one path segment only** (they never cross a `/`).

---

## API Reference

### Registering routes

```php
$router->get(string $pattern, callable $handler): static
$router->post(string $pattern, callable $handler): static
$router->put(string $pattern, callable $handler): static
$router->patch(string $pattern, callable $handler): static
$router->delete(string $pattern, callable $handler): static
$router->any(string $pattern, callable $handler): static          // all common methods
$router->addRoute(string $method, string $pattern, callable $handler): static
```

Every method returns `$this` so calls can be chained:

```php
$router
    ->get('/', fn($p) => 'home')
    ->get('/about', fn($p) => 'about')
    ->post('/users', fn($p) => 'create user');
```

### Error handlers

```php
// Called when no route matches the path (HTTP 404)
$router->notFound(function (string $path): void {
    http_response_code(404);
    echo "404 Not Found";
});

// Called when the path matches a route but the method does not (HTTP 405)
$router->methodNotAllowed(function (string $method, string $path): void {
    http_response_code(405);
    echo "405 Method Not Allowed";
});
```

### Dispatching

```php
// Dispatch the real HTTP request (reads $_SERVER)
$router->run(): mixed

// Dispatch a request manually (useful in tests, CLI scripts)
$router->dispatch(string $method, string $path): mixed
```

---

## Full Example

See [`examples/index.php`](examples/index.php) for a complete working example.

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

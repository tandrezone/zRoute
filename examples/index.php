<?php

/**
 * zRoute – usage examples.
 *
 * This file is meant to be served by a web server configured to route every
 * request through index.php (see README for Apache / Nginx examples).
 *
 * You can also exercise routes quickly from the command line:
 *
 *   php -r "
 *     \$_SERVER['REQUEST_METHOD'] = 'GET';
 *     \$_SERVER['REQUEST_URI']    = '/products/my-widget';
 *     require 'examples/index.php';
 *   "
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use zRoute\Router;

$router = new Router();

// -------------------------------------------------------------------------
// Static routes
// -------------------------------------------------------------------------

$router->get('/', function (array $params): void {
    echo "Welcome to zRoute!\n";
});

$router->get('/about', function (array $params): void {
    echo "About page\n";
});

$router->get('/contact', function (array $params): void {
    echo "Contact page\n";
});

// -------------------------------------------------------------------------
// Dynamic routes  (parameters use the $name syntax)
// -------------------------------------------------------------------------

// Single dynamic segment – parameter name may contain hyphens
$router->get('/products/$product-slug', function (array $params): void {
    // Always sanitise output in real applications!
    $slug = htmlspecialchars($params['product-slug'], ENT_QUOTES, 'UTF-8');
    echo "Product: {$slug}\n";
});

// Single numeric-style segment
$router->get('/users/$id', function (array $params): void {
    $id = htmlspecialchars($params['id'], ENT_QUOTES, 'UTF-8');
    echo "User ID: {$id}\n";
});

// Multiple dynamic segments
$router->get('/users/$userId/posts/$postId', function (array $params): void {
    $userId = htmlspecialchars($params['userId'], ENT_QUOTES, 'UTF-8');
    $postId = htmlspecialchars($params['postId'], ENT_QUOTES, 'UTF-8');
    echo "User {$userId} › Post {$postId}\n";
});

// -------------------------------------------------------------------------
// Different HTTP methods
// -------------------------------------------------------------------------

$router->post('/users', function (array $params): void {
    echo "Create a new user\n";
});

$router->put('/users/$id', function (array $params): void {
    $id = htmlspecialchars($params['id'], ENT_QUOTES, 'UTF-8');
    echo "Update user {$id}\n";
});

$router->delete('/users/$id', function (array $params): void {
    $id = htmlspecialchars($params['id'], ENT_QUOTES, 'UTF-8');
    echo "Delete user {$id}\n";
});

// -------------------------------------------------------------------------
// Error handlers
// -------------------------------------------------------------------------

$router->notFound(function (string $path): void {
    http_response_code(404);
    $path = htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
    echo "404 – Page not found: {$path}\n";
});

$router->methodNotAllowed(function (string $method, string $path): void {
    http_response_code(405);
    $method = htmlspecialchars($method, ENT_QUOTES, 'UTF-8');
    $path   = htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
    echo "405 – Method not allowed: {$method} {$path}\n";
});

// -------------------------------------------------------------------------
// Dispatch
// -------------------------------------------------------------------------

$router->run();

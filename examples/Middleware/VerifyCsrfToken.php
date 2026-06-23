<?php

declare(strict_types=1);

namespace zRoute\Examples\Middleware;

use zRoute\Contracts\MiddlewareInterface;
use zRoute\Request;

/**
 * Example middleware: verifies a CSRF token in the request body or header.
 *
 * Stub implementation — always passes through for demonstration purposes.
 */
class VerifyCsrfToken implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): mixed
    {
        // Real implementation: compare $_SESSION['_token'] with request token.
        return $next($request);
    }
}

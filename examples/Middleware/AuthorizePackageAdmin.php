<?php

declare(strict_types=1);

namespace zRoute\Examples\Middleware;

use zRoute\Contracts\MiddlewareInterface;
use zRoute\Request;

/**
 * Example middleware: authorises an admin-level package user.
 *
 * Stub implementation — always passes through for demonstration purposes.
 */
class AuthorizePackageAdmin implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): mixed
    {
        // Real implementation: check user role/permission before proceeding.
        return $next($request);
    }
}

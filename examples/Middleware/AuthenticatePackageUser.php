<?php

declare(strict_types=1);

namespace zRoute\Examples\Middleware;

use zRoute\Contracts\MiddlewareInterface;
use zRoute\Request;

/**
 * Example middleware: authenticates the package user.
 *
 * In production this would verify a session token, JWT, API key, etc.
 * Here it is a no-op stub that simply passes the request to the next handler.
 */
class AuthenticatePackageUser implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): mixed
    {
        // Real implementation: validate credentials and throw/redirect on failure.
        return $next($request);
    }
}

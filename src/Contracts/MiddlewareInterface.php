<?php

declare(strict_types=1);

namespace zRoute\Contracts;

use zRoute\Request;

/**
 * Contract that every middleware class must implement.
 *
 * A middleware receives the current Request, performs its work (authentication,
 * CSRF verification, logging, etc.), and then calls $next to pass control to
 * the rest of the pipeline.  It may short-circuit the pipeline by returning a
 * response without calling $next.
 *
 * Example implementation:
 *
 *   class AuthenticateUser implements MiddlewareInterface
 *   {
 *       public function handle(Request $request, callable $next): mixed
 *       {
 *           if (!$request->getHeader('Authorization')) {
 *               throw new \RuntimeException('Unauthorized', 401);
 *           }
 *           return $next($request);
 *       }
 *   }
 */
interface MiddlewareInterface
{
    /**
     * Process the incoming request.
     *
     * @param Request  $request The current HTTP request.
     * @param callable $next    The next handler in the pipeline.
     *                          Signature: (Request): mixed
     * @return mixed            The response produced by this middleware or a
     *                          downstream handler.
     */
    public function handle(Request $request, callable $next): mixed;
}

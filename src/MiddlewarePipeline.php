<?php

declare(strict_types=1);

namespace zRoute;

use zRoute\Contracts\MiddlewareInterface;

/**
 * Executes a sequence of middleware classes around a final destination handler.
 *
 * Middleware classes are resolved and instantiated at pipeline-run time.
 * Each class must implement {@see MiddlewareInterface}.
 *
 * The pipeline is constructed using array_reduce so that middleware declared
 * first in the array wraps middleware declared later — matching the familiar
 * "onion" execution model:
 *
 *   Middleware[0]::handle → Middleware[1]::handle → … → $destination
 *
 * Usage:
 *
 *   $pipeline = new MiddlewarePipeline([
 *       AuthenticateUser::class,
 *       VerifyCsrfToken::class,
 *   ]);
 *
 *   return $pipeline->run($request, fn(Request $req) => $controller($req));
 */
class MiddlewarePipeline
{
    /**
     * @param string[] $middlewareClasses Fully-qualified class names in execution order.
     */
    public function __construct(private readonly array $middlewareClasses) {}

    /**
     * Build and execute the pipeline, ending with $destination.
     *
     * @param Request  $request     The incoming HTTP request.
     * @param callable $destination Final handler (the route controller callback).
     *                              Signature: (Request): mixed
     * @return mixed                Whatever the pipeline produces.
     */
    public function run(Request $request, callable $destination): mixed
    {
        // Build the pipeline by wrapping each middleware around the next layer,
        // starting from the innermost (last) middleware.
        $pipeline = array_reduce(
            array_reverse($this->middlewareClasses),
            static function (callable $next, string $class): callable {
                return static function (Request $request) use ($next, $class): mixed {
                    /** @var MiddlewareInterface $middleware */
                    $middleware = new $class();
                    return $middleware->handle($request, $next);
                };
            },
            $destination,
        );

        return $pipeline($request);
    }
}

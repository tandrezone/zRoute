<?php

declare(strict_types=1);

namespace zRoute;

use InvalidArgumentException;
use RuntimeException;

/**
 * Parses a generic route definition array and registers each entry with the
 * Router.
 *
 * Route Definition Schema
 * -----------------------
 * Each element in the definitions array is an associative array with:
 *
 *   name        (string)   Optional logical name, e.g. 'user.show'.
 *   method      (string)   HTTP verb: GET, POST, PUT, PATCH, DELETE.
 *   path        (string)   URL pattern.  Dynamic segments use {paramName}.
 *                          Config-driven paths use config[namespace.key].
 *   callback    (mixed)    [ControllerClass::class, 'method'] or any callable.
 *   middleware  (string[]) FQCN list executed sequentially before the handler.
 *   parameters  (array)    Per-param validation rules (see ParameterValidator).
 *   purpose     (string)   Optional human-readable description (ignored at runtime).
 *
 * The loader wraps the callback, middleware pipeline, and parameter validator
 * into a single closure that the Router stores as the route handler.  The
 * closure's signature is compatible with the standard Router dispatch flow:
 * it receives the array of path-matched parameters, then builds a Request,
 * validates all parameters, runs the middleware pipeline, and finally invokes
 * the controller callback with the fully-populated Request object.
 *
 * Config-Driven Paths
 * -------------------
 * A path value of the form  config[namespace.dotted.key]  is resolved against
 * the $config array passed to loadInto() / loadFromFile().  Nested keys are
 * dot-separated.  A RuntimeException is thrown when the key cannot be found.
 *
 * Usage
 * -----
 *   $loader = new RouteLoader();
 *   $loader->loadFromFile($router, __DIR__ . '/routes.php', $appConfig);
 *
 * Or via the Router convenience methods:
 *   $router->loadFromFile(__DIR__ . '/routes.php', $appConfig);
 *   $router->loadFromArray($definitions, $appConfig);
 */
class RouteLoader
{
    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Register all definitions from $definitions into $router.
     *
     * @param Router                         $router      Target router.
     * @param array<int,array<string,mixed>> $definitions Route definition array.
     * @param array<string,mixed>            $config      Host-application config.
     */
    public function loadInto(Router $router, array $definitions, array $config = []): void
    {
        foreach ($definitions as $definition) {
            $this->registerDefinition($router, $definition, $config);
        }
    }

    /**
     * Require a PHP file that returns a route definition array and register it.
     *
     * @param Router              $router   Target router.
     * @param string              $filePath Absolute path to the definitions file.
     * @param array<string,mixed> $config   Host-application config.
     *
     * @throws InvalidArgumentException When the file does not return an array.
     */
    public function loadFromFile(Router $router, string $filePath, array $config = []): void
    {
        // phpcs:ignore
        $definitions = require $filePath;

        if (!is_array($definitions)) {
            throw new InvalidArgumentException(
                "Route file must return an array of route definitions: {$filePath}",
            );
        }

        $this->loadInto($router, $definitions, $config);
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    /**
     * Parse a single route definition and register it with the router.
     *
     * @param array<string,mixed> $def
     * @param array<string,mixed> $config
     */
    private function registerDefinition(Router $router, array $def, array $config): void
    {
        $method     = strtoupper((string) ($def['method']  ?? 'GET'));
        $rawPath    = (string) ($def['path']     ?? '/');
        $callback   = $def['callback']   ?? null;
        $middleware = (array) ($def['middleware'] ?? []);
        $parameters = (array) ($def['parameters'] ?? []);
        $name       = (string) ($def['name']      ?? '');

        // Routes without a callback (e.g. handoff/redirect stubs) are skipped.
        if ($callback === null) {
            return;
        }

        $path = $this->resolvePath($rawPath, $config);

        // Build per-param regex map from parameter definitions so that the
        // Route can compile the correct capturing group for each {param}.
        $paramRegexMap = $this->buildParamRegexMap($parameters);

        $handler = $this->buildHandler($callback, $middleware, $parameters);

        $router->registerRoute($method, $path, $handler, $paramRegexMap, $name);
    }

    /**
     * Extract custom regex strings from parameter definitions.
     *
     * Only parameters with source=path and a 'regex' rule are included.
     *
     * @param  array<string,array<string,mixed>> $parameters
     * @return array<string,string>
     */
    private function buildParamRegexMap(array $parameters): array
    {
        $map = [];
        foreach ($parameters as $name => $rules) {
            if (isset($rules['regex']) && ($rules['source'] ?? '') === 'path') {
                $map[$name] = (string) $rules['regex'];
            }
        }
        return $map;
    }

    /**
     * Wrap the controller callback, middleware pipeline, and parameter
     * validator into a single handler closure.
     *
     * The returned callable has the signature  (array $pathParams): mixed,
     * which is compatible with the Router's standard dispatch flow.
     *
     * @param mixed    $callback          Controller callback.
     * @param string[] $middlewareClasses Middleware FQCN list.
     * @param array<string,array<string,mixed>> $parameters Route parameter definitions.
     */
    private function buildHandler(mixed $callback, array $middlewareClasses, array $parameters): callable
    {
        return function (array $pathParams) use ($callback, $middlewareClasses, $parameters): mixed {
            // Build the request from globals and attach path params.
            $request = Request::fromGlobals()->withPathParams($pathParams);

            // Validate all parameters (path + query + body) when definitions exist.
            if ($parameters !== []) {
                $input     = array_merge($request->getQueryParams(), $request->getBodyParams(), $pathParams);
                $validator = new ParameterValidator();
                $validated = $validator->validate($parameters, $input);

                // Merge validated (type-cast) values back so the controller
                // receives clean, typed data.
                $request = $request
                    ->withPathParams(array_merge($pathParams, array_intersect_key($validated, $pathParams)))
                    ->withBodyParams(array_merge($request->getBodyParams(), $validated));
            }

            $destination = $this->resolveCallback($callback);

            if ($middlewareClasses !== []) {
                $pipeline = new MiddlewarePipeline($middlewareClasses);
                return $pipeline->run($request, static fn(Request $req) => $destination($req));
            }

            return $destination($request);
        };
    }

    /**
     * Normalise a callback value into an invokable callable.
     *
     * Supported forms:
     *   - Any native PHP callable (closure, function name, etc.)
     *   - [ClassName::class, 'method']  — instantiated at call time
     *   - [$object, 'method']           — already-instantiated object
     *
     * @throws InvalidArgumentException When the callback cannot be resolved.
     */
    private function resolveCallback(mixed $callback): callable
    {
        if (is_callable($callback)) {
            return $callback;
        }

        if (is_array($callback) && count($callback) === 2) {
            [$classOrObject, $method] = $callback;
            $instance = is_string($classOrObject) ? new $classOrObject() : $classOrObject;
            return [$instance, $method];
        }

        throw new InvalidArgumentException('Invalid route callback: must be a callable or [ClassName, method] pair.');
    }

    /**
     * Resolve a config-driven path of the form  config[namespace.key].
     *
     * Regular paths are returned unchanged.
     *
     * @param string              $path
     * @param array<string,mixed> $config
     *
     * @throws RuntimeException When a config key is not found.
     */
    private function resolvePath(string $path, array $config): string
    {
        if (!preg_match('/^config\[([^\]]+)\]$/', $path, $matches)) {
            return $path;
        }

        $keyPath = explode('.', $matches[1]);
        $value   = $config;

        foreach ($keyPath as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                throw new RuntimeException(
                    "Route config path key '{$matches[1]}' not found in the provided config array.",
                );
            }
            $value = $value[$key];
        }

        return (string) $value;
    }
}

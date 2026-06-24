<?php

declare(strict_types=1);

/**
 * =========================================================================
 * GENERIC ROUTE DEFINITION TEMPLATE FOR COMPOSER PACKAGES
 *
 * This file is loaded by RouteLoader::loadFromFile() or
 * Router::loadFromFile().  It must return a plain PHP array; no framework
 * bootstrap is required.
 *
 * Each element defines one route.  Supported keys:
 *
 *   name        (string)   Logical name used for reverse-routing lookups.
 *   method      (string)   HTTP verb: GET | POST | PUT | PATCH | DELETE.
 *   path        (string)   URL pattern.  Dynamic segments use {paramName}.
 *                          Config-driven: config[namespace.key]
 *   callback    (array)    [ControllerClass::class, 'method']
 *   middleware  (string[]) FQCN list executed sequentially before handler.
 *   parameters  (array)    Per-param validation rules (see ParameterValidator).
 *   purpose     (string)   Optional human-readable description (runtime no-op).
 *
 * Parameter rule keys:
 *   type      string | integer | float | boolean | array
 *   required  true (default) | false
 *   default   fallback value when param is absent
 *   source    path | query | body | auto (default)
 *   regex     regex fragment applied to path params (no delimiters or anchors)
 *   structure per-key type map for 'array' typed parameters
 *
 * Usage:
 *
 *   use zRoute\Router;
 *
 *   $router = new Router();
 *   $router->loadFromFile(__DIR__ . '/routes.php', $appConfig);
 *   $router->run();
 * =========================================================================
 */

use zRoute\Examples\Controllers\ExampleProcessController;
use zRoute\Examples\Controllers\ExampleResourceController;

return [

    // -------------------------------------------------------------------------
    // ROUTE: Resource Index (GET)
    // Features: optional query parameters, default values, middleware
    // -------------------------------------------------------------------------
    [
        'name'       => 'package.resource.index',
        'method'     => 'GET',
        'path'       => '/package-prefix/resources',
        'callback'   => [ExampleResourceController::class, 'index'],
        'middleware' => [
            'zRoute\Examples\Middleware\AuthenticatePackageUser',
        ],
        'parameters' => [
            'page'     => ['type' => 'integer', 'required' => false, 'default' => 1],
            'per_page' => ['type' => 'integer', 'required' => false, 'default' => 15],
            'filter'   => ['type' => 'string',  'required' => false],
        ],
    ],

    // -------------------------------------------------------------------------
    // ROUTE: Dynamic Path Parameter (GET)
    // Features: {id} URI segment, numeric-only regex constraint
    // -------------------------------------------------------------------------
    [
        'name'       => 'package.resource.show',
        'method'     => 'GET',
        'path'       => '/package-prefix/resources/{id}',
        'callback'   => [ExampleResourceController::class, 'show'],
        'middleware' => [],
        'parameters' => [
            'id' => [
                'type'   => 'integer',
                'source' => 'path',
                'regex'  => '[0-9]+',
            ],
        ],
    ],

    // -------------------------------------------------------------------------
    // ROUTE: Data Submission (POST)
    // Features: required fields, boolean typing, nested array structure
    // -------------------------------------------------------------------------
    [
        'name'       => 'package.resource.store',
        'method'     => 'POST',
        'path'       => '/package-prefix/resources',
        'callback'   => [ExampleResourceController::class, 'store'],
        'middleware' => [
            'zRoute\Examples\Middleware\VerifyCsrfToken',
        ],
        'parameters' => [
            'action_type' => ['type' => 'string',  'required' => true],
            'is_active'   => ['type' => 'boolean', 'required' => true],
            'meta_data'   => [
                'type'      => 'array',
                'required'  => false,
                'structure' => [
                    'reference_key' => 'string',
                    'retry_count'   => 'integer',
                ],
            ],
        ],
    ],

    // -------------------------------------------------------------------------
    // ROUTE: Resource Update (PUT)
    // -------------------------------------------------------------------------
    [
        'name'       => 'package.resource.update',
        'method'     => 'PUT',
        'path'       => '/package-prefix/resources/{id}',
        'callback'   => [ExampleResourceController::class, 'update'],
        'middleware' => [
            'zRoute\Examples\Middleware\AuthenticatePackageUser',
            'zRoute\Examples\Middleware\VerifyCsrfToken',
        ],
        'parameters' => [
            'id'          => ['type' => 'integer', 'source' => 'path', 'regex' => '[0-9]+'],
            'action_type' => ['type' => 'string',  'required' => false],
            'is_active'   => ['type' => 'boolean', 'required' => false],
        ],
    ],

    // -------------------------------------------------------------------------
    // ROUTE: Resource Deletion (DELETE)
    // Features: mutating HTTP verb, admin authorisation middleware
    // -------------------------------------------------------------------------
    [
        'name'       => 'package.resource.destroy',
        'method'     => 'DELETE',
        'path'       => '/package-prefix/resources/{id}',
        'callback'   => [ExampleResourceController::class, 'destroy'],
        'middleware' => [
            'zRoute\Examples\Middleware\AuthorizePackageAdmin',
        ],
        'parameters' => [
            'id' => ['type' => 'integer', 'source' => 'path', 'regex' => '[0-9]+'],
        ],
    ],

    // -------------------------------------------------------------------------
    // ROUTE: Process Trigger (POST)
    // Features: strict body payload, uses a different controller
    // -------------------------------------------------------------------------
    [
        'name'       => 'package.process.run',
        'method'     => 'POST',
        'path'       => '/package-prefix/process',
        'callback'   => [ExampleProcessController::class, 'run'],
        'middleware' => [
            'zRoute\Examples\Middleware\AuthenticatePackageUser',
            'zRoute\Examples\Middleware\VerifyCsrfToken',
        ],
        'parameters' => [
            'job_type'     => ['type' => 'string',  'required' => true],
            'async'        => ['type' => 'boolean', 'required' => false, 'default' => false],
            'payload'      => [
                'type'      => 'array',
                'required'  => false,
                'structure' => [
                    'source_id'    => 'integer',
                    'callback_url' => 'string',
                ],
            ],
        ],
    ],

    // -------------------------------------------------------------------------
    // ROUTE: Host Application Handoff / Redirect
    // Features: config-driven path resolved at load time from $appConfig
    //
    // Load with:
    //   $router->loadFromFile(__DIR__ . '/routes.php', [
    //       'package_config_namespace' => [
    //           'redirect_target_route' => '/dashboard',
    //       ],
    //   ]);
    //
    // NOTE: 'callback' is omitted intentionally — the RouteLoader skips this
    // entry unless a callable is provided.  Add a callback to activate it.
    // -------------------------------------------------------------------------
    [
        'name'    => 'package.handoff.exit',
        'method'  => 'GET',
        'path'    => 'config[package_config_namespace.redirect_target_route]',
        'purpose' => 'Redirects the user back to the host application after the package cycle completes.',
        'parameters' => [
            'status'       => ['type' => 'string', 'required' => false],
            'reference_id' => ['type' => 'string', 'required' => false],
        ],
        // 'callback' => [SomeController::class, 'handoff'],  // Uncomment to activate.
    ],

];

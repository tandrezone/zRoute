<?php

declare(strict_types=1);

namespace zRoute\Examples\Controllers;

use zRoute\Request;

/**
 * Example resource controller demonstrating how route callbacks are invoked.
 *
 * Each method receives a fully-populated {@see Request} object whose
 * parameters have already been validated and type-cast by ParameterValidator.
 *
 * In a real application these methods would query a database, build a response
 * object, etc.  Here they simply return descriptive strings so the examples
 * can be exercised without any external dependencies.
 */
class ExampleResourceController
{
    /**
     * GET /package-prefix/resources
     *
     * Lists resources.  Query params: page (int), per_page (int), filter (string).
     */
    public function index(Request $request): string
    {
        $page    = $request->getParam('page', 1);
        $perPage = $request->getParam('per_page', 15);
        $filter  = $request->getParam('filter', '');

        return sprintf(
            'index: page=%s per_page=%s filter=%s',
            $page,
            $perPage,
            $filter,
        );
    }

    /**
     * GET /package-prefix/resources/{id}
     *
     * Shows a single resource identified by {id}.
     */
    public function show(Request $request): string
    {
        return 'show: id=' . $request->getParam('id');
    }

    /**
     * POST /package-prefix/resources
     *
     * Stores a new resource.  Body params: action_type, is_active, meta_data.
     */
    public function store(Request $request): string
    {
        $actionType = $request->getParam('action_type');
        $isActive   = $request->getParam('is_active') ? 'true' : 'false';

        return "store: action_type={$actionType} is_active={$isActive}";
    }

    /**
     * PUT /package-prefix/resources/{id}
     *
     * Updates an existing resource.
     */
    public function update(Request $request): string
    {
        return 'update: id=' . $request->getParam('id');
    }

    /**
     * DELETE /package-prefix/resources/{id}
     *
     * Deletes a resource identified by {id}.
     */
    public function destroy(Request $request): string
    {
        return 'destroy: id=' . $request->getParam('id');
    }
}

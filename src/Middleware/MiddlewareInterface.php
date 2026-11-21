<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Middleware;

/**
 * Runs around an action, after the permission check.
 *
 * A middleware calls `$next($request)` to continue with the next middleware
 * (or the action), or returns its own response to short-circuit the pipeline.
 * Exceptions thrown by the action go up through the middlewares before being
 * turned into the standard error response.
 */
interface MiddlewareInterface
{
    /**
     * @param \WP_REST_Request $request
     * @param callable(\WP_REST_Request): \WP_REST_Response $next
     * @return \WP_REST_Response
     */
    public function process(\WP_REST_Request $request, callable $next): \WP_REST_Response;
}

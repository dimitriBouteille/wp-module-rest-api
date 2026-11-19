<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares;

use Dbout\WpRestApi\Middleware\MiddlewareInterface;

/**
 * Turns any exception of the next steps into a 409 response.
 */
class CatchingMiddleware implements MiddlewareInterface
{
    public function process(\WP_REST_Request $request, callable $next): \WP_REST_Response
    {
        try {
            return $next($request);
        } catch (\Throwable $exception) {
            return new \WP_REST_Response(['caught' => $exception->getMessage()], 409);
        }
    }
}

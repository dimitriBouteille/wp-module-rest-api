<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares;

use Dbout\WpRestApi\Middleware\MiddlewareInterface;

class SetParamMiddleware implements MiddlewareInterface
{
    public function process(\WP_REST_Request $request, callable $next): \WP_REST_Response
    {
        $request->set_param('name', 'from-middleware');

        return $next($request);
    }
}

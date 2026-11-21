<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Container;

use Dbout\WpRestApi\Middleware\MiddlewareInterface;

class MiddlewareWithDependency implements MiddlewareInterface
{
    public function __construct(private readonly Greeter $greeter)
    {
    }

    public function process(\WP_REST_Request $request, callable $next): \WP_REST_Response
    {
        $response = $next($request);
        $response->header('X-Greeting', $this->greeter->greeting);

        return $response;
    }
}

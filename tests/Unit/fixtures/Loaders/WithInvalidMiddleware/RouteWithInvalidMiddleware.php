<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithInvalidMiddleware;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Middleware;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route('loader-test/v1', '/invalid-middleware')]
class RouteWithInvalidMiddleware
{
    #[Action(Method::GET)]
    #[Middleware(\stdClass::class)]
    public function get(): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }
}

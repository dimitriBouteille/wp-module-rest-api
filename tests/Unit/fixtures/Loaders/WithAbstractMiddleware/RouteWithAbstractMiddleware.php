<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithAbstractMiddleware;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Middleware;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;
use Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares\AbstractMiddleware;

#[Middleware(AbstractMiddleware::class)]
#[Route('loader-test/v1', '/abstract-middleware')]
class RouteWithAbstractMiddleware
{
    #[Action(Method::GET)]
    public function get(): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }
}

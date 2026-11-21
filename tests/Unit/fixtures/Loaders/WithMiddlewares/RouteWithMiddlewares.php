<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithMiddlewares;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Middleware;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;
use Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares\FirstMiddleware;
use Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares\SecondMiddleware;
use Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares\ThirdMiddleware;

#[Middleware(FirstMiddleware::class)]
#[Middleware(SecondMiddleware::class)]
#[Route('loader-test/v1', '/middlewares')]
class RouteWithMiddlewares
{
    #[Action(Method::GET)]
    #[Middleware(ThirdMiddleware::class)]
    public function list(): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }

    #[Action(Method::POST)]
    public function create(): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }
}

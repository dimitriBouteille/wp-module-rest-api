<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithConstructorDependencies;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Middleware;
use Dbout\WpRestApi\Attributes\Resolve;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;
use Dbout\WpRestApi\Tests\Unit\fixtures\Container\Greeter;
use Dbout\WpRestApi\Tests\Unit\fixtures\Container\MiddlewareWithDependency;
use Dbout\WpRestApi\Tests\Unit\fixtures\Container\PermissionWithDependency;
use Dbout\WpRestApi\Tests\Unit\fixtures\Container\ResolverWithDependency;

/**
 * Every class created on a request needs a constructor argument: the
 * controller, its middleware, its permission and its resolver.
 */
#[Middleware(MiddlewareWithDependency::class)]
#[Route('loader-test/v1', '/constructor-dependencies')]
class RouteWithConstructorDependencies
{
    public function __construct(private readonly Greeter $greeter)
    {
    }

    #[Action(Method::GET, permissionCallback: PermissionWithDependency::class)]
    public function get(#[Resolve(ResolverWithDependency::class)] string $value): \WP_REST_Response
    {
        return new \WP_REST_Response(['value' => $value, 'greeting' => $this->greeter->greeting]);
    }
}

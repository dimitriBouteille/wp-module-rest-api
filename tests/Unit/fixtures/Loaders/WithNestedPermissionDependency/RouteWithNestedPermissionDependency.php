<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithNestedPermissionDependency;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;
use Dbout\WpRestApi\Permissions\AnyOf;
use Dbout\WpRestApi\Permissions\IsPublic;
use Dbout\WpRestApi\Tests\Unit\fixtures\Container\PermissionWithDependency;

#[Route('loader-test/v1', '/nested-permission', permissionCallback: new AnyOf(IsPublic::class, PermissionWithDependency::class))]
class RouteWithNestedPermissionDependency
{
    #[Action(Method::GET)]
    public function get(): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }
}

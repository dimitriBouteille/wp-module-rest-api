<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithComposedPermissions;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;
use Dbout\WpRestApi\Permissions\AllOf;
use Dbout\WpRestApi\Permissions\AnyOf;
use Dbout\WpRestApi\Permissions\HasCapability;
use Dbout\WpRestApi\Permissions\IsPublic;
use Dbout\WpRestApi\Tests\Unit\fixtures\AlwaysDenyPermission;
use Dbout\WpRestApi\Tests\Unit\fixtures\PermissionCallbacks;

#[Route(
    'loader-test/v1',
    '/composed',
    permissionCallback: new AnyOf(
        AlwaysDenyPermission::class,
        new AllOf(new IsPublic(), [PermissionCallbacks::class, 'allow']),
    ),
)]
class RouteWithComposedPermissions
{
    #[Action(Method::GET)]
    public function inherited(): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }

    #[Action(Method::POST, permissionCallback: new HasCapability('edit_post', 12))]
    public function capability(): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }
}

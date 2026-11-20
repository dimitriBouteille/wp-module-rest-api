<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\WordPress\fixtures\ComposedPermissionRoute;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;
use Dbout\WpRestApi\Permissions\AllOf;
use Dbout\WpRestApi\Permissions\AnyOf;
use Dbout\WpRestApi\Permissions\HasCapability;
use Dbout\WpRestApi\Permissions\HasRole;
use Dbout\WpRestApi\Permissions\IsLoggedIn;

#[Route('integration/v1', '/composed', permissionCallback: new AllOf(new IsLoggedIn(), new HasCapability('manage_options')))]
class ComposedPermissionRoute
{
    #[Action(Method::GET)]
    public function settings(): \WP_REST_Response
    {
        return new \WP_REST_Response(['settings' => true]);
    }

    #[Action(Method::GET, permissionCallback: new AnyOf(new HasRole('editor'), new HasCapability('manage_options')), path: '/reports')]
    public function reports(): \WP_REST_Response
    {
        return new \WP_REST_Response(['reports' => true]);
    }
}

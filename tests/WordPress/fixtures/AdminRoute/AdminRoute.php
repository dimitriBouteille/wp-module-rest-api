<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\WordPress\fixtures\AdminRoute;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;
use Dbout\WpRestApi\Permissions\IsAdministrator;

#[Route('integration/v1', '/admin/secret', permissionCallback: IsAdministrator::class)]
class AdminRoute
{
    #[Action(Method::GET)]
    public function secret(): \WP_REST_Response
    {
        return new \WP_REST_Response(['secret' => 42]);
    }
}

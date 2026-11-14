<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\WordPress\fixtures\PublicActionRoute;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;
use Dbout\WpRestApi\Permissions\IsAdministrator;
use Dbout\WpRestApi\Permissions\IsPublic;

#[Route('integration/v1', '/admin/status', permissionCallback: IsAdministrator::class)]
class PublicActionRoute
{
    #[Action(Method::GET, permissionCallback: IsPublic::class)]
    public function status(): \WP_REST_Response
    {
        return new \WP_REST_Response(['status' => 'ok']);
    }

    #[Action(Method::POST)]
    public function update(): \WP_REST_Response
    {
        return new \WP_REST_Response(['updated' => true]);
    }
}

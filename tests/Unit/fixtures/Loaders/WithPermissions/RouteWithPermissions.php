<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithPermissions;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;
use Dbout\WpRestApi\Permissions\IsAdministrator;
use Dbout\WpRestApi\Permissions\IsEditor;
use Dbout\WpRestApi\Permissions\IsPublic;

#[Route('loader-test/v1', '/permissions', permissionCallback: IsAdministrator::class)]
class RouteWithPermissions
{
    #[Action(Method::GET)]
    public function inherited(): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }

    #[Action(Method::POST, permissionCallback: IsPublic::class)]
    public function public(): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }

    #[Action(Method::PUT, permissionCallback: IsEditor::class)]
    public function overridden(): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }

    #[Action(Method::DELETE, permissionCallback: 'is_user_logged_in')]
    public function callable(): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }
}

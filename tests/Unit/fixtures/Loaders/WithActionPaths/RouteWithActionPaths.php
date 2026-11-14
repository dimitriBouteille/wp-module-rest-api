<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithActionPaths;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;
use Dbout\WpRestApi\Permissions\IsAdministrator;

#[Route('loader-test/v1', '/documents/')]
class RouteWithActionPaths
{
    #[Action(Method::GET)]
    public function list(): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }

    #[Action(Method::GET, path: '/(?P<id>\d+)')]
    public function get(int $id): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }

    #[Action(Method::POST, path: '/')]
    public function create(): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }

    #[Action(Method::DELETE, permissionCallback: IsAdministrator::class, path: '(?P<id>\d+)')]
    public function delete(int $id): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }
}

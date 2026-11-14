<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithDuplicateActionPath;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route('loader-test/v1', '/documents')]
class RouteWithDuplicateActionPath
{
    #[Action(Method::GET, path: '/(?P<id>\d+)')]
    public function get(int $id): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }

    #[Action(Method::GET, path: '(?P<id>\d+)/')]
    public function read(int $id): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }
}

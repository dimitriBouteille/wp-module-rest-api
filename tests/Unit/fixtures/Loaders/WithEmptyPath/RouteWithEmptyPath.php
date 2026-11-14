<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithEmptyPath;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route('loader-test/v1', '/')]
class RouteWithEmptyPath
{
    #[Action(Method::GET, path: '/{id:\d+}')]
    public function get(int $id): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }

    #[Action(Method::GET)]
    public function list(): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }
}

<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithIntParams;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Param;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route('loader-test/v1', '/offsets')]
class RouteWithIntParams
{
    #[Action(Method::GET)]
    public function list(
        #[Param]
        int $offset = 0,
        #[Param(sanitizeCallback: 'absint')]
        int $page = 1,
    ): \WP_REST_Response {
        return new \WP_REST_Response(['offset' => $offset, 'page' => $page]);
    }
}

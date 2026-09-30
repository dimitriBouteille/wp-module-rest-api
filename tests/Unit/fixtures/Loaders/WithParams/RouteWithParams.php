<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithParams;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Param;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route('loader-test/v1', '/items')]
class RouteWithParams
{
    #[Action(Method::GET)]
    public function show(
        #[Param(required: true, description: 'Item identifier')]
        int $id,
        #[Param(default: 10)]
        int $perPage = 10,
        #[Param(name: 'q')]
        ?string $search = null,
    ): \WP_REST_Response {
        return new \WP_REST_Response(['id' => $id, 'perPage' => $perPage, 'search' => $search]);
    }
}
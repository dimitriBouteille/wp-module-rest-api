<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithEnumParam;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Param;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\SortDirection;

#[Route('loader-test/v1', '/sorted')]
class RouteWithEnumParam
{
    #[Action(Method::GET)]
    public function list(
        #[Param(required: true)]
        SortDirection $direction,
    ): \WP_REST_Response {
        return new \WP_REST_Response(['direction' => $direction->value]);
    }
}
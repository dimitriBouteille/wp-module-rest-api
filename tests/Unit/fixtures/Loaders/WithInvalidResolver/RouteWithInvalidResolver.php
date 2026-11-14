<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithInvalidResolver;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Resolve;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route('loader-test/v1', '/invalid-resolver')]
class RouteWithInvalidResolver
{
    #[Action(Method::GET)]
    public function get(#[Resolve(\stdClass::class)] mixed $value): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }
}

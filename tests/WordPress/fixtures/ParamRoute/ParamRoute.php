<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\WordPress\fixtures\ParamRoute;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Param;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route('integration/v1', '/articles')]
class ParamRoute
{
    #[Action(Method::GET)]
    public function show(
        #[Param(required: true, description: 'Article identifier')]
        int $id,
    ): \WP_REST_Response {
        return new \WP_REST_Response(['id' => $id]);
    }
}
<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\WordPress\fixtures\PathParamRoute;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route('integration/v1', '/items/(?P<id>\d+)')]
class PathParamRoute
{
    #[Action(Method::GET)]
    public function show(int $id): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'id' => $id,
            'type' => get_debug_type($id),
        ]);
    }
}

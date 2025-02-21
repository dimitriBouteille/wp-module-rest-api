<?php
/**
 * Copyright (c) 2024 Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace App\Routes;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route(
    namespace: 'app/v2',
    route: 'document/(?P<documentId>\d+)'
)]
class MyRoute
{
    #[Action(Method::GET)]
    public function get(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
        ]);
    }
}

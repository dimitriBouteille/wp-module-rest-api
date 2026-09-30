<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures;

class RouteWithRequestParam
{
    public function execute(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'method' => $request->get_method(),
            'received' => $request instanceof \WP_REST_Request,
        ]);
    }
}

<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures;

class RouteReturningResponse
{
    public function execute(): \WP_REST_Response
    {
        return new \WP_REST_Response(['ok' => true], 200);
    }
}

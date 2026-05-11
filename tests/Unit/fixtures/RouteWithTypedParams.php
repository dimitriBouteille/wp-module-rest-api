<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures;

class RouteWithTypedParams
{
    public function execute(int $id, string $sort): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'id' => $id,
            'idType' => get_debug_type($id),
            'sort' => $sort,
            'sortType' => get_debug_type($sort),
        ]);
    }
}

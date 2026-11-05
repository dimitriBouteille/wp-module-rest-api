<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures;

class RouteWithInjectionCases
{
    public function optional(string $search = '', string $format = 'json'): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'search' => $search,
            'format' => $format,
        ]);
    }

    public function required(int $id): \WP_REST_Response
    {
        return new \WP_REST_Response(['id' => $id]);
    }

    public function nullable(?int $id): \WP_REST_Response
    {
        return new \WP_REST_Response(['id' => $id]);
    }

    public function enums(StringStatus $status, IntPriority $priority = IntPriority::Low): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'status' => $status,
            'priority' => $priority,
        ]);
    }

    public function boolean(bool $enabled): \WP_REST_Response
    {
        return new \WP_REST_Response(['enabled' => $enabled]);
    }

    public function requestInTheMiddle(int $id, \WP_REST_Request $request, string $sort = 'asc'): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'id' => $id,
            'method' => $request->get_method(),
            'sort' => $sort,
        ]);
    }
}

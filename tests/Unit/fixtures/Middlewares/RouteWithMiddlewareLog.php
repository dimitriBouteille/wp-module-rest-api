<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares;

class RouteWithMiddlewareLog
{
    public function execute(): \WP_REST_Response
    {
        RecordingMiddleware::$log[] = 'action';

        return new \WP_REST_Response(['ok' => true]);
    }

    public function withName(string $name): \WP_REST_Response
    {
        return new \WP_REST_Response(['name' => $name]);
    }

    public function fail(): never
    {
        throw new \RuntimeException('Action failed.');
    }
}

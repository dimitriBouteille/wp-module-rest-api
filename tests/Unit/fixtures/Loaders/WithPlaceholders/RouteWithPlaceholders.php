<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithPlaceholders;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route('loader-test/v1', '/countries')]
class RouteWithPlaceholders
{
    #[Action(Method::GET, path: '/{code:[A-Z]{3}}')]
    public function get(string $code): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }

    /**
     * Raw regex matching the placeholder of get().
     */
    #[Action(Method::PUT, path: '/(?P<code>[A-Z]{3})')]
    public function update(string $code): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }

    #[Action(Method::GET, path: '/{code:[A-Z]{3}}/cities/{city}')]
    public function city(string $code, string $city): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }
}

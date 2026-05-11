<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\WordPress\fixtures\MultiMethodRoute;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route('integration/v1', '/resources')]
class MultiMethodRoute
{
    #[Action(Method::GET)]
    public function list(): \WP_REST_Response
    {
        return new \WP_REST_Response(['action' => 'list']);
    }

    #[Action(Method::POST)]
    public function create(): \WP_REST_Response
    {
        return new \WP_REST_Response(['action' => 'create'], 201);
    }
}

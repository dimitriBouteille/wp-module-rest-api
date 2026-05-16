<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\NamespaceLoader\Pings;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route('namespace-loader/v1', '/pong')]
class PongRoute
{
    #[Action(Method::GET)]
    public function pong(): \WP_REST_Response
    {
        return new \WP_REST_Response(['ping' => true]);
    }
}
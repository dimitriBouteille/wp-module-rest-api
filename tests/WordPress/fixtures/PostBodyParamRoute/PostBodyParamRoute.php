<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\WordPress\fixtures\PostBodyParamRoute;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Param;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route('integration/v1', '/articles/create')]
class PostBodyParamRoute
{
    #[Action(Method::POST)]
    public function create(
        #[Param(required: true)]
        int $id,
        #[Param(required: true)]
        string $title,
    ): \WP_REST_Response {
        return new \WP_REST_Response(['id' => $id, 'title' => $title], 201);
    }
}
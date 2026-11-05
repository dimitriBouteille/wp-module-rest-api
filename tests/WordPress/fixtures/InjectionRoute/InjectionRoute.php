<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\WordPress\fixtures\InjectionRoute;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route('integration/v1', '/search')]
class InjectionRoute
{
    #[Action(Method::GET)]
    public function search(string $query = '', string $format = 'json', bool $exact = false): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'query' => $query,
            'format' => $format,
            'exact' => $exact,
        ]);
    }
}

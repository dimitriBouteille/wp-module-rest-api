<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\WordPress\fixtures\PlaceholderRoute;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route('integration/v1', '/articles/{id:\d+}')]
class PlaceholderRoute
{
    /**
     * @return array<string, int|string>
     */
    #[Action(Method::GET, path: '/comments/{slug}')]
    public function comment(int $id, string $slug): array
    {
        return ['id' => $id, 'slug' => $slug];
    }
}

<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\WordPress\fixtures\ReturnValueRoute;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route('integration/v1', '/return-values')]
class ReturnValueRoute
{
    /**
     * @return array<string, bool>
     */
    #[Action(Method::GET)]
    public function get(): array
    {
        return ['ok' => true];
    }

    #[Action(Method::DELETE)]
    public function delete(): \WP_Error
    {
        return new \WP_Error('not-found', 'Document not found.', ['status' => 404]);
    }
}

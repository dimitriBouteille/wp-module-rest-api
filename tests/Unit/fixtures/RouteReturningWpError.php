<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures;

class RouteReturningWpError
{
    public function execute(): \WP_Error
    {
        return new \WP_Error('forbidden_action', 'You may not do this.', ['details' => 'no']);
    }
}

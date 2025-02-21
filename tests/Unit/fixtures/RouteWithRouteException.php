<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\fixtures;

use Dbout\WpRestApi\Exceptions\RouteException;

class RouteWithRouteException
{
    /**
     * @throws \Exception
     * @return never
     */
    public function execute(): never
    {
        throw new RouteException('My route exception.');
    }
}

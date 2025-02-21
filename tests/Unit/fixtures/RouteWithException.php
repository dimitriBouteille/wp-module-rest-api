<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures;

class RouteWithException
{
    /**
     * @return never
     * @throws \Exception
     */
    public function execute(): never
    {
        throw new \Exception('My custom exception.');
    }
}

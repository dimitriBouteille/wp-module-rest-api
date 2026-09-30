<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures;

class RouteWithFatalError
{
    /**
     * @return never
     * @throws \TypeError
     */
    public function execute(): never
    {
        throw new \TypeError('My custom type error.');
    }
}

<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Container;

/**
 * Dependency injected through the constructors of the classes below.
 */
class Greeter
{
    public function __construct(public readonly string $greeting)
    {
    }
}

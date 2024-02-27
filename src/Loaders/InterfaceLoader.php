<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Loaders;

interface InterfaceLoader
{
    /**
     * Loads a resource.
     *
     * @param mixed $resource The resource
     * @throws \Exception If something went wrong
     * @return mixed
     */
    public function load(mixed $resource): mixed;
}

<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi;

class Route
{
    /**
     * @param string $namespace
     * @param string $path
     * @param array<RouteAction> $actions
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $path,
        public readonly array $actions
    ) {
    }
}

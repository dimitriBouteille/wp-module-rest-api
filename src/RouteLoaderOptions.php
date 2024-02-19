<?php
/**
 * Copyright (c) 2024 Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi;

use Psr\Cache\CacheItemPoolInterface;

class RouteLoaderOptions
{
    /**
     * @param CacheItemPoolInterface|null $cache
     */
    public function __construct(
        public ?CacheItemPoolInterface $cache = null,
    ) {
    }
}

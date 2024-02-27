<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi;

use Psr\Cache\CacheItemPoolInterface;

class RouteLoaderOptions
{
    final public const DEFAULT_CACHE_KEY = 'wp_autoloader_routes';

    /**
     * @param CacheItemPoolInterface|null $cache
     * @param string $cacheKey
     */
    public function __construct(
        public ?CacheItemPoolInterface $cache = null,
        public string $cacheKey = self::DEFAULT_CACHE_KEY,
    ) {
    }
}

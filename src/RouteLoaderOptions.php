<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi;

use Dbout\WpRestApi\ErrorFormat\ErrorFormatterInterface;
use Psr\Cache\CacheItemPoolInterface;

class RouteLoaderOptions
{
    final public const DEFAULT_CACHE_KEY = 'wp_autoloader_routes';

    /**
     * @param CacheItemPoolInterface|null $cache
     * @param string $cacheKey
     * @param bool $debug MUST NOT be enabled in production: forwards the raw
     *                    message of non-RouteException errors and, when
     *                    WP_DEBUG is also true, attaches the stack trace.
     * @param ErrorFormatterInterface|null $errorFormatter Optional pluggable
     *                    error response shape. Defaults to the historical
     *                    {error: {code, message, data}} envelope.
     * @param string $cacheVersion Added to the cache key, for example a release
     *                    number or a git SHA: changing it rebuilds the cache.
     * @param bool $cacheFingerprint Adds the path, modification time and size of
     *                    the route files to the cache key, so any change rebuilds
     *                    the cache. Costs a directory scan on each request.
     */
    public function __construct(
        public ?CacheItemPoolInterface $cache = null,
        public string $cacheKey = self::DEFAULT_CACHE_KEY,
        public bool $debug = false,
        public ?ErrorFormatterInterface $errorFormatter = null,
        public string $cacheVersion = '',
        public bool $cacheFingerprint = false,
    ) {
    }
}

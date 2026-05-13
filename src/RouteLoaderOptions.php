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
     */
    public function __construct(
        public ?CacheItemPoolInterface $cache = null,
        public string $cacheKey = self::DEFAULT_CACHE_KEY,
        public bool $debug = false,
        public ?ErrorFormatterInterface $errorFormatter = null,
    ) {
    }
}

<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi;

use Dbout\WpRestApi\Exceptions\ApiException;

class Route
{
    /** @var non-falsy-string */
    public readonly string $namespace;

    /** @var non-falsy-string */
    public readonly string $path;

    /**
     * @param string $namespace
     * @param string $path
     * @param array<RouteAction> $actions
     * @throws ApiException When the namespace or the path is empty, as
     *                      register_rest_route would silently ignore the route.
     */
    public function __construct(
        string $namespace,
        string $path,
        public readonly array $actions
    ) {
        if ($namespace === '' || $namespace === '0') {
            throw new ApiException(sprintf('The route namespace cannot be empty (path: %s).', $path));
        }

        if ($path === '' || $path === '0') {
            throw new ApiException(sprintf('The route path cannot be empty (namespace: %s).', $namespace));
        }

        $this->namespace = $namespace;
        $this->path = $path;
    }
}

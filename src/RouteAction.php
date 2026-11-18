<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi;

class RouteAction
{
    /**
     * @param mixed $className
     * @param string $methodName
     * @param array<string> $methods
     * @param mixed $permissionCallback
     * @param array<ParamSpec> $params Compiled WP argument schema. Empty when
     *                                 the handler declares no #[Param] attribute.
     * @param list<string> $middlewares #[Middleware] classes of the class
     *                                 then of the method, in run order.
     *                                 Checked again when run, as they can
     *                                 come from the cache.
     */
    public function __construct(
        public readonly mixed $className,
        public readonly string $methodName,
        public readonly array $methods,
        public readonly mixed $permissionCallback,
        public readonly array $params = [],
        public readonly array $middlewares = [],
    ) {
    }
}

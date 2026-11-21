<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Attributes;

/**
 * @see https://developer.wordpress.org/reference/functions/register_rest_route/
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Route
{
    /**
     * @param string $namespace The first URL segment after core prefix. Should be unique to your package/plugin.
     * @param string $route The base URL for route you are adding. Accepts placeholders such as '/documents/{id:\d+}'.
     * @param mixed $permissionCallback Checks if the user can perform the action before the real callback is called:
     *                                  a PermissionInterface class name, a permission object such as
     *                                  `new HasCapability('manage_options')` or `new AnyOf(...)`, or a callable.
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $route,
        public readonly mixed $permissionCallback = null,
    ) {
    }
}

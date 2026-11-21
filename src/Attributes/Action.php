<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Attributes;

use Dbout\WpRestApi\Enums\Method;

/**
 * @see https://developer.wordpress.org/rest-api/requests/#attributes
 * @see https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class Action
{
    /**
     * @param Method|array<Method> $methods
     * @param mixed|null $permissionCallback Checks if the user can perform the action before the real callback is called:
     *                                       a PermissionInterface class name, a permission object such as
     *                                       `new HasCapability('manage_options')` or `new AnyOf(...)`, or a callable.
     *                                       Replaces the #[Route] permission; null inherits it.
     * @param string|null $path Appended to the #[Route] path, for example '/{id:\d+}'. Actions sharing the same final path are registered as one WordPress route.
     */
    public function __construct(
        public readonly Method|array $methods,
        public readonly mixed $permissionCallback = null,
        public readonly ?string $path = null,
    ) {
    }
}

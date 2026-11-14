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
     * @param mixed|null $permissionCallback This is a function that checks if the user can perform the action (reading, updating, etc.) before the real callback is called.
     * @param string|null $path Appended to the #[Route] path, for example '/{id:\d+}'. Actions sharing the same final path are registered as one WordPress route.
     */
    public function __construct(
        public readonly Method|array $methods,
        public readonly mixed $permissionCallback = null,
        public readonly ?string $path = null,
    ) {
    }
}

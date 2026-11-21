<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Attributes;

use Dbout\WpRestApi\Resolvers\ParameterResolverInterface;

/**
 * Injects the value returned by a resolver into an action argument.
 *
 * ```php
 * public function show(#[Resolve(PostResolver::class)] \WP_Post $post): \WP_REST_Response
 * ```
 *
 * The resolver class is checked when routes are loaded and created on each
 * request, through the container of RouteLoaderOptions when it has the class,
 * without arguments otherwise.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Resolve
{
    /**
     * @param class-string $resolver A class implementing ParameterResolverInterface,
     *                               checked when routes are loaded.
     */
    public function __construct(
        public readonly string $resolver,
    ) {
    }
}

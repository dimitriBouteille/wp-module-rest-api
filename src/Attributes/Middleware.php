<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Attributes;

use Dbout\WpRestApi\Middleware\MiddlewareInterface;

/**
 * Wraps the actions of a #[Route] class, or a single action, with middlewares.
 *
 * ```php
 * #[Middleware(RateLimit::class)]
 * #[Route('app/v1', '/documents')]
 * class DocumentController
 * {
 *     #[Action(Method::GET)]
 *     #[Middleware(CacheResponse::class, Timing::class)]
 *     public function list(): \WP_REST_Response { ... }
 * }
 * ```
 *
 * Class middlewares run before method middlewares, in declaration order.
 * Each class is checked when routes are loaded and instantiated without
 * arguments on each request.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Middleware
{
    /**
     * @var list<string>
     */
    public readonly array $classes;

    /**
     * @param string ...$classes Classes implementing {@see MiddlewareInterface},
     *                           checked when routes are loaded.
     */
    public function __construct(string ...$classes)
    {
        $this->classes = array_values($classes);
    }
}

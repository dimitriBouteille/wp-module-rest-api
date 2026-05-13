<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Attributes;

/**
 * Describes the WP REST argument schema for a single handler parameter.
 * Compiled into the `args` array of register_rest_route by the loader,
 * letting WordPress enforce required/type/enum/sanitize before the
 * handler runs.
 *
 * @see https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#arguments
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Param
{
    /**
     * @param string|null $name Request key. Defaults to the PHP parameter name.
     * @param string|null $type WP type ('string', 'integer', 'number', 'boolean',
     *                          'array', 'object'). Inferred from the PHP type when null.
     * @param bool $required
     * @param mixed $default
     * @param mixed[]|null $enum Allowed values; auto-populated from a BackedEnum.
     * @param string|null $description
     * @param mixed $sanitizeCallback Cacheable callable (string or [class, method]).
     * @param mixed $validateCallback Cacheable callable (string or [class, method]).
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $type = null,
        public readonly bool $required = false,
        public readonly mixed $default = null,
        public readonly ?array $enum = null,
        public readonly ?string $description = null,
        public readonly mixed $sanitizeCallback = null,
        public readonly mixed $validateCallback = null,
    ) {
    }
}

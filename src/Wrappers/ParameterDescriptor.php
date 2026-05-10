<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Wrappers;

/**
 * Pre-resolved metadata about a single route handler parameter, cached
 * by ReflectionCache so that we don't pay the reflection cost on every
 * request. Lives in-process; rebuilt once per parameter per worker.
 */
final class ParameterDescriptor
{
    /**
     * @param string $name Declared parameter name (used to look up request params).
     * @param int $position Zero-based parameter position in the method signature.
     * @param string|null $typeName FQCN or scalar type name; null when the parameter
     *                              has no type or a non-named type (intersection / union).
     */
    public function __construct(
        public readonly string $name,
        public readonly int $position,
        public readonly ?string $typeName,
    ) {
    }
}

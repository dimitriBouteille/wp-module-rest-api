<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi;

/**
 * Pre-compiled WP REST argument descriptor for a single handler parameter.
 * Built once at discovery time from a {@see \Dbout\WpRestApi\Attributes\Param}
 * attribute (plus PHP type inference) and serialized into the route cache.
 */
final class ParamSpec
{
    /**
     * @param string $phpName Name of the PHP parameter on the handler method.
     *                        Used by the wrapper to inject the value.
     * @param string $requestName Request key clients send. Defaults to $phpName
     *                            unless Param::$name was supplied.
     * @param array<string, mixed> $args Final WP `args` entry (the value that
     *                            ships to register_rest_route under the
     *                            $requestName key).
     */
    public function __construct(
        public readonly string $phpName,
        public readonly string $requestName,
        public readonly array $args,
    ) {
    }
}

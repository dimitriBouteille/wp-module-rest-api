<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Container;

use Dbout\WpRestApi\Resolvers\ParameterResolverInterface;

class ResolverWithDependency implements ParameterResolverInterface
{
    public function __construct(private readonly Greeter $greeter)
    {
    }

    public function resolve(\ReflectionParameter $parameter, \WP_REST_Request $request): string
    {
        return $this->greeter->greeting;
    }
}

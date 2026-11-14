<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures;

use Dbout\WpRestApi\Resolvers\ParameterResolverInterface;

/**
 * Returns "<argument name>:<id request parameter>" to prove it receives
 * both the parameter and the request.
 */
class IdResolver implements ParameterResolverInterface
{
    public function resolve(\ReflectionParameter $parameter, \WP_REST_Request $request): string
    {
        return sprintf('%s:%s', $parameter->getName(), (string) $request->get_param('id'));
    }
}

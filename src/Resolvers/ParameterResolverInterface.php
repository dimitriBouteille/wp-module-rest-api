<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Resolvers;

use Dbout\WpRestApi\Exceptions\RouteException;

/**
 * Builds the value of an action argument declared with #[Resolve].
 */
interface ParameterResolverInterface
{
    /**
     * @param \ReflectionParameter $parameter The argument to resolve: name, type, attributes.
     * @param \WP_REST_Request $request
     * @throws RouteException To return an error, for example a NotFoundException.
     * @return mixed The value passed to the action.
     */
    public function resolve(\ReflectionParameter $parameter, \WP_REST_Request $request): mixed;
}

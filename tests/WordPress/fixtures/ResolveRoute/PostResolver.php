<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\WordPress\fixtures\ResolveRoute;

use Dbout\WpRestApi\Exceptions\NotFoundException;
use Dbout\WpRestApi\Resolvers\ParameterResolverInterface;

class PostResolver implements ParameterResolverInterface
{
    public function resolve(\ReflectionParameter $parameter, \WP_REST_Request $request): \WP_Post
    {
        $post = get_post((int) $request->get_param('id'));
        if (!$post instanceof \WP_Post) {
            throw new NotFoundException('Post');
        }

        return $post;
    }
}

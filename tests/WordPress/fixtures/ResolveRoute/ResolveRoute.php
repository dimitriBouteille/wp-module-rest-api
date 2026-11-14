<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\WordPress\fixtures\ResolveRoute;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Resolve;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route('integration/v1', '/posts/{id:\d+}')]
class ResolveRoute
{
    /**
     * @param array<string> $fields
     * @return array<string, mixed>
     */
    #[Action(Method::GET)]
    public function get(
        #[Resolve(PostResolver::class)] \WP_Post $post,
        ?\WP_User $user,
        array $fields = [],
    ): array {
        return [
            'title' => $post->post_title,
            'userId' => $user?->ID,
            'fields' => $fields,
        ];
    }

    /**
     * @return array<string, int>
     */
    #[Action(Method::DELETE)]
    public function delete(\WP_User $user): array
    {
        return ['deletedBy' => $user->ID];
    }
}

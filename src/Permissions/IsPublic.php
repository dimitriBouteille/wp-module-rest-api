<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Permissions;

/**
 * Allows everyone, logged in or not.
 *
 * Use it on an #[Action] to make it public when its #[Route] declares a
 * permission: a null permissionCallback on the action inherits the route one.
 */
final class IsPublic implements PermissionInterface, CacheablePermissionInterface
{
    /**
     * @inheritDoc
     */
    public function allow(\WP_REST_Request $request): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function cacheArguments(): array
    {
        return [];
    }
}

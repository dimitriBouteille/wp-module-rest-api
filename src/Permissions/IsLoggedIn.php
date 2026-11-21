<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Permissions;

/**
 * Allows logged-in users only.
 */
final class IsLoggedIn implements PermissionInterface, CacheablePermissionInterface
{
    /**
     * @inheritDoc
     */
    public function allow(\WP_REST_Request $request): bool
    {
        return is_user_logged_in();
    }

    /**
     * @inheritDoc
     */
    public function cacheArguments(): array
    {
        return [];
    }
}

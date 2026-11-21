<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Permissions;

/**
 * Checks current_user_can('contributor'), a role name and not a capability.
 *
 * Prefer {@see HasCapability} for new code, for example
 * `new HasCapability('edit_posts')`:
 * role checks break with custom roles or plugins that change them. Use
 * {@see HasRole} to check the role itself.
 */
class IsContributor implements PermissionInterface, CacheablePermissionInterface
{
    /**
     * @inheritDoc
     */
    public function allow(\WP_REST_Request $request): bool
    {
        return current_user_can('contributor');
    }

    /**
     * @inheritDoc
     */
    public function cacheArguments(): array
    {
        return [];
    }
}

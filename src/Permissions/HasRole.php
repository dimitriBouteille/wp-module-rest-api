<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Permissions;

/**
 * Allows the users that have a role on the current site.
 *
 * Prefer {@see HasCapability}: WordPress recommends checking capabilities,
 * which keep working with custom roles and plugins that change them. Unlike
 * current_user_can(), this class only looks at the roles of the user: a
 * multisite Super Admin without the role on the site is not allowed.
 */
final class HasRole implements PermissionInterface, CacheablePermissionInterface
{
    /**
     * @param string $role Role name, for example 'editor'.
     */
    public function __construct(
        public readonly string $role,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function allow(\WP_REST_Request $request): bool
    {
        return in_array($this->role, wp_get_current_user()->roles, true);
    }

    /**
     * @inheritDoc
     */
    public function cacheArguments(): array
    {
        return [$this->role];
    }
}

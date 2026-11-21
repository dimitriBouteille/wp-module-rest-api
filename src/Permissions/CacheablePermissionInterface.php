<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Permissions;

/**
 * Permission object that the route cache can store and rebuild with
 * `new $class(...$this->cacheArguments())`.
 *
 * Without it, a permission given as an object (for example
 * `new HasCapability('edit_posts')`) cannot be cached and the route cache
 * is skipped.
 *
 * @internal Implemented by the permissions of the package only.
 */
interface CacheablePermissionInterface
{
    /**
     * Arguments to pass to the constructor to rebuild the same permission.
     * They must be scalars, null, arrays or cacheable permissions.
     *
     * @return list<mixed>
     */
    public function cacheArguments(): array;
}

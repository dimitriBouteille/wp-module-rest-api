<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Permissions;

/**
 * Allows the users that have a capability, checked with current_user_can().
 *
 * ```php
 * #[Route('app/v1', '/settings', permissionCallback: new HasCapability('manage_options'))]
 * ```
 *
 * @see https://developer.wordpress.org/reference/functions/current_user_can/
 */
final class HasCapability implements PermissionInterface, CacheablePermissionInterface
{
    /**
     * @var list<mixed>
     */
    public readonly array $args;

    /**
     * @param string $capability Capability name, for example 'manage_options' or 'edit_posts'.
     * @param mixed ...$args Extra arguments of current_user_can(), for example an object ID
     *                       with a meta capability such as 'edit_post'.
     */
    public function __construct(
        public readonly string $capability,
        mixed ...$args,
    ) {
        $this->args = array_values($args);
    }

    /**
     * @inheritDoc
     */
    public function allow(\WP_REST_Request $request): bool
    {
        return current_user_can($this->capability, ...$this->args);
    }

    /**
     * @inheritDoc
     */
    public function cacheArguments(): array
    {
        return [$this->capability, ...$this->args];
    }
}

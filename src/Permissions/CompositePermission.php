<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Permissions;

use Dbout\WpRestApi\Exceptions\ApiException;
use Dbout\WpRestApi\Wrappers\ClassFactory;

/**
 * Base of the permissions that combine other permissions. Each check accepts
 * the same values as permissionCallback: a PermissionInterface class name,
 * a permission object or a callable.
 */
abstract class CompositePermission implements PermissionInterface, CacheablePermissionInterface
{
    /**
     * @var list<mixed>
     */
    public readonly array $checks;

    /**
     * @param mixed ...$checks
     * @throws ApiException When no check is given.
     */
    public function __construct(mixed ...$checks)
    {
        if ($checks === []) {
            throw new ApiException(sprintf('%s expects at least one permission.', static::class));
        }

        $this->checks = array_values($checks);
    }

    /**
     * Runs the checks without container: the class names they contain are
     * instantiated without arguments, so a class that needs dependencies
     * throws an ApiException. The route loader calls check() instead, so the
     * permission classes are created through the container. When you call
     * this method yourself, pass permission objects that are already built.
     *
     * @inheritDoc
     */
    public function allow(\WP_REST_Request $request): bool|\WP_Error
    {
        return $this->check($request, new ClassFactory());
    }

    /**
     * @param \WP_REST_Request $request
     * @param ClassFactory $classFactory Creates the permission classes given by name.
     * @throws \Exception
     * @return bool|\WP_Error
     *
     * @internal
     */
    abstract public function check(\WP_REST_Request $request, ClassFactory $classFactory): bool|\WP_Error;

    /**
     * Returns the checks only. A subclass whose constructor takes other
     * arguments must override this method, otherwise the route cache is
     * skipped with a notice.
     *
     * @inheritDoc
     */
    public function cacheArguments(): array
    {
        return $this->checks;
    }
}

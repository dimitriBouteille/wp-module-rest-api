<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Wrappers;

use Dbout\WpRestApi\Exceptions\ApiException;
use Dbout\WpRestApi\Exceptions\PermissionException;
use Dbout\WpRestApi\Permissions\CompositePermission;
use Dbout\WpRestApi\Permissions\PermissionInterface;
use Dbout\WpRestApi\RouteAction;

class PermissionWrapper
{
    /**
     * @param RouteAction $action
     * @param ClassFactory $classFactory Creates the permission class, through
     *        the container when one is set.
     */
    public function __construct(
        protected RouteAction $action,
        protected ClassFactory $classFactory = new ClassFactory(),
    ) {
    }

    /**
     * @param \WP_REST_Request $request
     * @throws \Exception
     * @return bool|\WP_Error
     */
    public function execute(\WP_REST_Request $request): bool|\WP_Error
    {
        $permissionCallback = $this->action->permissionCallback;
        if ($permissionCallback === null) {
            return true;
        }

        return self::resolve($permissionCallback, $request, $this->classFactory);
    }

    /**
     * Runs one permission: a PermissionInterface class name, a permission
     * object or a callable. Used by execute() and by AnyOf / AllOf for each
     * of their checks.
     *
     * @param mixed $permission
     * @param \WP_REST_Request $request
     * @param ClassFactory $classFactory Creates the permission classes given by name.
     * @throws ApiException When the permission is not a valid value.
     * @throws \Exception
     * @return bool|\WP_Error A PermissionException is returned as a rest_forbidden \WP_Error.
     */
    public static function resolve(
        mixed $permission,
        \WP_REST_Request $request,
        ClassFactory $classFactory = new ClassFactory(),
    ): bool|\WP_Error {
        try {
            if ($permission instanceof CompositePermission) {
                return $permission->check($request, $classFactory);
            }

            if ($permission instanceof PermissionInterface) {
                return $permission->allow($request);
            }

            if (is_string($permission) && class_exists($permission) && ReflectionCache::implementsPermissionInterface($permission)) {
                /** @var PermissionInterface $instance */
                $instance = $classFactory->create($permission);
                return $instance->allow($request);
            }

            if (is_callable($permission)) {
                return ($permission)($request);
            }

            throw new ApiException('Invalid permissionCallback argument.');
        } catch (PermissionException $exception) {
            return new \WP_Error(
                'rest_forbidden',
                $exception->getMessage(),
                ['status' => rest_authorization_required_code()]
            );
        }
    }
}

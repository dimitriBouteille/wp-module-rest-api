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

        try {
            if (is_string($permissionCallback) && class_exists($permissionCallback) && ReflectionCache::implementsPermissionInterface($permissionCallback)) {
                /** @var PermissionInterface $instance */
                $instance = $this->classFactory->create($permissionCallback);
                return $instance->allow($request);
            }

            if (is_callable($permissionCallback)) {
                return ($permissionCallback)($request);
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

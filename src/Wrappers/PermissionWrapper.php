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
     */
    public function __construct(
        protected RouteAction $action,
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
            $class = new \ReflectionClass($permissionCallback);
            if ($class->implementsInterface(PermissionInterface::class)) {
                /** @var PermissionInterface $instance */
                $instance = $class->newInstance();
                return $instance->allow($request);
            }

            if (is_callable($permissionCallback)) {
                return call_user_func($permissionCallback, $request);
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

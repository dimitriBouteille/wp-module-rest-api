<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Permissions;

use Dbout\WpRestApi\Wrappers\ClassFactory;
use Dbout\WpRestApi\Wrappers\PermissionWrapper;

/**
 * Allows the request when every check allows it. Checks run in order and
 * stop at the first failure, whose result (false or \WP_Error) is returned.
 *
 * ```php
 * #[Route('app/v1', '/settings', permissionCallback: new AllOf(new IsLoggedIn(), new HasCapability('manage_options')))]
 * ```
 */
final class AllOf extends CompositePermission
{
    /**
     * @inheritDoc
     */
    public function check(\WP_REST_Request $request, ClassFactory $classFactory): bool|\WP_Error
    {
        foreach ($this->checks as $check) {
            $result = PermissionWrapper::resolve($check, $request, $classFactory);
            if ($result !== true) {
                return $result;
            }
        }

        return true;
    }
}

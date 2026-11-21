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
 * Allows the request when at least one check allows it. Checks run in order
 * and stop at the first success.
 *
 * ```php
 * #[Route('app/v1', '/reports', permissionCallback: new AnyOf(IsAdministrator::class, new HasCapability('view_reports')))]
 * ```
 *
 * When every check fails, the first \WP_Error returned by a check is
 * returned, or false (the default WordPress error) when none was returned.
 */
final class AnyOf extends CompositePermission
{
    /**
     * @inheritDoc
     */
    public function check(\WP_REST_Request $request, ClassFactory $classFactory): bool|\WP_Error
    {
        $error = null;
        foreach ($this->checks as $check) {
            $result = PermissionWrapper::resolve($check, $request, $classFactory);
            if ($result === true) {
                return true;
            }

            if ($result instanceof \WP_Error) {
                $error ??= $result;
            }
        }

        return $error ?? false;
    }
}

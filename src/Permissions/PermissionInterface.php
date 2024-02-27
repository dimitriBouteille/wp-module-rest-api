<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Permissions;

use Dbout\WpRestApi\Exceptions\PermissionException;

interface PermissionInterface
{
    /**
     * This is a function that checks if the user can perform the action.
     *
     * @param \WP_REST_Request $request
     * @throws PermissionException
     * @throws \Exception
     * @return bool|\WP_Error
     */
    public function allow(\WP_REST_Request $request): bool|\WP_Error;
}

<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures;

use Dbout\WpRestApi\Permissions\PermissionInterface;

class AlwaysAllowPermission implements PermissionInterface
{
    public function allow(\WP_REST_Request $request): bool
    {
        return true;
    }
}

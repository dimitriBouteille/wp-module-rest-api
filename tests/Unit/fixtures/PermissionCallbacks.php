<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures;

class PermissionCallbacks
{
    public static function allow(\WP_REST_Request $request): bool
    {
        return true;
    }

    public static function deny(\WP_REST_Request $request): bool
    {
        return false;
    }
}

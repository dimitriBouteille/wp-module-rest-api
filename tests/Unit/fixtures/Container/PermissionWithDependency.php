<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Container;

use Dbout\WpRestApi\Permissions\PermissionInterface;

class PermissionWithDependency implements PermissionInterface
{
    public function __construct(private readonly bool $allow)
    {
    }

    public function allow(\WP_REST_Request $request): bool
    {
        return $this->allow;
    }
}

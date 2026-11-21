<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures;

use Dbout\WpRestApi\Permissions\CompositePermission;
use Dbout\WpRestApi\Wrappers\ClassFactory;
use Dbout\WpRestApi\Wrappers\PermissionWrapper;

/**
 * Composite whose constructor takes an argument that cacheArguments() does
 * not return.
 */
class AtLeastPermission extends CompositePermission
{
    public function __construct(public readonly int $min, mixed ...$checks)
    {
        parent::__construct(...$checks);
    }

    public function check(\WP_REST_Request $request, ClassFactory $classFactory): bool|\WP_Error
    {
        $allowed = 0;
        foreach ($this->checks as $check) {
            if (PermissionWrapper::resolve($check, $request, $classFactory) === true) {
                $allowed++;
            }
        }

        return $allowed >= $this->min;
    }
}

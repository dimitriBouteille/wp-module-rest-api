<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures;

/**
 * Same as AtLeastPermission with an untyped argument: the cache would
 * silently rebuild a different permission.
 */
class UntypedAtLeastPermission extends AtLeastPermission
{
    /**
     * @param mixed $threshold
     * @param mixed ...$checks
     */
    public function __construct(public readonly mixed $threshold, mixed ...$checks)
    {
        parent::__construct(2, ...$checks);
    }
}

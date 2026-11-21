<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures;

use Dbout\WpRestApi\Permissions\IsAdministrator;

/**
 * Role permission subclass whose constructor requires an argument.
 */
class IsAdministratorOfSite extends IsAdministrator
{
    public function __construct(public readonly int $siteId)
    {
    }
}

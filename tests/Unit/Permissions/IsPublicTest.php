<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\Permissions;

use Dbout\WpRestApi\Permissions\IsPublic;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Dbout\WpRestApi\Permissions\IsPublic
 */
class IsPublicTest extends TestCase
{
    /**
     * @covers ::allow
     */
    public function testAllowReturnsTrue(): void
    {
        $permission = new IsPublic();

        $this->assertTrue($permission->allow(new \WP_REST_Request()));
        $this->assertTrue($permission->allow(new \WP_REST_Request('DELETE', '/anything')));
    }
}

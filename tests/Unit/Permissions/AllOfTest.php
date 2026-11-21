<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\Permissions;

use Dbout\WpRestApi\Exceptions\ApiException;
use Dbout\WpRestApi\Permissions\AllOf;
use Dbout\WpRestApi\Permissions\AnyOf;
use Dbout\WpRestApi\Permissions\IsPublic;
use Dbout\WpRestApi\Tests\Unit\fixtures\AlwaysAllowPermission;
use Dbout\WpRestApi\Tests\Unit\fixtures\AlwaysDenyPermission;
use Dbout\WpRestApi\Tests\Unit\fixtures\PermissionCallbacks;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Dbout\WpRestApi\Permissions\AllOf
 */
class AllOfTest extends TestCase
{
    /**
     * @covers ::check
     */
    public function testReturnsTrueOnlyIfAllPass(): void
    {
        $request = new \WP_REST_Request();

        $this->assertTrue((new AllOf(new IsPublic(), AlwaysAllowPermission::class, [PermissionCallbacks::class, 'allow']))->allow($request));
        $this->assertFalse((new AllOf(new IsPublic(), [PermissionCallbacks::class, 'deny']))->allow($request));
    }

    /**
     * @covers ::check
     */
    public function testReturnsForbiddenIfAnyFails(): void
    {
        $result = (new AllOf(AlwaysAllowPermission::class, AlwaysDenyPermission::class))->allow(new \WP_REST_Request());

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('rest_forbidden', $result->get_error_code());
        $this->assertSame('Access denied for testing.', $result->get_error_message());
    }

    /**
     * @covers ::check
     */
    public function testStopsAtTheFirstFailure(): void
    {
        $calls = 0;
        $permission = new AllOf(
            [PermissionCallbacks::class, 'error'],
            static function () use (&$calls): bool {
                $calls++;
                return true;
            },
        );
        $result = $permission->allow(new \WP_REST_Request());

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('custom_forbidden', $result->get_error_code());
        $this->assertSame(0, $calls);
    }

    /**
     * @covers ::check
     */
    public function testNestsWithAnyOf(): void
    {
        $permission = new AllOf(
            new IsPublic(),
            new AnyOf([PermissionCallbacks::class, 'deny'], AlwaysAllowPermission::class),
        );

        $this->assertTrue($permission->allow(new \WP_REST_Request()));
    }

    /**
     * @covers ::__construct
     */
    public function testRequiresAtLeastOneCheck(): void
    {
        $this->expectException(ApiException::class);

        new AllOf();
    }
}

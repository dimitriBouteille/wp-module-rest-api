<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\Permissions;

use Dbout\WpRestApi\Exceptions\ApiException;
use Dbout\WpRestApi\Permissions\AnyOf;
use Dbout\WpRestApi\Tests\Unit\fixtures\AlwaysAllowPermission;
use Dbout\WpRestApi\Tests\Unit\fixtures\AlwaysDenyPermission;
use Dbout\WpRestApi\Tests\Unit\fixtures\Container\ArrayContainer;
use Dbout\WpRestApi\Tests\Unit\fixtures\Container\PermissionWithDependency;
use Dbout\WpRestApi\Tests\Unit\fixtures\PermissionCallbacks;
use Dbout\WpRestApi\Wrappers\ClassFactory;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Dbout\WpRestApi\Permissions\AnyOf
 */
class AnyOfTest extends TestCase
{
    /**
     * @covers ::check
     */
    public function testReturnsTrueIfAnyChildPasses(): void
    {
        $permission = new AnyOf([PermissionCallbacks::class, 'deny'], AlwaysAllowPermission::class);

        $this->assertTrue($permission->allow(new \WP_REST_Request()));
    }

    /**
     * @covers ::check
     */
    public function testStopsAtTheFirstSuccess(): void
    {
        $calls = 0;
        $permission = new AnyOf(
            new AlwaysAllowPermission(),
            static function () use (&$calls): bool {
                $calls++;
                return true;
            },
        );

        $this->assertTrue($permission->allow(new \WP_REST_Request()));
        $this->assertSame(0, $calls);
    }

    /**
     * @covers ::check
     */
    public function testReturnsFalseIfAllFailWithoutError(): void
    {
        $permission = new AnyOf([PermissionCallbacks::class, 'deny'], [PermissionCallbacks::class, 'deny']);

        $this->assertFalse($permission->allow(new \WP_REST_Request()));
    }

    /**
     * @covers ::check
     */
    public function testReturnsForbiddenIfAllFail(): void
    {
        $permission = new AnyOf(
            [PermissionCallbacks::class, 'deny'],
            AlwaysDenyPermission::class,
            [PermissionCallbacks::class, 'error'],
        );
        $result = $permission->allow(new \WP_REST_Request());

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('rest_forbidden', $result->get_error_code(), 'The first error MUST be returned.');
        $this->assertSame('Access denied for testing.', $result->get_error_message());
    }

    /**
     * @covers ::check
     */
    public function testCreatesPermissionClassesWithTheClassFactory(): void
    {
        $container = new ArrayContainer([
            PermissionWithDependency::class => new PermissionWithDependency(true),
        ]);
        $permission = new AnyOf(PermissionWithDependency::class);

        $this->assertTrue($permission->check(new \WP_REST_Request(), new ClassFactory($container)));
        $this->assertSame([PermissionWithDependency::class], $container->requested);
    }

    /**
     * @covers ::__construct
     */
    public function testRequiresAtLeastOneCheck(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage(sprintf('%s expects at least one permission.', AnyOf::class));

        new AnyOf();
    }

    /**
     * @covers ::check
     */
    public function testInvalidCheckThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid permissionCallback argument.');

        (new AnyOf([PermissionCallbacks::class, 'deny'], 42))->allow(new \WP_REST_Request());
    }
}

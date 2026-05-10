<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\Wrappers;

use Dbout\WpRestApi\Exceptions\ApiException;
use Dbout\WpRestApi\RouteAction;
use Dbout\WpRestApi\Tests\Unit\fixtures\AlwaysAllowPermission;
use Dbout\WpRestApi\Tests\Unit\fixtures\AlwaysDenyPermission;
use Dbout\WpRestApi\Tests\Unit\fixtures\PermissionCallbacks;
use Dbout\WpRestApi\Wrappers\PermissionWrapper;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Dbout\WpRestApi\Wrappers\PermissionWrapper
 */
class PermissionWrapperTest extends TestCase
{
    /**
     * @throws \Exception
     * @return void
     * @covers ::execute
     */
    public function testWithoutPermission(): void
    {
        $wrapper = $this->createWrapper(null);
        $this->assertTrue($wrapper->execute(new \WP_REST_Request()));
    }

    /**
     * @covers ::execute
     */
    public function testPermissionInterfaceClassStringAllow(): void
    {
        $wrapper = $this->createWrapper(AlwaysAllowPermission::class);
        $this->assertTrue($wrapper->execute(new \WP_REST_Request()));
    }

    /**
     * @covers ::execute
     */
    public function testPermissionExceptionIsConvertedToWpError(): void
    {
        $wrapper = $this->createWrapper(AlwaysDenyPermission::class);
        $result = $wrapper->execute(new \WP_REST_Request());

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('rest_forbidden', $result->get_error_code());
        $this->assertSame('Access denied for testing.', $result->get_error_message());
    }

    /**
     * @covers ::execute
     */
    public function testStringFunctionCallable(): void
    {
        // is_object() is a built-in function that accepts any value and
        // returns true when the argument is an object — perfect smoke test
        // for resolving a function-name-as-string callable.
        $wrapper = $this->createWrapper('is_object');
        $this->assertTrue($wrapper->execute(new \WP_REST_Request()));
    }

    /**
     * @covers ::execute
     */
    public function testStringClassMethodCallable(): void
    {
        $wrapper = $this->createWrapper(PermissionCallbacks::class . '::allow');
        $this->assertTrue($wrapper->execute(new \WP_REST_Request()));
    }

    /**
     * @covers ::execute
     */
    public function testArrayCallable(): void
    {
        $wrapper = $this->createWrapper([PermissionCallbacks::class, 'allow']);
        $this->assertTrue($wrapper->execute(new \WP_REST_Request()));

        $wrapper = $this->createWrapper([PermissionCallbacks::class, 'deny']);
        $this->assertFalse($wrapper->execute(new \WP_REST_Request()));
    }

    /**
     * @covers ::execute
     */
    public function testClosureCallable(): void
    {
        $wrapper = $this->createWrapper(static fn (\WP_REST_Request $request): bool => true);
        $this->assertTrue($wrapper->execute(new \WP_REST_Request()));

        $wrapper = $this->createWrapper(static fn (\WP_REST_Request $request): bool => false);
        $this->assertFalse($wrapper->execute(new \WP_REST_Request()));
    }

    /**
     * @covers ::execute
     */
    public function testInvalidCallbackThrowsApiException(): void
    {
        $wrapper = $this->createWrapper(42);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid permissionCallback argument.');

        $wrapper->execute(new \WP_REST_Request());
    }

    /**
     * @covers ::execute
     */
    public function testUnknownStringCallbackThrowsApiException(): void
    {
        $wrapper = $this->createWrapper('this_function_does_not_exist_anywhere');

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid permissionCallback argument.');

        $wrapper->execute(new \WP_REST_Request());
    }

    /**
     * @param mixed $permissionCallback
     * @return PermissionWrapper
     */
    protected function createWrapper(mixed $permissionCallback): PermissionWrapper
    {
        return new PermissionWrapper(new RouteAction(
            'MyClass',
            'execute',
            ['GET', 'POST'],
            $permissionCallback,
        ));
    }
}

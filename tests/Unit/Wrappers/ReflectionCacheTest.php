<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\Wrappers;

use Dbout\WpRestApi\Tests\Unit\fixtures\AlwaysAllowPermission;
use Dbout\WpRestApi\Tests\Unit\fixtures\PermissionCallbacks;
use Dbout\WpRestApi\Tests\Unit\fixtures\RouteWithFatalError;
use Dbout\WpRestApi\Wrappers\ParameterDescriptor;
use Dbout\WpRestApi\Wrappers\ReflectionCache;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Dbout\WpRestApi\Wrappers\ReflectionCache
 */
class ReflectionCacheTest extends TestCase
{
    protected function setUp(): void
    {
        ReflectionCache::clear();
    }

    /**
     * @covers ::parameters
     */
    public function testParametersReturnsDescriptors(): void
    {
        $descriptors = ReflectionCache::parameters(PermissionCallbacks::class, 'allow');

        $this->assertCount(1, $descriptors);
        $this->assertInstanceOf(ParameterDescriptor::class, $descriptors[0]);
        $this->assertSame('request', $descriptors[0]->name);
        $this->assertSame(0, $descriptors[0]->position);
        $this->assertSame(\WP_REST_Request::class, $descriptors[0]->typeName);
    }

    /**
     * @covers ::parameters
     */
    public function testParametersAreMemoized(): void
    {
        $first = ReflectionCache::parameters(PermissionCallbacks::class, 'allow');
        $second = ReflectionCache::parameters(PermissionCallbacks::class, 'allow');

        // Identity check on the array AND on the descriptor instance proves
        // the second call hit the cache and didn't rebuild anything.
        $this->assertSame($first, $second);
        $this->assertSame($first[0], $second[0]);
    }

    /**
     * @covers ::parameters
     */
    public function testParametersOnMethodWithoutArgsReturnsEmptyArray(): void
    {
        $descriptors = ReflectionCache::parameters(RouteWithFatalError::class, 'execute');
        $this->assertSame([], $descriptors);
    }

    /**
     * @covers ::parameters
     */
    public function testParametersThrowsOnUnknownMethod(): void
    {
        $this->expectException(\ReflectionException::class);
        ReflectionCache::parameters(PermissionCallbacks::class, 'doesNotExist');
    }

    /**
     * @covers ::implementsPermissionInterface
     */
    public function testImplementsPermissionInterfaceTrue(): void
    {
        $this->assertTrue(ReflectionCache::implementsPermissionInterface(AlwaysAllowPermission::class));
    }

    /**
     * @covers ::implementsPermissionInterface
     */
    public function testImplementsPermissionInterfaceFalse(): void
    {
        $this->assertFalse(ReflectionCache::implementsPermissionInterface(PermissionCallbacks::class));
    }

    /**
     * @covers ::implementsPermissionInterface
     * @covers ::clear
     */
    public function testImplementsPermissionInterfaceIsMemoized(): void
    {
        // Prime the cache.
        $this->assertTrue(ReflectionCache::implementsPermissionInterface(AlwaysAllowPermission::class));

        // Second call must hit the in-memory cache; no easy way to spy on
        // ReflectionClass construction, so we settle for behavioural parity.
        $this->assertTrue(ReflectionCache::implementsPermissionInterface(AlwaysAllowPermission::class));

        // After clear(), the cache is rebuilt — still returning the right answer.
        ReflectionCache::clear();
        $this->assertTrue(ReflectionCache::implementsPermissionInterface(AlwaysAllowPermission::class));
    }
}

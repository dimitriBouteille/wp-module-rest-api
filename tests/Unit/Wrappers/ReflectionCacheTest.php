<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\Wrappers;

use Dbout\WpRestApi\Tests\Unit\fixtures\AlwaysAllowPermission;
use Dbout\WpRestApi\Middleware\MiddlewareInterface;
use Dbout\WpRestApi\Tests\Unit\fixtures\IdResolver;
use Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares\AbstractMiddleware;
use Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares\FirstMiddleware;
use Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares\MiddlewareWithOptionalArgument;
use Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares\MiddlewareWithRequiredArgument;
use Dbout\WpRestApi\Tests\Unit\fixtures\PermissionCallbacks;
use Dbout\WpRestApi\Tests\Unit\fixtures\RouteWithFatalError;
use Dbout\WpRestApi\Tests\Unit\fixtures\RouteWithInjectionCases;
use Dbout\WpRestApi\Wrappers\ParameterDescriptor;
use Dbout\WpRestApi\Wrappers\ReflectionCache;
use Dbout\WpRestApi\Tests\Unit\fixtures\Container\AbstractResolver;
use Dbout\WpRestApi\Tests\Unit\fixtures\Container\PrivateConstructor;
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

    /**
     * @covers ::parameters
     */
    public function testResolveAttributeIsCachedInDescriptor(): void
    {
        [$value, $id] = ReflectionCache::parameters(RouteWithInjectionCases::class, 'resolved');

        $this->assertSame(IdResolver::class, $value->resolverClass);
        $this->assertNull($id->resolverClass);
    }

    /**
     * @covers ::isParameterResolver
     */
    public function testIsParameterResolver(): void
    {
        $this->assertTrue(ReflectionCache::isParameterResolver(IdResolver::class));
        $this->assertFalse(ReflectionCache::isParameterResolver(\stdClass::class));
        $this->assertFalse(ReflectionCache::isParameterResolver('App\\Missing\\Resolver'));
    }

    /**
     * @param string $class
     * @param bool $expected
     * @dataProvider providerIsMiddleware
     * @covers ::isMiddleware
     */
    public function testIsMiddleware(string $class, bool $expected): void
    {
        $this->assertSame($expected, ReflectionCache::isMiddleware($class));
    }

    /**
     * @return \Generator
     */
    public static function providerIsMiddleware(): \Generator
    {
        yield 'Concrete middleware' => [FirstMiddleware::class, true];
        yield 'Optional constructor argument' => [MiddlewareWithOptionalArgument::class, true];
        yield 'Missing class' => ['App\\Missing\\Middleware', false];
        yield 'Not a middleware' => [\stdClass::class, false];
        yield 'The interface itself' => [MiddlewareInterface::class, false];
        yield 'Abstract class' => [AbstractMiddleware::class, false];
        yield 'Required constructor argument, checked by RouteLoader' => [MiddlewareWithRequiredArgument::class, true];
    }

    /**
     * @covers ::isParameterResolver
     */
    public function testAbstractClassIsNotAParameterResolver(): void
    {
        $this->assertFalse(ReflectionCache::isParameterResolver(AbstractResolver::class));
    }

    /**
     * @param class-string $class
     * @param bool $expected
     * @dataProvider providerIsInstantiableWithoutArguments
     * @covers ::isInstantiableWithoutArguments
     */
    public function testIsInstantiableWithoutArguments(string $class, bool $expected): void
    {
        $this->assertSame($expected, ReflectionCache::isInstantiableWithoutArguments($class));
    }

    /**
     * @return \Generator
     */
    public static function providerIsInstantiableWithoutArguments(): \Generator
    {
        yield 'No constructor' => [FirstMiddleware::class, true];
        yield 'Optional constructor argument' => [MiddlewareWithOptionalArgument::class, true];
        yield 'Required constructor argument' => [MiddlewareWithRequiredArgument::class, false];
        yield 'Abstract class' => [AbstractMiddleware::class, false];
        yield 'Private constructor' => [PrivateConstructor::class, false];
    }
}

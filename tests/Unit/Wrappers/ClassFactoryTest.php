<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\Wrappers;

use Dbout\WpRestApi\Exceptions\ApiException;
use Dbout\WpRestApi\Tests\Unit\fixtures\AlwaysAllowPermission;
use Dbout\WpRestApi\Tests\Unit\fixtures\Container\ArrayContainer;
use Dbout\WpRestApi\Tests\Unit\fixtures\Container\ControllerWithDependency;
use Dbout\WpRestApi\Tests\Unit\fixtures\Container\Greeter;
use Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares\AbstractMiddleware;
use Dbout\WpRestApi\Wrappers\ClassFactory;
use Dbout\WpRestApi\Wrappers\ReflectionCache;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Dbout\WpRestApi\Wrappers\ClassFactory
 */
class ClassFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        ReflectionCache::clear();
    }

    /**
     * @covers ::create
     */
    public function testCreatesANewInstanceWithoutContainer(): void
    {
        $factory = new ClassFactory();

        $first = $factory->create(AlwaysAllowPermission::class);
        $second = $factory->create(AlwaysAllowPermission::class);

        $this->assertInstanceOf(AlwaysAllowPermission::class, $first);
        $this->assertNotSame($first, $second, 'A new instance is created on each call.');
    }

    /**
     * @covers ::create
     */
    public function testUsesTheContainerWhenItHasTheClass(): void
    {
        $controller = new ControllerWithDependency(new Greeter('Hello'));
        $container = new ArrayContainer([ControllerWithDependency::class => $controller]);

        $this->assertSame($controller, (new ClassFactory($container))->create(ControllerWithDependency::class));
        $this->assertSame([ControllerWithDependency::class], $container->requested);
    }

    /**
     * @covers ::create
     */
    public function testFallsBackToNewWhenTheContainerMissesTheClass(): void
    {
        $container = new ArrayContainer();

        $instance = (new ClassFactory($container))->create(AlwaysAllowPermission::class);

        $this->assertInstanceOf(AlwaysAllowPermission::class, $instance);
        $this->assertSame([], $container->requested, 'get() is not called when has() is false.');
    }

    /**
     * @covers ::create
     * @covers ::cannotCreateMessage
     */
    public function testRequiredConstructorArgumentWithoutContainerThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage(sprintf(
            'The class %s cannot be instantiated without arguments: set RouteLoaderOptions::$container',
            ControllerWithDependency::class,
        ));

        (new ClassFactory())->create(ControllerWithDependency::class);
    }

    /**
     * Typical case: Symfony services are private by default.
     *
     * @covers ::create
     * @covers ::cannotCreateMessage
     */
    public function testRequiredConstructorArgumentMissingFromContainerThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('the container does not provide it: register it as a public service.');

        (new ClassFactory(new ArrayContainer()))->create(ControllerWithDependency::class);
    }

    /**
     * @covers ::create
     */
    public function testContainerReturningAnotherTypeThrows(): void
    {
        $container = new ArrayContainer([ControllerWithDependency::class => new \stdClass()]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage(sprintf(
            'The container returned stdClass for the id %s',
            ControllerWithDependency::class,
        ));

        (new ClassFactory($container))->create(ControllerWithDependency::class);
    }

    /**
     * @covers ::canCreate
     */
    public function testCanCreate(): void
    {
        $withoutContainer = new ClassFactory();
        $withContainer = new ClassFactory(new ArrayContainer([
            ControllerWithDependency::class => new ControllerWithDependency(new Greeter('Hello')),
        ]));

        $this->assertTrue($withoutContainer->canCreate(AlwaysAllowPermission::class));
        $this->assertFalse($withoutContainer->canCreate(ControllerWithDependency::class));
        $this->assertFalse($withoutContainer->canCreate(AbstractMiddleware::class));
        $this->assertTrue($withContainer->canCreate(ControllerWithDependency::class));
        $this->assertTrue($withContainer->canCreate(AlwaysAllowPermission::class));
    }
}

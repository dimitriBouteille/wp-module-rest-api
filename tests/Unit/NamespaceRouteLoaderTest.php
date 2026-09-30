<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit;

use Composer\Autoload\ClassLoader;
use Dbout\WpRestApi\NamespaceRouteLoader;
use Dbout\WpRestApi\Route;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Dbout\WpRestApi\NamespaceRouteLoader
 */
class NamespaceRouteLoaderTest extends TestCase
{
    private const FIXTURE_NS = 'Dbout\\WpRestApi\\Tests\\Unit\\fixtures\\NamespaceLoader\\';

    /**
     * @covers ::findRoutes
     * @covers ::discoverClasses
     * @covers ::resolveDirectoriesFor
     */
    public function testDiscoversRoutesFromPsr4Namespace(): void
    {
        $loader = new NamespaceRouteLoader(self::FIXTURE_NS . 'Pings\\');

        $routes = $this->invokeGetRoutes($loader);

        $byPath = [];
        foreach ($routes as $route) {
            $byPath[$route->path] = $route;
        }

        $this->assertArrayHasKey('/ping', $byPath);
        $this->assertArrayHasKey('/pong', $byPath);
        $this->assertSame('namespace-loader/v1', $byPath['/ping']->namespace);
    }

    /**
     * @covers ::resolveDirectoriesFor
     */
    public function testLongestPsr4PrefixWins(): void
    {
        $classLoader = new ClassLoader();
        $classLoader->addPsr4(
            'Dbout\\WpRestApi\\Tests\\',
            __DIR__ . '/..',
        );
        // A wider, less-specific prefix that must NOT be picked.
        $classLoader->addPsr4(
            'Dbout\\',
            __DIR__ . '/../../src',
        );

        $loader = new NamespaceRouteLoader(self::FIXTURE_NS . 'Pings\\', null, $classLoader);
        $routes = $this->invokeGetRoutes($loader);

        $paths = array_map(static fn (Route $r): string => $r->path, $routes);
        sort($paths);
        $this->assertSame(
            ['/ping', '/pong'],
            $paths,
            'Longest matching prefix should resolve the fixture directory, not the wider Dbout\\ prefix.',
        );
    }

    /**
     * @covers ::findRoutes
     * @covers ::discoverClasses
     */
    public function testClassesWithoutRouteAttributeAreSkipped(): void
    {
        $loader = new NamespaceRouteLoader(self::FIXTURE_NS . 'NotRoutes\\');

        $routes = $this->invokeGetRoutes($loader);
        $this->assertSame(
            [],
            $routes,
            'NamespaceRouteLoader must not surface classes without #[Route], and must drop abstract ones.',
        );
    }

    /**
     * @covers ::resolveDirectoriesFor
     */
    public function testUnknownNamespaceReturnsEmpty(): void
    {
        $classLoader = new ClassLoader();
        // Intentionally no PSR-4 entry registered.

        $loader = new NamespaceRouteLoader('Acme\\Nothing\\', null, $classLoader);

        $this->assertSame([], $this->invokeGetRoutes($loader));
    }

    /**
     * @covers ::__construct
     */
    public function testAcceptsMultipleNamespaces(): void
    {
        $loader = new NamespaceRouteLoader([
            self::FIXTURE_NS . 'Pings\\',
            self::FIXTURE_NS . 'NotRoutes\\',
        ]);

        $routes = $this->invokeGetRoutes($loader);
        $paths = array_map(static fn (Route $r): string => $r->path, $routes);
        sort($paths);
        $this->assertSame(['/ping', '/pong'], $paths);
    }

    /**
     * @return Route[]
     */
    private function invokeGetRoutes(NamespaceRouteLoader $loader): array
    {
        $method = new \ReflectionMethod(\Dbout\WpRestApi\RouteLoader::class, 'getRoutes');
        $method->setAccessible(true);

        /** @var Route[] $routes */
        $routes = $method->invoke($loader);
        return $routes;
    }
}
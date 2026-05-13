<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit;

use Dbout\WpRestApi\Route;
use Dbout\WpRestApi\RouteAction;
use Dbout\WpRestApi\RouteLoader;
use Dbout\WpRestApi\RouteLoaderOptions;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @coversDefaultClass \Dbout\WpRestApi\RouteLoader
 */
class RouteLoaderTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/fixtures/RouteLoader';

    /**
     * @covers ::getRoutes
     * @covers ::dehydrateRoutes
     * @covers ::hydrateRoutes
     */
    public function testRoutesArePersistedAsJson(): void
    {
        $cache = new ArrayCachePool();
        $loader = new RouteLoader(self::FIXTURE_DIR, new RouteLoaderOptions(cache: $cache));

        $this->invokeGetRoutes($loader);

        $stored = $cache->getItem(RouteLoaderOptions::DEFAULT_CACHE_KEY)->get();
        $this->assertIsString($stored, 'Cache payload must be a string.');

        $decoded = json_decode($stored, true);
        $this->assertIsArray($decoded, 'Cache payload must be valid JSON.');
        $this->assertNotEmpty($decoded);

        $first = $decoded[0];
        $this->assertSame('test-cache/v1', $first['namespace']);
        $this->assertSame('/ping', $first['path']);
        $this->assertCount(1, $first['actions']);
        $this->assertSame(['GET'], $first['actions'][0]['methods']);
    }

    /**
     * @covers ::getRoutes
     * @covers ::hydrateRoutes
     */
    public function testCacheHitReturnsRoutesWithoutRescanning(): void
    {
        $cache = new ArrayCachePool();
        $options = new RouteLoaderOptions(cache: $cache);
        $loader = new RouteLoader(self::FIXTURE_DIR, $options);

        $first = $this->invokeGetRoutes($loader);
        $this->assertCount(1, $first);

        $bogusLoader = new RouteLoader('/path/that/does/not/exist', $options);
        $second = $this->invokeGetRoutes($bogusLoader);

        $this->assertCount(1, $second, 'Cached routes should be returned even when discovery would fail.');
        $this->assertEquals($first[0]->namespace, $second[0]->namespace);
        $this->assertEquals($first[0]->path, $second[0]->path);
        $this->assertEquals($first[0]->actions[0]->className, $second[0]->actions[0]->className);
    }

    /**
     * @covers ::getRoutes
     * @covers ::hydrateRoutes
     */
    public function testCorruptedCachePayloadFallsBackToDiscovery(): void
    {
        $cache = new ArrayCachePool();
        $item = $cache->getItem(RouteLoaderOptions::DEFAULT_CACHE_KEY);
        $item->set('{"this is": not valid json');
        $cache->save($item);

        $loader = new RouteLoader(self::FIXTURE_DIR, new RouteLoaderOptions(cache: $cache));
        $routes = $this->invokeGetRoutes($loader);

        $this->assertCount(1, $routes);
        $this->assertSame('test-cache/v1', $routes[0]->namespace);

        $rebuilt = $cache->getItem(RouteLoaderOptions::DEFAULT_CACHE_KEY)->get();
        $this->assertIsString($rebuilt);
        $this->assertNotSame('{"this is": not valid json', $rebuilt, 'Cache should have been overwritten with a fresh payload.');
        $this->assertIsArray(json_decode($rebuilt, true));
    }

    /**
     * @covers ::getRoutes
     * @covers ::hydrateRoutes
     */
    public function testCachePayloadWithUnexpectedShapeFallsBack(): void
    {
        $cache = new ArrayCachePool();
        $item = $cache->getItem(RouteLoaderOptions::DEFAULT_CACHE_KEY);
        $item->set(json_encode([['namespace' => 'foo']])); // missing "path" and "actions"
        $cache->save($item);

        $loader = new RouteLoader(self::FIXTURE_DIR, new RouteLoaderOptions(cache: $cache));
        $routes = $this->invokeGetRoutes($loader);

        $this->assertCount(1, $routes);
        $this->assertSame('test-cache/v1', $routes[0]->namespace);
    }

    /**
     * @covers ::dehydrateRoutes
     */
    public function testClosurePermissionCallbackSkipsCache(): void
    {
        $cache = new ArrayCachePool();
        $options = new RouteLoaderOptions(cache: $cache);

        $loader = new class (self::FIXTURE_DIR, $options) extends RouteLoader {
            protected function findRoutes(): array
            {
                return [
                    new Route('demo/v1', '/closure', [
                        new RouteAction(
                            \Dbout\WpRestApi\Tests\Unit\fixtures\RouteLoader\PingRoute::class,
                            'ping',
                            ['GET'],
                            static fn (): bool => true,
                        ),
                    ]),
                ];
            }
        };

        set_error_handler(static function (int $errno, string $errstr): bool {
            throw new \ErrorException($errstr, $errno);
        }, \E_USER_NOTICE);

        try {
            $this->expectException(\ErrorException::class);
            $this->expectExceptionMessageMatches('/cannot be cached/i');
            $this->invokeGetRoutes($loader);
        } finally {
            restore_error_handler();
        }

        $this->assertFalse($cache->getItem(RouteLoaderOptions::DEFAULT_CACHE_KEY)->isHit());
    }

    /**
     * @covers ::buildRouteArgs
     * @covers ::compileArgs
     */
    public function testBuildRouteArgsCompilesSchema(): void
    {
        $loader = new RouteLoader(__DIR__ . '/fixtures/Loaders/WithParams');
        $routes = $this->invokeGetRoutes($loader);

        $this->assertCount(1, $routes);
        $args = $this->invokeBuildRouteArgs($loader, $routes[0]);
        $this->assertCount(1, $args, 'WithParams fixture exposes a single action.');

        $compiled = $args[0]['args'];
        $this->assertArrayHasKey('id', $compiled);
        $this->assertArrayHasKey('perPage', $compiled);
        $this->assertArrayHasKey('q', $compiled, 'The request key MUST be Param::$name when overridden.');

        $this->assertTrue($compiled['id']['required']);
        $this->assertSame('integer', $compiled['id']['type']);
        $this->assertSame('absint', $compiled['id']['sanitize_callback']);
        $this->assertSame('Item identifier', $compiled['id']['description']);

        $this->assertSame(10, $compiled['perPage']['default']);

        $this->assertSame('sanitize_text_field', $compiled['q']['sanitize_callback']);
    }

    /**
     * @covers ::buildRouteArgs
     * @covers ::compileArgs
     */
    public function testEnumParameterPopulatesEnumOption(): void
    {
        $loader = new RouteLoader(__DIR__ . '/fixtures/Loaders/WithEnumParam');
        $routes = $this->invokeGetRoutes($loader);

        $this->assertCount(1, $routes);
        $args = $this->invokeBuildRouteArgs($loader, $routes[0]);

        $directionArg = $args[0]['args']['direction'] ?? null;
        $this->assertIsArray($directionArg);
        $this->assertSame('string', $directionArg['type']);
        $this->assertSame(['asc', 'desc'], $directionArg['enum']);
    }

    /**
     * @covers ::buildRouteArgs
     * @covers ::compileArgs
     */
    public function testHandlerWithoutParamAttributeRegistersEmptyArgs(): void
    {
        $loader = new RouteLoader(self::FIXTURE_DIR);
        $routes = $this->invokeGetRoutes($loader);

        $args = $this->invokeBuildRouteArgs($loader, $routes[0]);
        $this->assertSame([], $args[0]['args'], 'Backward compat: handlers without #[Param] stay with empty args.');
    }

    /**
     * @covers ::dehydrateRoutes
     * @covers ::hydrateRoutes
     * @covers ::dehydrateParams
     * @covers ::hydrateParams
     */
    public function testParamSpecSurvivesCacheRoundtrip(): void
    {
        $cache = new ArrayCachePool();
        $loader = new RouteLoader(
            __DIR__ . '/fixtures/Loaders/WithParams',
            new RouteLoaderOptions(cache: $cache),
        );

        $first = $this->invokeGetRoutes($loader);

        // Second call hits the cache and rebuilds RouteAction::$params from JSON.
        $bogus = new RouteLoader('/does/not/exist', new RouteLoaderOptions(cache: $cache));
        $second = $this->invokeGetRoutes($bogus);

        $this->assertSame(
            $this->paramArgsByRequestName($first[0]->actions[0]->params),
            $this->paramArgsByRequestName($second[0]->actions[0]->params),
            'ParamSpec list must come back identical from the cache.',
        );
    }

    /**
     * @param array<\Dbout\WpRestApi\ParamSpec> $params
     * @return array<string, array<string, mixed>>
     */
    private function paramArgsByRequestName(array $params): array
    {
        $out = [];
        foreach ($params as $spec) {
            $out[$spec->requestName] = $spec->args;
        }

        return $out;
    }

    /**
     * @return Route[]
     */
    private function invokeGetRoutes(RouteLoader $loader): array
    {
        $method = new \ReflectionMethod(RouteLoader::class, 'getRoutes');
        $method->setAccessible(true);

        /** @var Route[] $routes */
        $routes = $method->invoke($loader);
        return $routes;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function invokeBuildRouteArgs(RouteLoader $loader, Route $route): array
    {
        $method = new \ReflectionMethod(RouteLoader::class, 'buildRouteArgs');
        $method->setAccessible(true);

        /** @var array<array<string, mixed>> $args */
        $args = $method->invoke($loader, $route);
        return $args;
    }
}

/**
 * Minimal in-memory PSR-6 cache pool for unit tests. Only the methods used
 * by RouteLoader are implemented in a meaningful way; the rest are stubs.
 */
final class ArrayCachePool implements CacheItemPoolInterface
{
    /** @var array<string, ArrayCacheItem> */
    private array $items = [];

    public function getItem(string $key): CacheItemInterface
    {
        return $this->items[$key] ?? new ArrayCacheItem($key);
    }

    /** @inheritDoc */
    public function getItems(array $keys = []): iterable
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->getItem($key);
        }
        return $out;
    }

    public function hasItem(string $key): bool
    {
        return isset($this->items[$key]);
    }

    public function clear(): bool
    {
        $this->items = [];
        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->items[$key]);
        return true;
    }

    /** @inheritDoc */
    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->deleteItem($key);
        }
        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        if (!$item instanceof ArrayCacheItem) {
            return false;
        }
        $item->markHit();
        $this->items[$item->getKey()] = $item;
        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }
}

final class ArrayCacheItem implements CacheItemInterface
{
    private mixed $value = null;
    private bool $hit = false;

    public function __construct(private readonly string $key)
    {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(\DateInterval|int|null $time): static
    {
        return $this;
    }

    public function markHit(): void
    {
        $this->hit = true;
    }
}

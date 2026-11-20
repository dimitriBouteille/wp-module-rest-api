<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit;

use Dbout\WpRestApi\Exceptions\ApiException;
use Dbout\WpRestApi\Route;
use Dbout\WpRestApi\RouteAction;
use Dbout\WpRestApi\RouteLoader;
use Dbout\WpRestApi\RouteLoaderOptions;
use Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares\AbstractMiddleware;
use Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares\FirstMiddleware;
use Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares\MiddlewareWithRequiredArgument;
use Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares\RecordingMiddleware;
use Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares\ThirdMiddleware;
use Dbout\WpRestApi\Tests\Unit\fixtures\Container\ArrayContainer;
use Dbout\WpRestApi\Tests\Unit\fixtures\Container\Greeter;
use Dbout\WpRestApi\Tests\Unit\fixtures\Container\MiddlewareWithDependency;
use Dbout\WpRestApi\Tests\Unit\fixtures\Container\PermissionWithDependency;
use Dbout\WpRestApi\Tests\Unit\fixtures\Container\ResolverWithDependency;
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithConstructorDependencies\RouteWithConstructorDependencies;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @coversDefaultClass \Dbout\WpRestApi\RouteLoader
 */
class RouteLoaderTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/fixtures/RouteLoader';
    private const CONSTRUCTOR_DEPENDENCIES_DIR = __DIR__ . '/fixtures/Loaders/WithConstructorDependencies';

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

        $stored = $cache->getItem($loader->getCacheKey())->get();
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
        $loader = new RouteLoader(self::FIXTURE_DIR, new RouteLoaderOptions(cache: $cache));
        $item = $cache->getItem($loader->getCacheKey());
        $item->set('{"this is": not valid json');
        $cache->save($item);

        $routes = $this->invokeGetRoutes($loader);

        $this->assertCount(1, $routes);
        $this->assertSame('test-cache/v1', $routes[0]->namespace);

        $rebuilt = $cache->getItem($loader->getCacheKey())->get();
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
        $loader = new RouteLoader(self::FIXTURE_DIR, new RouteLoaderOptions(cache: $cache));
        $item = $cache->getItem($loader->getCacheKey());
        $item->set(json_encode([['namespace' => 'foo']])); // missing "path" and "actions"
        $cache->save($item);

        $routes = $this->invokeGetRoutes($loader);

        $this->assertCount(1, $routes);
        $this->assertSame('test-cache/v1', $routes[0]->namespace);
    }

    /**
     * @covers ::getRoutes
     * @covers ::hydrateRoutes
     */
    public function testCachePayloadWithEmptyPathFallsBack(): void
    {
        $cache = new ArrayCachePool();
        $loader = new RouteLoader(self::FIXTURE_DIR, new RouteLoaderOptions(cache: $cache));
        $item = $cache->getItem($loader->getCacheKey());
        $item->set(json_encode([['namespace' => 'foo/v1', 'path' => '', 'actions' => []]]));
        $cache->save($item);

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

        $this->assertFalse($cache->getItem($loader->getCacheKey())->isHit());
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
        $this->assertArrayNotHasKey('sanitize_callback', $compiled['id'], 'int params rely on the WordPress schema cast, not absint.');
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
     * @covers ::dehydrateRoutes
     * @covers ::hydrateRoutes
     */
    public function testMiddlewaresSurviveCacheRoundtrip(): void
    {
        $cache = new ArrayCachePool();
        $loader = new RouteLoader(
            __DIR__ . '/fixtures/Loaders/WithMiddlewares',
            new RouteLoaderOptions(cache: $cache),
        );

        $first = $this->invokeGetRoutes($loader);

        $bogus = new RouteLoader('/does/not/exist', new RouteLoaderOptions(cache: $cache));
        $second = $this->invokeGetRoutes($bogus);

        $this->assertNotEmpty($first[0]->actions[0]->middlewares);
        $this->assertSame(
            array_map(static fn (RouteAction $action): array => $action->middlewares, $first[0]->actions),
            array_map(static fn (RouteAction $action): array => $action->middlewares, $second[0]->actions),
        );
    }

    /**
     * @covers ::buildRouteArgs
     */
    public function testGlobalMiddlewaresRunBeforeAttributeMiddlewares(): void
    {
        $loader = new RouteLoader(
            __DIR__ . '/fixtures/Loaders/WithMiddlewares',
            new RouteLoaderOptions(middlewares: [ThirdMiddleware::class]),
        );
        $routes = $this->invokeGetRoutes($loader);
        $args = $this->invokeBuildRouteArgs($loader, $routes[0]);
        $create = array_values(array_filter($args, static fn (array $arg): bool => $arg['methods'] === ['POST']))[0];

        RecordingMiddleware::$log = [];
        $create['callback'](new \WP_REST_Request('POST', '/dummy'));

        $this->assertSame([
            'ThirdMiddleware:before',
            'FirstMiddleware:before',
            'SecondMiddleware:before',
            'SecondMiddleware:after',
            'FirstMiddleware:after',
            'ThirdMiddleware:after',
        ], RecordingMiddleware::$log);
    }

    /**
     * The exception is thrown before add_action(), so register() can be
     * called without WordPress.
     *
     * @covers ::register
     * @covers ::checkMiddlewares
     */
    public function testRegisterThrowsOnInvalidGlobalMiddleware(): void
    {
        $loader = new RouteLoader(
            __DIR__ . '/fixtures/Loaders/WithMiddlewares',
            new RouteLoaderOptions(middlewares: ['App\\Missing\\Middleware']),
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('The middleware App\\Missing\\Middleware of ' . RouteLoaderOptions::class . '::$middlewares must be');

        $loader->register();
    }

    /**
     * @param mixed $middleware
     * @param string $expectedName
     * @dataProvider providerInvalidGlobalMiddleware
     * @covers ::checkMiddlewares
     */
    public function testInvalidGlobalMiddlewareThrows(mixed $middleware, string $expectedName): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage(sprintf('The middleware %s of ', $expectedName));

        $this->invokeCheckMiddlewares(new RouteLoaderOptions(middlewares: [$middleware]));
    }

    /**
     * @return \Generator
     */
    public static function providerInvalidGlobalMiddleware(): \Generator
    {
        yield 'Not a middleware' => [\stdClass::class, 'stdClass'];
        yield 'Abstract class' => [AbstractMiddleware::class, AbstractMiddleware::class];
        yield 'Object that is not a middleware' => [new \stdClass(), 'stdClass'];
        yield 'Closure' => [static fn () => null, 'Closure'];
        yield 'Integer' => [42, 'int'];
    }

    /**
     * @covers ::checkMiddlewares
     */
    public function testValidGlobalMiddlewaresPass(): void
    {
        $this->invokeCheckMiddlewares(new RouteLoaderOptions(middlewares: [
            FirstMiddleware::class,
            new MiddlewareWithRequiredArgument('X-Test'),
        ]));

        $this->addToAssertionCount(1);
    }

    /**
     * Payloads written before middlewares existed have no "middlewares" key.
     *
     * @covers ::hydrateRoutes
     */
    public function testCachePayloadWithoutMiddlewaresIsAccepted(): void
    {
        $cache = new ArrayCachePool();
        $loader = new RouteLoader('/does/not/exist', new RouteLoaderOptions(cache: $cache));
        $item = $cache->getItem($loader->getCacheKey());
        $item->set(json_encode([[
            'namespace' => 'test-cache/v1',
            'path' => '/legacy',
            'actions' => [['className' => 'Foo', 'methodName' => 'bar', 'methods' => ['GET'], 'params' => []]],
        ]]));
        $cache->save($item);

        $routes = $this->invokeGetRoutes($loader);

        $this->assertSame('/legacy', $routes[0]->path);
        $this->assertSame([], $routes[0]->actions[0]->middlewares);
    }

    /**
     * @covers ::hydrateRoutes
     */
    public function testCachePayloadWithInvalidMiddlewaresFallsBack(): void
    {
        $cache = new ArrayCachePool();
        $loader = new RouteLoader(self::FIXTURE_DIR, new RouteLoaderOptions(cache: $cache));
        $item = $cache->getItem($loader->getCacheKey());
        $item->set(json_encode([[
            'namespace' => 'test-cache/v1',
            'path' => '/stale',
            'actions' => [['className' => 'Foo', 'methodName' => 'bar', 'methods' => ['GET'], 'middlewares' => [42]]],
        ]]));
        $cache->save($item);

        $routes = $this->invokeGetRoutes($loader);

        $this->assertSame('/ping', $routes[0]->path, 'An invalid payload must trigger a fresh discovery.');
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
     * @covers ::findRoutes
     * @covers ::checkRoutes
     */
    public function testActionPathsRegisterOneRoutePerFinalPath(): void
    {
        $routes = $this->invokeGetRoutes(new RouteLoader(__DIR__ . '/fixtures/Loaders/WithActionPaths'));

        $this->assertSame(
            ['/documents', '/documents/(?P<id>\d+)'],
            array_map(static fn (Route $route): string => $route->path, $routes),
        );
    }

    /**
     * @covers ::findRoutes
     * @covers ::checkRoutes
     */
    public function testDuplicateMethodOnSameFinalPathThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('You cannot use the same method on the URL loader-test/v1/documents/(?P<id>\d+) multiple times.');

        $this->invokeGetRoutes(new RouteLoader(__DIR__ . '/fixtures/Loaders/WithDuplicateActionPath'));
    }

    /**
     * @covers ::getCacheKey
     */
    public function testCacheKeyIsPrefixedWithTheConfiguredKey(): void
    {
        $key = (new RouteLoader(self::FIXTURE_DIR, new RouteLoaderOptions(cacheKey: 'my_routes')))->getCacheKey();

        $this->assertMatchesRegularExpression('/^my_routes_[a-f0-9]{32}$/', $key, 'The key MUST only use PSR-6 safe characters.');
        $this->assertSame($key, (new RouteLoader(self::FIXTURE_DIR, new RouteLoaderOptions(cacheKey: 'my_routes')))->getCacheKey());
    }

    /**
     * @covers ::getRoutes
     * @covers ::getCacheKey
     */
    public function testCacheVersionChangesKey(): void
    {
        $cache = new ArrayCachePool();
        $v1 = new RouteLoader(self::FIXTURE_DIR, new RouteLoaderOptions(cache: $cache, cacheVersion: '1.0.0'));
        $this->invokeGetRoutes($v1);

        $v2 = new RouteLoader('/path/that/does/not/exist', new RouteLoaderOptions(cache: $cache, cacheVersion: '1.0.1'));
        $this->assertNotSame($v1->getCacheKey(), $v2->getCacheKey());

        // The v1 entry is ignored: routes are discovered again, and the bogus directory has none.
        $this->assertSame([], $this->invokeGetRoutes($v2));
        $this->assertCount(1, $this->invokeGetRoutes($v1), 'The v1 entry MUST still be served to v1.');
    }

    /**
     * @covers ::getCacheKey
     * @covers ::packageVersion
     */
    public function testPackageVersionChangesKey(): void
    {
        $current = new RouteLoader(self::FIXTURE_DIR);
        $upgraded = new class (self::FIXTURE_DIR) extends RouteLoader {
            protected static function packageVersion(): string
            {
                return '99.0.0@abcdef';
            }
        };

        $this->assertNotSame($current->getCacheKey(), $upgraded->getCacheKey());
    }

    /**
     * @covers ::getCacheKey
     * @covers ::fingerprint
     * @covers ::routeFiles
     */
    public function testFingerprintChangesWhenFileIsTouched(): void
    {
        $directory = sys_get_temp_dir() . '/wp-rest-api-fingerprint-' . uniqid();
        mkdir($directory . '/Sub', 0777, true);
        $file = $directory . '/Sub/Route.php';
        file_put_contents($file, '<?php');
        touch($file, 1_700_000_000);

        try {
            $withFingerprint = new RouteLoader($directory, new RouteLoaderOptions(cacheFingerprint: true));
            $withoutFingerprint = new RouteLoader($directory);
            $before = $withFingerprint->getCacheKey();
            $beforeWithout = $withoutFingerprint->getCacheKey();

            touch($file, 1_700_000_100);
            $afterTouch = $withFingerprint->getCacheKey();
            $this->assertNotSame($before, $afterTouch, 'Touching a route file MUST change the key.');

            file_put_contents($directory . '/NewRoute.php', '<?php');
            $this->assertNotSame($afterTouch, $withFingerprint->getCacheKey(), 'Adding a route file MUST change the key.');

            $beforeText = $withFingerprint->getCacheKey();
            file_put_contents($directory . '/notes.txt', 'ignored');
            $this->assertSame($beforeText, $withFingerprint->getCacheKey(), 'Non-PHP files MUST NOT change the key.');

            $this->assertSame($beforeWithout, $withoutFingerprint->getCacheKey(), 'Without the option, files MUST NOT change the key.');
        } finally {
            array_map('unlink', array_filter(glob($directory . '/{,Sub/}*', \GLOB_BRACE) ?: [], 'is_file'));
            rmdir($directory . '/Sub');
            rmdir($directory);
        }
    }

    /**
     * @covers ::checkMiddlewares
     */
    public function testGlobalMiddlewareWithRequiredArgumentNeedsTheContainer(): void
    {
        $this->invokeCheckMiddlewares(new RouteLoaderOptions(
            middlewares: [MiddlewareWithRequiredArgument::class],
            container: new ArrayContainer([
                MiddlewareWithRequiredArgument::class => new MiddlewareWithRequiredArgument('X-Test'),
            ]),
        ));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage(sprintf(
            'The class %s cannot be instantiated without arguments: set RouteLoaderOptions::$container to inject its dependencies. Used by %s::$middlewares.',
            MiddlewareWithRequiredArgument::class,
            RouteLoaderOptions::class,
        ));

        $this->invokeCheckMiddlewares(new RouteLoaderOptions(middlewares: [MiddlewareWithRequiredArgument::class]));
    }

    /**
     * @covers ::checkRoutes
     * @covers ::checkConstructors
     */
    public function testClassesWithRequiredArgumentsPassWhenTheContainerHasThem(): void
    {
        $routes = $this->invokeGetRoutes(new RouteLoader(
            self::CONSTRUCTOR_DEPENDENCIES_DIR,
            new RouteLoaderOptions(container: new ArrayContainer(self::constructorDependencies())),
        ));

        $this->assertCount(1, $routes);
    }

    /**
     * @param class-string $missing
     * @dataProvider providerClassMissingFromContainer
     * @covers ::checkRoutes
     * @covers ::checkConstructors
     */
    public function testClassWithRequiredArgumentsMissingFromContainerThrows(string $missing): void
    {
        $services = self::constructorDependencies();
        unset($services[$missing]);
        $loader = new RouteLoader(
            self::CONSTRUCTOR_DEPENDENCIES_DIR,
            new RouteLoaderOptions(container: new ArrayContainer($services)),
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage(sprintf(
            'The class %s cannot be instantiated without arguments and the container does not provide it: register it as a public service. Used by %s::get().',
            $missing,
            RouteWithConstructorDependencies::class,
        ));

        $this->invokeGetRoutes($loader);
    }

    /**
     * @return \Generator
     */
    public static function providerClassMissingFromContainer(): \Generator
    {
        yield 'Controller' => [RouteWithConstructorDependencies::class];
        yield 'Middleware' => [MiddlewareWithDependency::class];
        yield 'Permission' => [PermissionWithDependency::class];
        yield 'Resolver' => [ResolverWithDependency::class];
    }

    /**
     * @covers ::checkConstructors
     */
    public function testClassWithRequiredArgumentsWithoutContainerThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage(sprintf(
            'The class %s cannot be instantiated without arguments: set RouteLoaderOptions::$container to inject its dependencies.',
            RouteWithConstructorDependencies::class,
        ));

        $this->invokeGetRoutes(new RouteLoader(self::CONSTRUCTOR_DEPENDENCIES_DIR));
    }

    /**
     * The container reaches both wrappers: the permission, the middleware,
     * the resolver and the controller come from it.
     *
     * @covers ::buildRouteArgs
     * @covers ::classFactory
     */
    public function testContainerIsPassedToTheWrappers(): void
    {
        $container = new ArrayContainer(self::constructorDependencies());
        $loader = new RouteLoader(self::CONSTRUCTOR_DEPENDENCIES_DIR, new RouteLoaderOptions(container: $container));
        $routes = $this->invokeGetRoutes($loader);
        $args = $this->invokeBuildRouteArgs($loader, $routes[0])[0];
        $request = new \WP_REST_Request('GET', '/dummy');

        $this->assertTrue($args['permission_callback']($request));
        $response = $args['callback']($request);

        $this->assertSame(['value' => 'Resolved', 'greeting' => 'Hello'], $response->get_data());
        $this->assertSame('Hello', $response->get_headers()['X-Greeting'] ?? null);
    }

    /**
     * @return array<class-string, object>
     */
    private static function constructorDependencies(): array
    {
        return [
            RouteWithConstructorDependencies::class => new RouteWithConstructorDependencies(new Greeter('Hello')),
            MiddlewareWithDependency::class => new MiddlewareWithDependency(new Greeter('Hello')),
            PermissionWithDependency::class => new PermissionWithDependency(true),
            ResolverWithDependency::class => new ResolverWithDependency(new Greeter('Resolved')),
        ];
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

    private function invokeCheckMiddlewares(RouteLoaderOptions $options): void
    {
        $method = new \ReflectionMethod(RouteLoader::class, 'checkMiddlewares');
        $method->setAccessible(true);
        $method->invoke(new RouteLoader('/does/not/exist', $options), $options->middlewares);
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

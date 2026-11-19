<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi;

use Composer\InstalledVersions;
use Dbout\WpRestApi\ErrorFormat\DefaultFormatter;
use Dbout\WpRestApi\Exceptions\ApiException;
use Dbout\WpRestApi\Helpers\FileLocator;
use Dbout\WpRestApi\Loaders\AnnotationDirectoryLoader;
use Dbout\WpRestApi\Middleware\MiddlewareInterface;
use Dbout\WpRestApi\Wrappers\ClassFactory;
use Dbout\WpRestApi\Wrappers\PermissionWrapper;
use Dbout\WpRestApi\Wrappers\ReflectionCache;
use Dbout\WpRestApi\Wrappers\RestWrapper;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Container\ContainerInterface;

class RouteLoader
{
    private const PACKAGE_NAME = 'dbout/wp-module-rest-api';

    /**
     * @param string|array<string> $routeDirectory
     * @param RouteLoaderOptions|null $options
     *
     * @deprecated Directory-based discovery relies on a custom token parser
     *             that has known edge cases (anonymous classes, block-style
     *             namespaces). Prefer {@see NamespaceRouteLoader} which uses
     *             Composer's PSR-4 mapping as the source of truth.
     */
    public function __construct(
        protected string|array $routeDirectory,
        protected ?RouteLoaderOptions $options = null,
    ) {
    }

    /**
     * @throws \Exception|\Psr\Cache\InvalidArgumentException
     * @return Route[]
     */
    protected function getRoutes(): array
    {
        $cache = $this->options?->cache;
        if (!$cache instanceof CacheItemPoolInterface) {
            return $this->findRoutes();
        }

        $cacheRoutes = $cache->getItem($this->getCacheKey());
        if ($cacheRoutes->isHit()) {
            $payload = $cacheRoutes->get();
            $hydrated = is_string($payload) ? $this->hydrateRoutes($payload) : null;
            if ($hydrated !== null) {
                return $hydrated;
            }
        }

        $routes = $this->findRoutes();
        $serialized = $this->dehydrateRoutes($routes);
        if ($serialized !== null) {
            $cacheRoutes->set($serialized);
            $cache->save($cacheRoutes);
        }

        return $routes;
    }

    /**
     * Key of the route cache item: the configured cacheKey followed by a hash
     * of what can change the routes, so a deployment rebuilds the cache:
     * the package version, the cacheVersion option and, when cacheFingerprint
     * is enabled, the route files.
     *
     * @return string
     */
    public function getCacheKey(): string
    {
        $options = $this->options ?? new RouteLoaderOptions();
        $parts = [static::packageVersion(), $options->cacheVersion];
        if ($options->cacheFingerprint) {
            $parts[] = $this->fingerprint();
        }

        return sprintf('%s_%s', $options->cacheKey, hash('xxh128', implode('|', $parts)));
    }

    /**
     * Version and git reference of the installed package: in a dev install
     * (dev-develop), only the reference changes between two updates.
     *
     * @return string
     */
    protected static function packageVersion(): string
    {
        if (!class_exists(InstalledVersions::class) || !InstalledVersions::isInstalled(self::PACKAGE_NAME)) {
            return '';
        }

        return sprintf(
            '%s@%s',
            InstalledVersions::getVersion(self::PACKAGE_NAME) ?? '',
            InstalledVersions::getReference(self::PACKAGE_NAME) ?? '',
        );
    }

    /**
     * Hash of the path, modification time and size of every route file.
     *
     * @return string
     */
    protected function fingerprint(): string
    {
        clearstatcache();

        $entries = [];
        foreach ($this->routeFiles() as $file) {
            $entries[] = sprintf('%s:%d:%d', $file, (int) @filemtime($file), (int) @filesize($file));
        }

        sort($entries);
        return hash('xxh128', implode("\n", $entries));
    }

    /**
     * PHP files that can declare routes, used by the cache fingerprint.
     *
     * @return iterable<string>
     */
    protected function routeFiles(): iterable
    {
        $directories = is_array($this->routeDirectory) ? $this->routeDirectory : [$this->routeDirectory];
        foreach ($directories as $pattern) {
            foreach (glob($pattern) ?: [] as $directory) {
                foreach (self::findPhpFiles($directory) as $file) {
                    yield $file->getPathname();
                }
            }
        }
    }

    /**
     * @param string $directory
     * @return \Generator<\SplFileInfo> PHP files of the directory and its sub-directories,
     *                                    hidden files and directories excluded.
     */
    protected static function findPhpFiles(string $directory): \Generator
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator(
                    $directory,
                    \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS,
                ),
                static fn (\SplFileInfo $current): bool => !str_starts_with($current->getBasename(), '.'),
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file;
            }
        }
    }

    /**
     * @param string $payload JSON payload retrieved from the cache.
     * @return Route[]|null Returns null when the payload is corrupted or
     *                     does not match the expected shape; the caller is
     *                     expected to fall back to a fresh discovery.
     */
    protected function hydrateRoutes(string $payload): ?array
    {
        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return null;
        }

        $routes = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                return null;
            }

            $namespace = $entry['namespace'] ?? null;
            $path = $entry['path'] ?? null;
            $rawActions = $entry['actions'] ?? null;
            if (!is_string($namespace) || !is_string($path) || !is_array($rawActions)) {
                return null;
            }

            $actions = [];
            foreach ($rawActions as $rawAction) {
                if (!is_array($rawAction)) {
                    return null;
                }

                $className = $rawAction['className'] ?? null;
                $methodName = $rawAction['methodName'] ?? null;
                $methods = $rawAction['methods'] ?? null;
                if (!is_string($className) || !is_string($methodName) || !is_array($methods)) {
                    return null;
                }

                $params = $this->hydrateParams($rawAction['params'] ?? []);
                if ($params === null) {
                    return null;
                }

                // Missing in payloads written before middlewares existed.
                $middlewares = $rawAction['middlewares'] ?? [];
                if (!is_array($middlewares) || !array_is_list($middlewares)) {
                    return null;
                }

                foreach ($middlewares as $middleware) {
                    if (!is_string($middleware)) {
                        return null;
                    }
                }

                $actions[] = new RouteAction(
                    $className,
                    $methodName,
                    array_values(array_filter($methods, is_string(...))),
                    $rawAction['permissionCallback'] ?? null,
                    $params,
                    $middlewares,
                );
            }

            try {
                $routes[] = new Route($namespace, $path, $actions);
            } catch (ApiException) {
                return null;
            }
        }

        return $routes;
    }

    /**
     * @param Route[] $routes
     * @return string|null JSON payload, or null when at least one route uses
     *                    a permissionCallback that cannot be safely cached
     *                    (e.g. a Closure or a non-callable object).
     */
    protected function dehydrateRoutes(array $routes): ?string
    {
        $payload = [];
        foreach ($routes as $route) {
            $actions = [];
            foreach ($route->actions as $action) {
                if (!$this->isCacheableCallback($action->permissionCallback)) {
                    trigger_error(sprintf(
                        'Route %s/%s has a permissionCallback that cannot be cached (Closure or non-serializable value); the route cache will be skipped.',
                        $route->namespace,
                        $route->path,
                    ), \E_USER_NOTICE);

                    return null;
                }

                $serializedParams = $this->dehydrateParams($action->params);
                if ($serializedParams === null) {
                    trigger_error(sprintf(
                        'Route %s/%s has a Param callback that cannot be cached (Closure or non-serializable value); the route cache will be skipped.',
                        $route->namespace,
                        $route->path,
                    ), \E_USER_NOTICE);

                    return null;
                }

                $actions[] = [
                    'className' => $action->className,
                    'methodName' => $action->methodName,
                    'methods' => $action->methods,
                    'permissionCallback' => $action->permissionCallback,
                    'params' => $serializedParams,
                    'middlewares' => $action->middlewares,
                ];
            }

            $payload[] = [
                'namespace' => $route->namespace,
                'path' => $route->path,
                'actions' => $actions,
            ];
        }

        try {
            return json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * @param mixed $raw Raw payload from the cache; not yet validated.
     * @return array<ParamSpec>|null Null when the payload shape is invalid.
     */
    protected function hydrateParams(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }

        $params = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                return null;
            }

            $phpName = $entry['phpName'] ?? null;
            $requestName = $entry['requestName'] ?? null;
            $args = $entry['args'] ?? null;
            if (!is_string($phpName) || !is_string($requestName) || !is_array($args)) {
                return null;
            }

            $params[] = new ParamSpec($phpName, $requestName, $args);
        }

        return $params;
    }

    /**
     * @param array<ParamSpec> $params
     * @return array<int, array<string, mixed>>|null Null when at least one
     *         param has a sanitize/validate callback that cannot be cached.
     */
    protected function dehydrateParams(array $params): ?array
    {
        $out = [];
        foreach ($params as $spec) {
            foreach (['sanitize_callback', 'validate_callback'] as $key) {
                if (!array_key_exists($key, $spec->args)) {
                    continue;
                }

                if (!$this->isCacheableCallback($spec->args[$key])) {
                    return null;
                }
            }

            $out[] = [
                'phpName' => $spec->phpName,
                'requestName' => $spec->requestName,
                'args' => $spec->args,
            ];
        }

        return $out;
    }

    /**
     * Whether the given permission callback can be safely persisted as JSON
     * and rebuilt as a usable callback later on.
     */
    protected function isCacheableCallback(mixed $callback): bool
    {
        if ($callback === null || is_string($callback)) {
            return true;
        }

        return is_array($callback)
            && count($callback) === 2
            && isset($callback[0], $callback[1])
            && is_string($callback[0])
            && is_string($callback[1]);
    }

    /**
     * @throws \Exception
     * @return Route[]
     */
    protected function findRoutes(): array
    {
        $tmpDirectories = is_array($this->routeDirectory) ? $this->routeDirectory : [$this->routeDirectory];
        $routes = [];

        $directories = [];
        foreach ($tmpDirectories as $dir) {
            $globalDirs = glob($dir);
            if (!is_array($globalDirs)) {
                continue;
            }

            $directories = array_merge($directories, $globalDirs);
        }

        foreach ($directories as $directory) {
            $directory = new \SplFileInfo($directory);
            if (!$directory->isDir()) {
                throw new ApiException(sprintf(
                    'The path %s is not a valid folder.',
                    $directory
                ));
            }

            $path = $directory->getRealPath();
            if (!is_string($path)) {
                continue;
            }

            $loader = new AnnotationDirectoryLoader(
                new FileLocator([$path])
            );

            $routes = array_merge($routes, $loader->load($path));
        }

        $this->checkRoutes($routes);
        return $routes;
    }

    /**
     * @param array<Route> $routes
     * @throws ApiException
     * @return void
     */
    protected function checkRoutes(array $routes): void
    {
        foreach ($routes as $route) {
            $methods = [];
            foreach ($route->actions as $action) {
                $diff = array_intersect($methods, $action->methods);
                if ($diff === []) {
                    $methods = array_merge($methods, $action->methods);
                    continue;
                }

                throw new ApiException(sprintf(
                    'You cannot use the same method on the URL %s/%s multiple times. Check the #[Action] methods and paths of %s.',
                    $route->namespace,
                    ltrim($route->path, '/'),
                    is_string($action->className) ? $action->className : 'the route class',
                ));
            }
        }

        $this->checkConstructors($routes);
    }

    /**
     * Checks that the classes created on each request (controller, permission
     * class, #[Resolve] resolvers, #[Middleware] classes) can be created: the
     * container has them or they need no constructor argument.
     *
     * Done when routes are discovered, not on each register(), so a cache hit
     * costs no reflection: the error message of ClassFactory still explains
     * the problem at request time when the container changes afterwards.
     *
     * @param array<Route> $routes
     * @throws ApiException
     * @throws \ReflectionException
     * @return void
     */
    protected function checkConstructors(array $routes): void
    {
        $factory = $this->classFactory();
        foreach ($routes as $route) {
            foreach ($route->actions as $action) {
                $className = $action->className;
                if (!is_string($className) || !class_exists($className)) {
                    continue;
                }

                $classes = [$className, ...$action->middlewares];
                $permission = $action->permissionCallback;
                if (is_string($permission) && class_exists($permission) && ReflectionCache::implementsPermissionInterface($permission)) {
                    $classes[] = $permission;
                }

                foreach (ReflectionCache::parameters($className, $action->methodName) as $descriptor) {
                    if ($descriptor->resolverClass !== null) {
                        $classes[] = $descriptor->resolverClass;
                    }
                }

                foreach ($classes as $class) {
                    if (!class_exists($class) || $factory->canCreate($class)) {
                        continue;
                    }

                    throw new ApiException(sprintf(
                        '%s Used by %s::%s().',
                        ClassFactory::cannotCreateMessage($class, $this->options?->container instanceof ContainerInterface),
                        $className,
                        $action->methodName,
                    ));
                }
            }
        }
    }

    /**
     * @return ClassFactory Creates the user classes through the container of
     *                      the options, when one is set.
     */
    protected function classFactory(): ClassFactory
    {
        return new ClassFactory($this->options?->container);
    }

    /**
     * Checks RouteLoaderOptions::$middlewares when routes are registered, so
     * an invalid entry fails on every page load rather than on the first REST
     * request. Done on each call: these middlewares are not stored in the
     * route cache.
     *
     * @param array<mixed> $middlewares Values of RouteLoaderOptions::$middlewares:
     *                    the property is public and its PHPDoc type is not
     *                    enforced, so any value can reach this method.
     * @throws ApiException
     * @return void
     */
    protected function checkMiddlewares(array $middlewares): void
    {
        $factory = $this->classFactory();
        foreach ($middlewares as $middleware) {
            if ($middleware instanceof MiddlewareInterface) {
                continue;
            }

            if (!is_string($middleware) || !ReflectionCache::isMiddleware($middleware)) {
                throw new ApiException(sprintf(
                    'The middleware %s of %s::$middlewares must be an instance of %s or an existing, non-abstract class implementing it.',
                    is_string($middleware) ? $middleware : get_debug_type($middleware),
                    RouteLoaderOptions::class,
                    MiddlewareInterface::class,
                ));
            }

            if (!$factory->canCreate($middleware)) {
                throw new ApiException(sprintf(
                    '%s Used by %s::$middlewares.',
                    ClassFactory::cannotCreateMessage($middleware, $this->options?->container instanceof ContainerInterface),
                    RouteLoaderOptions::class,
                ));
            }
        }
    }

    /**
     * Register all routes with register_rest_route
     * @see https://developer.wordpress.org/reference/functions/register_rest_route/
     * @throws \Exception
     * @throws InvalidArgumentException
     * @throws ApiException When a middleware of RouteLoaderOptions::$middlewares is invalid.
     * @return void
     */
    public function register(): void
    {
        $routes = $this->getRoutes();
        $this->checkMiddlewares(($this->options ?? new RouteLoaderOptions())->middlewares);
        add_action('rest_api_init', function () use ($routes): void {
            foreach ($routes as $route) {
                register_rest_route(
                    $route->namespace,
                    $route->path,
                    $this->buildRouteArgs($route),
                );
            }
        });
    }

    /**
     * @param Route $route
     * @return array<array<string, mixed>>
     */
    protected function buildRouteArgs(Route $route): array
    {
        $actions = [];
        $options = $this->options ?? new RouteLoaderOptions();
        $isDebug = $options->debug;
        $errorFormatter = $options->errorFormatter ?? new DefaultFormatter();
        $classFactory = $this->classFactory();

        foreach ($route->actions as $action) {
            $actions[] = [
                'methods' => $action->methods,
                'callback' => [new RestWrapper(
                    $action,
                    $isDebug,
                    $errorFormatter,
                    $options->middlewares,
                    $classFactory,
                ), 'execute'],
                'permission_callback' => [new PermissionWrapper($action, $classFactory), 'execute'],
                'args' => $this->compileArgs($action),
            ];
        }

        return $actions;
    }

    /**
     * @param RouteAction $action
     * @return array<string, array<string, mixed>>
     */
    protected function compileArgs(RouteAction $action): array
    {
        $args = [];
        foreach ($action->params as $spec) {
            $args[$spec->requestName] = $spec->args;
        }

        return $args;
    }
}

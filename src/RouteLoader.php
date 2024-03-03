<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi;

use Dbout\WpRestApi\Exceptions\ApiException;
use Dbout\WpRestApi\Helpers\FileLocator;
use Dbout\WpRestApi\Loaders\AnnotationDirectoryLoader;
use Dbout\WpRestApi\Wrappers\PermissionWrapper;
use Dbout\WpRestApi\Wrappers\RestWrapper;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

class RouteLoader
{
    /**
     * @param string|array<string> $routeDirectory
     * @param RouteLoaderOptions|null $options
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

        $cacheKey = $this->options->cacheKey;
        $cacheRoutes = $cache->getItem($cacheKey);
        if ($cacheRoutes->isHit()) {
            try {
                return unserialize($cacheRoutes->get());
            } catch (\Exception) {
            }
        }

        $routes = $this->findRoutes();
        $cacheRoutes->set(serialize($routes));
        $cache->save($cacheRoutes);
        return $routes;
    }

    /**
     * @throws \Exception
     * @return Route[]
     */
    protected function findRoutes(): array
    {
        $directories = is_array($this->routeDirectory) ? $this->routeDirectory : [$this->routeDirectory];
        $routes = [];

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
                    'You cannot use the same method on the URL %s/%s multiple times.',
                    $route->namespace,
                    $route->path
                ));
            }
        }
    }

    /**
     * Register all routes with register_rest_route
     * @see https://developer.wordpress.org/reference/functions/register_rest_route/
     * @throws \Exception
     * @throws InvalidArgumentException
     * @return void
     */
    public function register(): void
    {
        $routes = $this->getRoutes();
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
        $isDebug = $this->options?->debug ?? false;
        foreach ($route->actions as $action) {
            $actions[] = [
                'methods' => $action->methods,
                'callback' => [new RestWrapper($action, $isDebug), 'execute'],
                'permission_callback' => [new PermissionWrapper($action), 'execute'],
                'args' => [],
            ];
        }

        return $actions;
    }
}

<?php
/**
 * Copyright (c) 2024 Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi;

use Dbout\WpRestApi\Exceptions\ApiException;
use Dbout\WpRestApi\Loader\AnnotationDirectoryLoader;
use Dbout\WpRestApi\Wrappers\PermissionWrapper;
use Dbout\WpRestApi\Wrappers\RestWrapper;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Finder\Finder;

class RouteLoader
{
    final public const CACHE_KEY = 'wp_autoloader_routes';

    /**
     * @var FilesystemAdapter|null
     */
    protected ?FilesystemAdapter $cache = null;

    /**
     * @param string $rootDirectory
     * @param RouteLoaderOptions|null $options
     */
    public function __construct(
        protected string $rootDirectory,
        protected ?RouteLoaderOptions $options = null,
    ) {
        if ($this->options->cache !== false) {
            $this->cache = new FilesystemAdapter(
                directory: $this->options->cache
            );
        }
    }

    /**
     * @throws \Exception|\Psr\Cache\InvalidArgumentException
     * @return Route[]
     */
    protected function getRoutes(): array
    {
        if ($this->cache instanceof FilesystemAdapter) {
            $routes = $this->cache->get(self::CACHE_KEY, function () {
                $routes = $this->findRoutes();
                return serialize($routes);
            });

            try {
                return unserialize($routes);
            } catch (\Exception) {
            }
        }

        return $this->findRoutes();
    }

    /**
     * @throws \Exception
     * @return Route[]
     */
    protected function findRoutes(): array
    {
        $finder = new Finder();
        $directories = $finder->ignoreUnreadableDirs()->in($this->rootDirectory);
        $routes = [];

        foreach ($directories->depth(1) as $directory) {
            if (!$directory instanceof \SplFileInfo) {
                continue;
            }

            $path = $directory->getRealPath();
            $loader = new AnnotationDirectoryLoader(
                new FileLocator($path)
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
        add_action('rest_api_init', function () use ($routes) {
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
     * @return array
     */
    protected function buildRouteArgs(Route $route): array
    {
        $actions = [];
        foreach ($route->actions as $action) {
            $actions[] = [
                'methods' => $action->methods,
                'callback' => [new RestWrapper($action), 'execute'],
                'permission_callback' => [new PermissionWrapper($action), 'execute'],
                'args' => [],
            ];
        }

        return $actions;
    }
}

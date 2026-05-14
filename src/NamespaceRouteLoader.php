<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi;

use Composer\Autoload\ClassLoader;
use Dbout\WpRestApi\Exceptions\ApiException;
use Dbout\WpRestApi\Loaders\AnnotatedRouteRestLoader;

/**
 * Namespace-based route discovery.
 *
 * Resolves the filesystem directory(ies) attached to a PSR-4 namespace
 * prefix via Composer's autoloader, derives each candidate class' FQCN
 * from its file path (no token parsing), and delegates the attribute
 * inspection to {@see AnnotatedRouteRestLoader}.
 *
 * Compared to the legacy {@see RouteLoader} directory mode, this avoids
 * the custom token-stream parser and trusts Composer as the single
 * source of truth for the class-to-file mapping.
 *
 * @api
 */
class NamespaceRouteLoader extends RouteLoader
{
    /** @var string[] Normalized namespace prefixes (always trailing "\\"). */
    protected array $namespaces;

    protected ClassLoader $classLoader;

    protected AnnotatedRouteRestLoader $annotationLoader;

    /**
     * @param string|string[] $namespace One or more PSR-4 namespace prefixes
     *                                    to scan (e.g. `App\Routes\`).
     * @param RouteLoaderOptions|null $options
     * @param ClassLoader|null $classLoader Optional Composer ClassLoader override.
     *                                       Defaults to the first registered loader.
     */
    public function __construct(
        string|array $namespace,
        ?RouteLoaderOptions $options = null,
        ?ClassLoader $classLoader = null,
    ) {
        // The parent constructor stores $routeDirectory; we never read it
        // because we override findRoutes(). Pass an empty array to keep the
        // contract honest.
        parent::__construct([], $options);

        $this->namespaces = array_values(array_map(
            static fn (string $ns): string => rtrim($ns, '\\') . '\\',
            is_array($namespace) ? $namespace : [$namespace],
        ));
        $this->classLoader = $classLoader ?? self::resolveDefaultClassLoader();
        $this->annotationLoader = new AnnotatedRouteRestLoader();
    }

    /**
     * @throws ApiException When no Composer ClassLoader is registered.
     * @return ClassLoader
     */
    protected static function resolveDefaultClassLoader(): ClassLoader
    {
        $loaders = ClassLoader::getRegisteredLoaders();
        if ($loaders === []) {
            throw new ApiException(
                'No Composer ClassLoader is registered. Did you require vendor/autoload.php?',
            );
        }

        return reset($loaders);
    }

    /**
     * @throws \ReflectionException|ApiException
     * @return Route[]
     */
    protected function findRoutes(): array
    {
        $routes = [];
        foreach ($this->namespaces as $namespace) {
            foreach ($this->discoverClasses($namespace) as $fqcn) {
                if (!class_exists($fqcn)) {
                    continue;
                }

                $reflection = new \ReflectionClass($fqcn);
                if ($reflection->isAbstract()) {
                    continue;
                }

                $route = $this->annotationLoader->load($fqcn);
                if ($route instanceof Route) {
                    $routes[] = $route;
                }
            }
        }

        $this->checkRoutes($routes);
        return $routes;
    }

    /**
     * Walks the directory bound to the longest registered PSR-4 prefix that
     * is a prefix of $namespace, and yields the FQCN of every PHP file
     * found underneath.
     *
     * @return iterable<string>
     */
    protected function discoverClasses(string $namespace): iterable
    {
        $directories = $this->resolveDirectoriesFor($namespace);
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
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

            $basePath = rtrim((string) realpath($directory), DIRECTORY_SEPARATOR);
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $real = $file->getRealPath();
                if ($real === false) {
                    continue;
                }

                $relative = substr($real, strlen($basePath) + 1, -4);
                $suffix = str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
                yield $namespace . $suffix;
            }
        }
    }

    /**
     * Resolve filesystem directories for a namespace by finding the longest
     * registered PSR-4 prefix that matches and appending the remaining
     * sub-namespace as a relative path.
     *
     * @return string[]
     */
    protected function resolveDirectoriesFor(string $namespace): array
    {
        $psr4 = $this->classLoader->getPrefixesPsr4();

        $longest = '';
        foreach (array_keys($psr4) as $prefix) {
            if (str_starts_with($namespace, $prefix) && strlen($prefix) > strlen($longest)) {
                $longest = $prefix;
            }
        }

        if ($longest === '') {
            return [];
        }

        $subPath = str_replace('\\', DIRECTORY_SEPARATOR, substr($namespace, strlen($longest)));
        $subPath = rtrim($subPath, DIRECTORY_SEPARATOR);

        return array_map(
            static function (string $base) use ($subPath): string {
                $base = rtrim($base, DIRECTORY_SEPARATOR);
                return $subPath === '' ? $base : $base . DIRECTORY_SEPARATOR . $subPath;
            },
            $psr4[$longest],
        );
    }
}

<?php
/**
 * Copyright (c) 2024 Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Loaders;

use Dbout\WpRestApi\Helpers\FileLocatorInterface;
use Dbout\WpRestApi\Helpers\Parser;
use Dbout\WpRestApi\Route;

class AnnotationDirectoryLoader implements InterfaceLoader
{
    /**
     * @param FileLocatorInterface $locator
     * @param InterfaceLoader $annotationClassLoader
     */
    public function __construct(
        protected FileLocatorInterface $locator,
        protected InterfaceLoader $annotationClassLoader = new AnnotatedRouteRestLoader()
    ) {
    }

    /**
     * @inheritDoc
     * @return array<Route>
     */
    public function load(mixed $resource): array
    {
        $dir = $this->locator->locate($resource);
        if (is_array($dir) || !is_dir($dir)) {
            return [];
        }

        /** @var \SplFileInfo[] $files */
        $files = iterator_to_array(new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS),
                function (\SplFileInfo $current) {
                    return !str_starts_with($current->getBasename(), '.');
                }
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        ));

        usort($files, function (\SplFileInfo $a, \SplFileInfo $b) {
            return (string) $a > (string) $b ? 1 : -1;
        });

        $routes = [];
        foreach ($files as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            /** @var class-string|null $class */
            $class = $this->findClass($file);
            if ($class === null) {
                continue;
            }

            $ref = new \ReflectionClass($class);
            if ($ref->isAbstract()) {
                continue;
            }

            $classRoute = $this->annotationClassLoader->load($class);
            if (!$classRoute instanceof Route) {
                continue;
            }

            $routes[] = $classRoute;
        }

        return $routes;
    }

    /**
     * @param string $file
     * @return string|null
     */
    protected function findClass(string $file): ?string
    {
        $fileContent = file_get_contents($file);
        if ($fileContent === false) {
            return null;
        }

        try {
            return Parser::findClassName($fileContent);
        } catch (\InvalidArgumentException) {
            throw new \InvalidArgumentException(sprintf(
                'The file "%s" does not contain PHP code. Did you forgot to add the "<?php" start tag at the beginning of the file?',
                $file
            ));
        }
    }
}

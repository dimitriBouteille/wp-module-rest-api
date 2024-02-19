<?php
/**
 * Copyright (c) 2024 Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Loader;

use Dbout\WpRestApi\Route;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Config\Loader\LoaderInterface;

/**
 * @see \Symfony\Component\Routing\Loader\AnnotationDirectoryLoader
 * @see \Symfony\Component\Routing\Loader\AnnotationClassLoader
 */
class AnnotationDirectoryLoader extends FileLoader
{
    /**
     * @param FileLocatorInterface $locator
     * @param LoaderInterface $annotationClassLoader
     */
    public function __construct(
        FileLocatorInterface $locator,
        protected LoaderInterface $annotationClassLoader = new AnnotatedRouteRestLoader()
    ) {
        parent::__construct(
            $locator
        );
    }

    /**
     * @return array<Route>
     * @inheritDoc
     */
    public function load($resource, string $type = null): array
    {
        if (!is_dir($dir = $this->locator->locate($resource))) {
            return [];
        }

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
     * @inheritDoc
     */
    public function supports($resource, string $type = null): bool
    {
        return \is_string($resource) && 'php' === pathinfo($resource, \PATHINFO_EXTENSION);
    }

    /**
     * @param string $file
     * @return string|null
     */
    protected function findClass(string $file): ?string
    {
        $class = false;
        $namespace = false;
        $tokens = token_get_all(file_get_contents($file));

        if (1 === \count($tokens) && \T_INLINE_HTML === $tokens[0][0]) {
            throw new \InvalidArgumentException(sprintf('The file "%s" does not contain PHP code. Did you forgot to add the "<?php" start tag at the beginning of the file?', $file));
        }

        $nsTokens = [\T_NS_SEPARATOR => true, \T_STRING => true];
        if (\defined('T_NAME_QUALIFIED')) {
            $nsTokens[\T_NAME_QUALIFIED] = true;
        }
        for ($i = 0; isset($tokens[$i]); ++$i) {
            $token = $tokens[$i];
            if (!isset($token[1])) {
                continue;
            }

            if ($class && \T_STRING === $token[0]) {
                return $namespace.'\\'.$token[1];
            }

            if (true === $namespace && isset($nsTokens[$token[0]])) {
                $namespace = $token[1];
                while (isset($tokens[++$i][1], $nsTokens[$tokens[$i][0]])) {
                    $namespace .= $tokens[$i][1];
                }
                $token = $tokens[$i];
            }

            if (\T_CLASS === $token[0]) {
                // Skip usage of ::class constant and anonymous classes
                $skipClassToken = false;
                for ($j = $i - 1; $j > 0; --$j) {
                    if (!isset($tokens[$j][1])) {
                        if ('(' === $tokens[$j] || ',' === $tokens[$j]) {
                            $skipClassToken = true;
                        }
                        break;
                    }

                    if (\T_DOUBLE_COLON === $tokens[$j][0] || \T_NEW === $tokens[$j][0]) {
                        $skipClassToken = true;
                        break;
                    } elseif (!\in_array($tokens[$j][0], [\T_WHITESPACE, \T_DOC_COMMENT, \T_COMMENT], true)) {
                        break;
                    }
                }

                if (!$skipClassToken) {
                    $class = true;
                }
            }

            if (\T_NAMESPACE === $token[0]) {
                $namespace = true;
            }
        }

        return null;
    }
}
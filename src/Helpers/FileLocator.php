<?php
/**
 * Copyright (c) 2024 Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Helpers;

use Dbout\WpRestApi\Exceptions\ApiException;

class FileLocator implements FileLocatorInterface
{
    /**
     * @param string|string[] $paths A path or an array of paths where to look for resources
     */
    public function __construct(
        protected array $paths = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function locate(string $name, string $currentPath = null, bool $first = true): string|array
    {
        if ('' === $name) {
            throw new \InvalidArgumentException('An empty file name is not valid to be located.');
        }

        if ($this->isAbsolutePath($name)) {
            if (!file_exists($name)) {
                throw new ApiException(sprintf('The file "%s" does not exist.', $name));
            }

            return $name;
        }

        $paths = $this->paths;

        if (null !== $currentPath) {
            array_unshift($paths, $currentPath);
        }

        $paths = array_unique($paths);
        $filepaths = $notfound = [];

        foreach ($paths as $path) {
            if (@file_exists($file = $path.\DIRECTORY_SEPARATOR.$name)) {
                if (true === $first) {
                    return $file;
                }
                $filepaths[] = $file;
            } else {
                $notfound[] = $file;
            }
        }

        if (!$filepaths) {
            throw new ApiException(sprintf('The file "%s" does not exist (in: "%s").', $name, implode('", "', $paths)));
        }

        return $filepaths;
    }

    /**
     * Returns whether the file path is an absolute path.
     */
    private function isAbsolutePath(string $file): bool
    {
        if ('/' === $file[0] || '\\' === $file[0]
            || (
                \strlen($file) > 3 && ctype_alpha($file[0])
                && ':' === $file[1]
                && ('\\' === $file[2] || '/' === $file[2])
            )
            || null !== parse_url($file, \PHP_URL_SCHEME)
        ) {
            return true;
        }

        return false;
    }
}

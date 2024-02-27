<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Helpers;

use Dbout\WpRestApi\Exceptions\ApiException;

interface FileLocatorInterface
{
    /**
     * Returns a full path for a given file name.
     *
     * @param string      $name        The file name to locate
     * @param string|null $currentPath The current path
     * @param bool        $first       Whether to return the first occurrence or an array of filenames
     *
     * @throws \InvalidArgumentException        If $name is empty
     * @throws ApiException If a file is not found
     * @return string|array<string> The full path to the file or an array of file paths
     *
     */
    public function locate(string $name, string $currentPath = null, bool $first = true): array|string;
}

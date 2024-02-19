<?php
/**
 * Copyright (c) 2024 Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi;

class RouteLoaderOptions
{
    /**
     * @param string|bool $cache    Set this option to false for disable the cache or the path to the cache folder
     *                              if you want to have cache on the routes search
     */
    public function __construct(
        public string|bool $cache = false,
    ) {
    }
}

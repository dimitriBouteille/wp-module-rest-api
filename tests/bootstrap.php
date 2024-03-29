<?php
/**
 * Copyright (c) 2024 Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

define( 'WPINC', 'wp-includes' );
define('ABSPATH', __DIR__ . '/../web/wordpress/');
$includeDirectory  = __DIR__ . '/../web/wordpress/wp-includes';

$paths = [
    '/functions.php',
    '/plugin.php',
    '/class-wp-http-response.php',
    '/rest-api.php',
    '/rest-api/class-wp-rest-server.php',
    '/rest-api/class-wp-rest-response.php',
    '/rest-api/class-wp-rest-request.php',
    '/class-wp-error.php'
];

foreach ($paths as $path) {
    require $includeDirectory . $path;
}

<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares;

use Dbout\WpRestApi\Middleware\MiddlewareInterface;

/**
 * Records "<name>:before" and "<name>:after" in a shared log, so tests can
 * check the order in which the pipeline runs.
 */
abstract class RecordingMiddleware implements MiddlewareInterface
{
    /**
     * @var list<string>
     */
    public static array $log = [];

    public function process(\WP_REST_Request $request, callable $next): \WP_REST_Response
    {
        $name = (new \ReflectionClass($this))->getShortName();
        self::$log[] = $name . ':before';
        $response = $next($request);
        self::$log[] = $name . ':after';

        return $response;
    }
}

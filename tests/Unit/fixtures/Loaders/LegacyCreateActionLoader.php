<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Loaders;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Loaders\AnnotatedRouteRestLoader;
use Dbout\WpRestApi\RouteAction;

/**
 * Overrides createAction() with its signature before middlewares: loading
 * this class fails with a fatal error if the signature changes.
 */
class LegacyCreateActionLoader extends AnnotatedRouteRestLoader
{
    public int $calls = 0;

    protected function createAction(
        \ReflectionClass $reflectionClass,
        \ReflectionMethod $method,
        Route $route,
        Action $action
    ): RouteAction {
        $this->calls++;

        return parent::createAction($reflectionClass, $method, $route, $action);
    }
}

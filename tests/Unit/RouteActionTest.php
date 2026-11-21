<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit;

use Dbout\WpRestApi\ParamSpec;
use Dbout\WpRestApi\RouteAction;
use Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares\FirstMiddleware;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Dbout\WpRestApi\RouteAction
 */
class RouteActionTest extends TestCase
{
    /**
     * @covers ::withMiddlewares
     */
    public function testWithMiddlewaresReturnsACopy(): void
    {
        $params = [new ParamSpec('id', 'id', ['type' => 'integer'])];
        $action = new RouteAction('App\\Controller', 'list', ['GET'], 'is_user_logged_in', $params);

        $copy = $action->withMiddlewares([FirstMiddleware::class]);

        $this->assertNotSame($action, $copy);
        $this->assertSame([], $action->middlewares, 'The original action is not modified.');
        $this->assertSame([FirstMiddleware::class], $copy->middlewares);
        $this->assertSame('App\\Controller', $copy->className);
        $this->assertSame('list', $copy->methodName);
        $this->assertSame(['GET'], $copy->methods);
        $this->assertSame('is_user_logged_in', $copy->permissionCallback);
        $this->assertSame($params, $copy->params);
    }
}

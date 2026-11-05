<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit;

use Dbout\WpRestApi\Exceptions\ApiException;
use Dbout\WpRestApi\Route;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Dbout\WpRestApi\Route
 */
class RouteTest extends TestCase
{
    /**
     * @covers ::__construct
     */
    public function testValidRoute(): void
    {
        $route = new Route('app/v1', '/ping', []);

        $this->assertSame('app/v1', $route->namespace);
        $this->assertSame('/ping', $route->path);
        $this->assertSame([], $route->actions);
    }

    /**
     * @covers ::__construct
     * @dataProvider providerInvalidRoutes
     */
    public function testEmptyNamespaceOrPathThrows(string $namespace, string $path, string $expectedMessage): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage($expectedMessage);

        new Route($namespace, $path, []);
    }

    /**
     * @return \Generator<string, array{string, string, string}>
     */
    public static function providerInvalidRoutes(): \Generator
    {
        yield 'Empty namespace' => ['', '/ping', 'The route namespace cannot be empty'];
        yield 'Falsy namespace' => ['0', '/ping', 'The route namespace cannot be empty'];
        yield 'Empty path' => ['app/v1', '', 'The route path cannot be empty'];
        yield 'Falsy path' => ['app/v1', '0', 'The route path cannot be empty'];
    }
}

<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\Loaders;

use Dbout\WpRestApi\Loaders\AnnotatedRouteRestLoader;
use Dbout\WpRestApi\ParamSpec;
use Dbout\WpRestApi\Route;
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\SortDirection;
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithEnumParam\RouteWithEnumParam;
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithParams\RouteWithParams;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Dbout\WpRestApi\Loaders\AnnotatedRouteRestLoader
 */
class AnnotatedRouteRestLoaderTest extends TestCase
{
    /**
     * @covers ::collectParams
     * @covers ::compileParam
     * @covers ::inferWpType
     * @covers ::inferSanitizeCallback
     */
    public function testParamAttributeIsCollected(): void
    {
        $route = (new AnnotatedRouteRestLoader())->load(RouteWithParams::class);

        $this->assertInstanceOf(Route::class, $route);
        $this->assertCount(1, $route->actions);

        $params = $route->actions[0]->params;
        $this->assertCount(3, $params, 'Each #[Param]-annotated argument must produce a ParamSpec.');

        $byRequestName = [];
        foreach ($params as $spec) {
            $this->assertInstanceOf(ParamSpec::class, $spec);
            $byRequestName[$spec->requestName] = $spec;
        }

        $this->assertSame('id', $byRequestName['id']->phpName);
        $this->assertSame([
            'type' => 'integer',
            'required' => true,
            'description' => 'Item identifier',
            'sanitize_callback' => 'absint',
        ], $byRequestName['id']->args);

        $this->assertSame([
            'type' => 'integer',
            'default' => 10,
            'sanitize_callback' => 'absint',
        ], $byRequestName['perPage']->args);

        $this->assertSame(
            'search',
            $byRequestName['q']->phpName,
            'Param::$name must override the request key while the PHP name stays accessible for injection.'
        );
        $this->assertSame('sanitize_text_field', $byRequestName['q']->args['sanitize_callback']);
    }

    /**
     * @covers ::compileParam
     * @covers ::inferWpType
     * @covers ::inferEnumFromBackedEnum
     */
    public function testBackedEnumParameterAutoPopulatesEnumValues(): void
    {
        $route = (new AnnotatedRouteRestLoader())->load(RouteWithEnumParam::class);

        $this->assertInstanceOf(Route::class, $route);
        $params = $route->actions[0]->params;
        $this->assertCount(1, $params);

        $spec = $params[0];
        $this->assertSame('string', $spec->args['type']);
        $this->assertSame(
            [SortDirection::Asc->value, SortDirection::Desc->value],
            $spec->args['enum'],
            'BackedEnum cases must be unrolled to their backing values.'
        );
        $this->assertTrue($spec->args['required']);
    }
}
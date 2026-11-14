<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\Loaders;

use Dbout\WpRestApi\Exceptions\ApiException;
use Dbout\WpRestApi\Loaders\AnnotatedRouteRestLoader;
use Dbout\WpRestApi\Permissions\IsAdministrator;
use Dbout\WpRestApi\Permissions\IsEditor;
use Dbout\WpRestApi\Permissions\IsPublic;
use Dbout\WpRestApi\ParamSpec;
use Dbout\WpRestApi\Route;
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\SortDirection;
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithActionPaths\RouteWithActionPaths;
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithEnumParam\RouteWithEnumParam;
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithIntParams\RouteWithIntParams;
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithParams\RouteWithParams;
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithPermissions\RouteWithPermissions;
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
        ], $byRequestName['id']->args);

        $this->assertSame([
            'type' => 'integer',
            'default' => 10,
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

    /**
     * @covers ::compileParam
     * @covers ::inferSanitizeCallback
     */
    public function testIntParamHasNoAbsintSanitizer(): void
    {
        $route = (new AnnotatedRouteRestLoader())->load(RouteWithIntParams::class);

        $this->assertInstanceOf(Route::class, $route);
        $byRequestName = [];
        foreach ($route->actions[0]->params as $spec) {
            $byRequestName[$spec->requestName] = $spec->args;
        }

        $this->assertSame('integer', $byRequestName['offset']['type']);
        $this->assertArrayNotHasKey(
            'sanitize_callback',
            $byRequestName['offset'],
            'An int param MUST NOT get absint: WordPress casts it from the schema type and keeps negative values.'
        );
        $this->assertSame(
            'absint',
            $byRequestName['page']['sanitize_callback'] ?? null,
            'An explicit sanitizeCallback MUST still be used.'
        );
    }

    /**
     * @covers ::createAction
     */
    public function testActionInheritsRoutePermissionByDefault(): void
    {
        $this->assertSame(IsAdministrator::class, $this->permissionsByMethod()['inherited']);
    }

    /**
     * @covers ::createAction
     */
    public function testActionCanOverrideToPublicViaIsPublic(): void
    {
        $this->assertSame(IsPublic::class, $this->permissionsByMethod()['public']);
    }

    /**
     * @covers ::createAction
     */
    public function testActionPermissionOverridesRoutePermission(): void
    {
        $permissions = $this->permissionsByMethod();

        $this->assertSame(IsEditor::class, $permissions['overridden']);
        $this->assertSame('is_user_logged_in', $permissions['callable']);
    }

    /**
     * @return array<string, mixed> Permission callback of each action, keyed by method name.
     */
    protected function permissionsByMethod(): array
    {
        $route = (new AnnotatedRouteRestLoader())->load(RouteWithPermissions::class);
        $this->assertInstanceOf(Route::class, $route);

        $permissions = [];
        foreach ($route->actions as $action) {
            $permissions[$action->methodName] = $action->permissionCallback;
        }

        return $permissions;
    }

    /**
     * @covers ::loadRoutes
     * @covers ::buildPath
     */
    public function testActionPathCreatesSeparateRoutes(): void
    {
        $routes = (new AnnotatedRouteRestLoader())->loadRoutes(RouteWithActionPaths::class);

        $this->assertCount(2, $routes, 'One route per distinct final path.');

        $actionsByPath = [];
        foreach ($routes as $route) {
            $this->assertSame('loader-test/v1', $route->namespace);
            $actionsByPath[$route->path] = array_map(
                static fn ($action): string => $action->methodName,
                $route->actions,
            );
        }

        $this->assertSame([
            '/documents' => ['list', 'create'],
            '/documents/(?P<id>\d+)' => ['get', 'delete'],
        ], $actionsByPath, 'Paths are joined with a single slash, without leading or trailing slash.');

        $delete = $routes[1]->actions[1];
        $this->assertSame(IsAdministrator::class, $delete->permissionCallback);
        $this->assertSame(['DELETE'], $delete->methods);
    }

    /**
     * @covers ::load
     */
    public function testLoadThrowsWhenClassDeclaresSeveralPaths(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('loadRoutes()');

        (new AnnotatedRouteRestLoader())->load(RouteWithActionPaths::class);
    }

    /**
     * @covers ::loadRoutes
     */
    public function testLoadRoutesReturnsEmptyListForClassWithoutRoute(): void
    {
        $this->assertSame([], (new AnnotatedRouteRestLoader())->loadRoutes(\stdClass::class));
    }
}

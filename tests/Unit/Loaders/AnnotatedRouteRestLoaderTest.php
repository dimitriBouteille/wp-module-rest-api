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
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithAbstractMiddleware\RouteWithAbstractMiddleware;
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithActionPaths\RouteWithActionPaths;
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithEmptyPath\RouteWithEmptyPath;
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithInvalidMiddleware\RouteWithInvalidMiddleware;
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithInvalidResolver\RouteWithInvalidResolver;
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithMiddlewares\RouteWithMiddlewares;
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithPlaceholders\RouteWithPlaceholders;
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithEnumParam\RouteWithEnumParam;
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithIntParams\RouteWithIntParams;
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithParams\RouteWithParams;
use Dbout\WpRestApi\Tests\Unit\fixtures\Loaders\WithPermissions\RouteWithPermissions;
use Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares\AbstractMiddleware;
use Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares\FirstMiddleware;
use Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares\SecondMiddleware;
use Dbout\WpRestApi\Tests\Unit\fixtures\Middlewares\ThirdMiddleware;
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
     * @covers ::buildPath
     */
    public function testEmptyFinalPathThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('The route path of ' . RouteWithEmptyPath::class . '::list() cannot be empty');

        (new AnnotatedRouteRestLoader())->loadRoutes(RouteWithEmptyPath::class);
    }

    /**
     * @covers ::loadRoutes
     */
    public function testLoadRoutesReturnsEmptyListForClassWithoutRoute(): void
    {
        $this->assertSame([], (new AnnotatedRouteRestLoader())->loadRoutes(\stdClass::class));
    }

    /**
     * @covers ::loadRoutes
     */
    public function testPlaceholdersAreCompiledAndGroupedWithRawRegex(): void
    {
        $routes = (new AnnotatedRouteRestLoader())->loadRoutes(RouteWithPlaceholders::class);

        $actionsByPath = [];
        foreach ($routes as $route) {
            $actionsByPath[$route->path] = array_map(
                static fn ($action): string => $action->methodName,
                $route->actions,
            );
        }

        $this->assertSame([
            '/countries/(?P<code>[A-Z]{3})' => ['get', 'update'],
            '/countries/(?P<code>[A-Z]{3})/cities/(?P<city>[^/]+)' => ['city'],
        ], $actionsByPath, 'A placeholder and the matching raw regex MUST give the same route.');
    }

    /**
     * @covers ::loadRoutes
     * @covers ::assertResolvers
     */
    public function testInvalidResolverClassThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('The resolver stdClass of the argument $value in ' . RouteWithInvalidResolver::class . '::get() must be an existing class implementing');

        (new AnnotatedRouteRestLoader())->loadRoutes(RouteWithInvalidResolver::class);
    }

    /**
     * @covers ::collectMiddlewares
     * @covers ::createAction
     */
    public function testMiddlewaresAreCollectedClassThenMethod(): void
    {
        $routes = (new AnnotatedRouteRestLoader())->loadRoutes(RouteWithMiddlewares::class);

        $byMethod = [];
        foreach ($routes[0]->actions as $action) {
            $byMethod[$action->methodName] = $action->middlewares;
        }

        $this->assertSame(
            [FirstMiddleware::class, SecondMiddleware::class, ThirdMiddleware::class],
            $byMethod['list'],
        );
        $this->assertSame(
            [FirstMiddleware::class, SecondMiddleware::class],
            $byMethod['create'],
            'An action without #[Middleware] keeps the class middlewares.',
        );
    }

    /**
     * @covers ::collectMiddlewares
     */
    public function testInvalidMiddlewareClassThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('The middleware stdClass of ' . RouteWithInvalidMiddleware::class . '::get() must be an existing class implementing');

        (new AnnotatedRouteRestLoader())->loadRoutes(RouteWithInvalidMiddleware::class);
    }

    /**
     * is_a() accepts abstract classes: the loader must refuse them, or the
     * route would fail on every request.
     *
     * @covers ::collectMiddlewares
     */
    public function testAbstractMiddlewareClassThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('The middleware ' . AbstractMiddleware::class . ' of ' . RouteWithAbstractMiddleware::class . ' must be an existing class implementing');

        (new AnnotatedRouteRestLoader())->loadRoutes(RouteWithAbstractMiddleware::class);
    }
}

<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Loaders;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Middleware;
use Dbout\WpRestApi\Attributes\Param;
use Dbout\WpRestApi\Attributes\Resolve;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;
use Dbout\WpRestApi\Exceptions\ApiException;
use Dbout\WpRestApi\Helpers\PathCompiler;
use Dbout\WpRestApi\Middleware\MiddlewareInterface;
use Dbout\WpRestApi\ParamSpec;
use Dbout\WpRestApi\Resolvers\ParameterResolverInterface;
use Dbout\WpRestApi\Route as RestRoute;
use Dbout\WpRestApi\RouteAction;
use Dbout\WpRestApi\Wrappers\ReflectionCache;

class AnnotatedRouteRestLoader implements InterfaceLoader
{
    /**
     * Loads a class declaring a single path.
     *
     * @inheritDoc
     * @deprecated Use {@see loadRoutes()}, which supports #[Action(path: ...)].
     * @throws ApiException When the class declares several paths with #[Action(path: ...)].
     */
    public function load($resource): ?RestRoute
    {
        $routes = $this->loadRoutes($resource);
        if (count($routes) > 1) {
            throw new ApiException(sprintf(
                'Class "%s" declares several paths with #[Action(path: ...)], use %s::loadRoutes() to load them.',
                $resource,
                self::class,
            ));
        }

        return $routes[0] ?? null;
    }

    /**
     * Loads the routes of a class: one route per final path, the #[Route]
     * path followed by the optional #[Action] path. Placeholders such as
     * `{id:\d+}` are compiled here, so cached routes hold the final regex.
     *
     * @param mixed $resource Class name.
     * @throws \InvalidArgumentException When the class does not exist or is abstract.
     * @throws ApiException When a final path is empty, has an invalid placeholder
     *                      or a #[Middleware] class is invalid.
     * @return list<RestRoute> Empty when the class has no #[Route] or no #[Action].
     */
    public function loadRoutes(mixed $resource): array
    {
        if (!is_string($resource) || !class_exists($resource)) {
            throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', is_string($resource) ? $resource : get_debug_type($resource)));
        }

        $class = new \ReflectionClass($resource);
        if ($class->isAbstract()) {
            throw new \InvalidArgumentException(sprintf(
                'Annotations from class "%s" cannot be read as it is abstract.',
                $class->getName()
            ));
        }

        $route = $class->getAttributes(Route::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
        $route = $route?->newInstance();
        if (!$route instanceof Route) {
            return [];
        }

        $classMiddlewares = $this->collectMiddlewares($class);

        /** @var array<string, list<RouteAction>> $actionsByPath */
        $actionsByPath = [];
        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $action = $method->getAttributes(Action::class)[0] ?? null;
            $action = $action?->newInstance();
            if (!$action instanceof Action) {
                continue;
            }

            $this->assertResolvers($method);
            $path = $this->buildPath($route->route, $action->path);
            if ($path === '/') {
                throw new ApiException(sprintf(
                    'The route path of %s::%s() cannot be empty: set a path on #[Route] or #[Action].',
                    $class->getName(),
                    $method->getName(),
                ));
            }

            $path = PathCompiler::compile($path);
            $routeAction = $this->createAction($class, $method, $route, $action);
            // Added here rather than through createAction(), whose signature
            // is kept for the classes overriding it.
            $middlewares = [...$classMiddlewares, ...$this->collectMiddlewares($method)];
            if ($middlewares !== []) {
                $routeAction = $routeAction->withMiddlewares($middlewares);
            }

            $actionsByPath[$path][] = $routeAction;
        }

        $routes = [];
        foreach ($actionsByPath as $path => $actions) {
            $routes[] = new RestRoute($route->namespace, (string) $path, $actions);
        }

        return $routes;
    }

    /**
     * Checks the #[Resolve] attributes when routes are loaded, so an invalid
     * resolver fails at registration time rather than on the first request.
     *
     * @param \ReflectionMethod $method
     * @throws ApiException
     * @return void
     */
    protected function assertResolvers(\ReflectionMethod $method): void
    {
        foreach ($method->getParameters() as $parameter) {
            $attribute = $parameter->getAttributes(Resolve::class)[0] ?? null;
            if ($attribute === null) {
                continue;
            }

            $resolver = $attribute->newInstance()->resolver;
            if (!ReflectionCache::isParameterResolver($resolver)) {
                throw new ApiException(sprintf(
                    'The resolver %s of the argument $%s in %s::%s() must be an existing class implementing %s.',
                    $resolver,
                    $parameter->getName(),
                    $method->getDeclaringClass()->getName(),
                    $method->getName(),
                    ParameterResolverInterface::class,
                ));
            }
        }
    }

    /**
     * Returns the #[Middleware] classes declared on a class or a method, in
     * declaration order, checked when routes are loaded so an invalid class
     * fails at registration time rather than on the first request.
     *
     * @param \ReflectionClass<object>|\ReflectionMethod $reflector
     * @throws ApiException When a class does not exist, does not implement
     *                      MiddlewareInterface or cannot be instantiated without arguments.
     * @return list<string>
     */
    protected function collectMiddlewares(\ReflectionClass|\ReflectionMethod $reflector): array
    {
        $middlewares = [];
        foreach ($reflector->getAttributes(Middleware::class) as $attribute) {
            foreach ($attribute->newInstance()->classes as $middleware) {
                if (!ReflectionCache::isMiddleware($middleware)) {
                    throw new ApiException(sprintf(
                        'The middleware %s of %s must be an existing class implementing %s, instantiable without arguments.',
                        $middleware,
                        $reflector instanceof \ReflectionMethod
                            ? sprintf('%s::%s()', $reflector->getDeclaringClass()->getName(), $reflector->getName())
                            : $reflector->getName(),
                        MiddlewareInterface::class,
                    ));
                }

                $middlewares[] = $middleware;
            }
        }

        return $middlewares;
    }

    /**
     * Joins the #[Route] path and the #[Action] path with a single slash.
     *
     * Leading and trailing slashes are trimmed as register_rest_route() does,
     * so '/documents/' and 'documents' give the same route.
     *
     * @param string $routePath
     * @param string|null $actionPath
     * @return string '/' when both paths are empty.
     */
    protected function buildPath(string $routePath, ?string $actionPath): string
    {
        $segments = array_filter(
            [trim($routePath, '/'), trim($actionPath ?? '', '/')],
            static fn (string $segment): bool => $segment !== '',
        );

        return '/' . implode('/', $segments);
    }

    /**
     * @param Route $route
     * @param array<RouteAction> $actions
     * @return RestRoute
     * @deprecated Not used anymore: routes are built by {@see loadRoutes()}.
     */
    protected function createRoute(Route $route, array $actions): RestRoute
    {
        return new RestRoute(
            $route->namespace,
            $route->route,
            $actions
        );
    }

    /**
     * @param \ReflectionClass $reflectionClass
     * @param \ReflectionMethod $method
     * @param Route $route
     * @param Action $action
     * @return RouteAction
     */
    protected function createAction(
        \ReflectionClass $reflectionClass,
        \ReflectionMethod $method,
        Route $route,
        Action $action
    ): RouteAction {
        $methods = [];
        if ($action->methods instanceof Method) {
            $methods[] = $action->methods->value;
        } elseif (is_array($action->methods)) {
            foreach ($action->methods as $m) {
                $methods[] = $m->value;
            }
        }

        return new RouteAction(
            $reflectionClass->getName(),
            $method->getName(),
            $methods,
            $action->permissionCallback ?? $route->permissionCallback,
            $this->collectParams($method),
        );
    }

    /**
     * @param \ReflectionMethod $method
     * @return array<ParamSpec>
     */
    protected function collectParams(\ReflectionMethod $method): array
    {
        $params = [];
        foreach ($method->getParameters() as $parameter) {
            $attribute = $parameter->getAttributes(Param::class)[0] ?? null;
            if ($attribute === null) {
                continue;
            }

            /** @var Param $param */
            $param = $attribute->newInstance();
            $params[] = $this->compileParam($parameter, $param);
        }

        return $params;
    }

    /**
     * Translate a single Param attribute (+ the PHP type) into the WP args shape.
     */
    protected function compileParam(\ReflectionParameter $parameter, Param $param): ParamSpec
    {
        $phpName = $parameter->getName();
        $requestName = $param->name ?? $phpName;

        $reflectionType = $parameter->getType();
        $phpType = $reflectionType instanceof \ReflectionNamedType ? $reflectionType->getName() : null;
        $nullable = $reflectionType instanceof \ReflectionNamedType && $reflectionType->allowsNull();

        $args = [];

        $type = $param->type ?? $this->inferWpType($phpType);
        if ($type !== null) {
            $args['type'] = $nullable ? [$type, 'null'] : $type;
        }

        if ($param->required) {
            $args['required'] = true;
        }

        if ($param->default !== null) {
            $args['default'] = $param->default;
        } elseif ($parameter->isDefaultValueAvailable() && !$param->required) {
            $args['default'] = $parameter->getDefaultValue();
        }

        $enum = $param->enum ?? $this->inferEnumFromBackedEnum($phpType);
        if ($enum !== null) {
            $args['enum'] = $enum;
        }

        if ($param->description !== null) {
            $args['description'] = $param->description;
        }

        $sanitize = $param->sanitizeCallback ?? $this->inferSanitizeCallback($phpType);
        if ($sanitize !== null) {
            $args['sanitize_callback'] = $sanitize;
        }

        if ($param->validateCallback !== null) {
            $args['validate_callback'] = $param->validateCallback;
        }

        return new ParamSpec($phpName, $requestName, $args);
    }

    /**
     * @param string|null $phpType
     * @throws \ReflectionException
     * @return string|null
     */
    protected function inferWpType(?string $phpType): ?string
    {
        if ($phpType === null) {
            return null;
        }

        if (is_subclass_of($phpType, \BackedEnum::class)) {
            $reflectionEnum = new \ReflectionEnum($phpType);
            $backing = $reflectionEnum->getBackingType();
            return $backing instanceof \ReflectionNamedType && $backing->getName() === 'int'
                ? 'integer'
                : 'string';
        }

        return match ($phpType) {
            'int' => 'integer',
            'float' => 'number',
            'bool' => 'boolean',
            'string' => 'string',
            'array' => 'array',
            default => null,
        };
    }

    /**
     * @param string|null $phpType
     * @return array<int|string>|null
     */
    protected function inferEnumFromBackedEnum(?string $phpType): ?array
    {
        if ($phpType === null || !is_subclass_of($phpType, \BackedEnum::class)) {
            return null;
        }

        $values = [];
        foreach ($phpType::cases() as $case) {
            $values[] = $case->value;
        }

        return $values;
    }

    /**
     * `int` and `float` have no sanitizer: WordPress then uses
     * rest_parse_request_arg(), which validates and casts the value from the
     * inferred schema type. absint() is not used, it turns -5 into 5.
     *
     * @param string|null $phpType
     * @return string|null
     */
    protected function inferSanitizeCallback(?string $phpType): ?string
    {
        return match ($phpType) {
            'string' => 'sanitize_text_field',
            'bool' => 'rest_sanitize_boolean',
            default => null,
        };
    }
}

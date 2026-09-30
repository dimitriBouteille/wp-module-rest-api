<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Loaders;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Param;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;
use Dbout\WpRestApi\ParamSpec;
use Dbout\WpRestApi\Route as RestRoute;
use Dbout\WpRestApi\RouteAction;

class AnnotatedRouteRestLoader implements InterfaceLoader
{
    /**
     * @inheritDoc
     */
    public function load($resource): ?RestRoute
    {
        if (!class_exists($resource)) {
            throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', $resource));
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
            return null;
        }

        $actions = [];
        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $action = $method->getAttributes(Action::class)[0] ?? null;
            $action = $action?->newInstance();
            if (!$action instanceof Action) {
                continue;
            }

            $actions[] = $this->createAction($class, $method, $route, $action);
        }

        if ($actions === []) {
            return null;
        }

        return $this->createRoute($route, $actions);
    }

    /**
     * @param Route $route
     * @param array<RouteAction> $actions
     * @return RestRoute
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
     * @param string|null $phpType
     * @return string|null
     */
    protected function inferSanitizeCallback(?string $phpType): ?string
    {
        return match ($phpType) {
            'int' => 'absint',
            'string' => 'sanitize_text_field',
            'bool' => 'rest_sanitize_boolean',
            default => null,
        };
    }
}

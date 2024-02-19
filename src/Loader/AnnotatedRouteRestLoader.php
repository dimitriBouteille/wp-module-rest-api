<?php
/**
 * Copyright (c) 2024 Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Loader;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;
use Dbout\WpRestApi\Route as RestRoute;
use Dbout\WpRestApi\RouteAction;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;

class AnnotatedRouteRestLoader implements LoaderInterface
{
    /**
     * @param $resource
     * @param string|null $type
     * @return ?RestRoute
     */
    public function load($resource, string $type = null): ?RestRoute
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
     * @param array $actions
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
                $methods[] = $m instanceof Method ? $m->value : $m;
            }
        }

        return new RouteAction(
            $reflectionClass->getName(),
            $method->getName(),
            $methods,
            $action->permissionCallback ?? $route->permissionCallback
        );
    }

    /**
     * @inheritDoc
     */
    public function supports($resource, string $type = null): bool
    {
        return \is_string($resource) && preg_match('/^(?:\\\\?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)+$/', $resource);
    }

    /**
     * @inheritDoc
     */
    public function setResolver(LoaderResolverInterface $resolver)
    {
    }

    /**
     * @inheritDoc
     */
    public function getResolver()
    {
    }
}
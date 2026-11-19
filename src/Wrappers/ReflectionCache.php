<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Wrappers;

use Dbout\WpRestApi\Attributes\Param;
use Dbout\WpRestApi\Attributes\Resolve;
use Dbout\WpRestApi\Middleware\MiddlewareInterface;
use Dbout\WpRestApi\Permissions\PermissionInterface;
use Dbout\WpRestApi\Resolvers\ParameterResolverInterface;

/**
 * In-process memoization layer for the reflection lookups performed on
 * every REST request. Class metadata is constant for a worker's lifetime,
 * so we resolve it once and reuse the result.
 */
final class ReflectionCache
{
    /** @var array<string, ParameterDescriptor[]> */
    private static array $parameters = [];

    /** @var array<class-string, bool> */
    private static array $implementsPermissionInterface = [];

    /** @var array<string, bool> */
    private static array $isParameterResolver = [];

    /** @var array<string, bool> */
    private static array $isMiddleware = [];

    /** @var array<string, bool> */
    private static array $isInstantiableWithoutArguments = [];

    /**
     * Returns the resolved parameter descriptors for the given method.
     *
     * @param class-string $class
     * @throws \ReflectionException
     * @return ParameterDescriptor[]
     */
    public static function parameters(string $class, string $method): array
    {
        $key = $class . '::' . $method;
        if (isset(self::$parameters[$key])) {
            return self::$parameters[$key];
        }

        $reflection = new \ReflectionMethod($class, $method);
        $descriptors = [];
        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            $paramAttribute = $parameter->getAttributes(Param::class)[0] ?? null;
            $paramInstance = $paramAttribute?->newInstance();
            $resolveAttribute = $parameter->getAttributes(Resolve::class)[0] ?? null;
            $descriptors[] = new ParameterDescriptor(
                name: $parameter->getName(),
                requestName: $paramInstance instanceof Param && $paramInstance->name !== null
                    ? $paramInstance->name
                    : $parameter->getName(),
                position: $parameter->getPosition(),
                typeName: $type instanceof \ReflectionNamedType ? $type->getName() : null,
                hasDefaultValue: $parameter->isDefaultValueAvailable(),
                allowsNull: $type === null || $type->allowsNull(),
                isVariadic: $parameter->isVariadic(),
                resolverClass: $resolveAttribute?->newInstance()->resolver,
            );
        }

        return self::$parameters[$key] = $descriptors;
    }

    /**
     * Whether the given class implements PermissionInterface.
     *
     * @param class-string $class
     */
    public static function implementsPermissionInterface(string $class): bool
    {
        if (isset(self::$implementsPermissionInterface[$class])) {
            return self::$implementsPermissionInterface[$class];
        }

        return self::$implementsPermissionInterface[$class] =
            (new \ReflectionClass($class))->implementsInterface(PermissionInterface::class);
    }

    /**
     * Whether the given class exists, is not abstract and implements
     * ParameterResolverInterface. Its constructor is not checked, the
     * container can provide it.
     *
     * @param string $class
     * @phpstan-assert-if-true class-string<ParameterResolverInterface> $class
     */
    public static function isParameterResolver(string $class): bool
    {
        if (isset(self::$isParameterResolver[$class])) {
            return self::$isParameterResolver[$class];
        }

        return self::$isParameterResolver[$class] = self::isConcreteClassImplementing($class, ParameterResolverInterface::class);
    }

    /**
     * Whether the given class can be used as a middleware: it exists, is not
     * abstract and implements MiddlewareInterface. Its constructor is not
     * checked, the container can provide it.
     *
     * @param string $class
     * @phpstan-assert-if-true class-string<MiddlewareInterface> $class
     */
    public static function isMiddleware(string $class): bool
    {
        if (isset(self::$isMiddleware[$class])) {
            return self::$isMiddleware[$class];
        }

        return self::$isMiddleware[$class] = self::isConcreteClassImplementing($class, MiddlewareInterface::class);
    }

    /**
     * Whether `new $class()` works: the class exists, is not abstract, has a
     * public constructor and no required constructor parameter.
     *
     * @param class-string $class
     */
    public static function isInstantiableWithoutArguments(string $class): bool
    {
        if (isset(self::$isInstantiableWithoutArguments[$class])) {
            return self::$isInstantiableWithoutArguments[$class];
        }

        if (!class_exists($class)) {
            return self::$isInstantiableWithoutArguments[$class] = false;
        }

        $reflection = new \ReflectionClass($class);

        return self::$isInstantiableWithoutArguments[$class] = $reflection->isInstantiable()
            && ($reflection->getConstructor()?->getNumberOfRequiredParameters() ?? 0) === 0;
    }

    /**
     * @param string $class
     * @param class-string $interface
     * @return bool
     */
    private static function isConcreteClassImplementing(string $class, string $interface): bool
    {
        if (!class_exists($class)) {
            return false;
        }

        $reflection = new \ReflectionClass($class);

        return !$reflection->isAbstract() && $reflection->implementsInterface($interface);
    }

    /**
     * Clear the cache. Intended for tests that swap class definitions
     * between scenarios; production code never needs this.
     */
    public static function clear(): void
    {
        self::$parameters = [];
        self::$implementsPermissionInterface = [];
        self::$isParameterResolver = [];
        self::$isMiddleware = [];
        self::$isInstantiableWithoutArguments = [];
    }
}

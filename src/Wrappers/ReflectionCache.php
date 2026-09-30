<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Wrappers;

use Dbout\WpRestApi\Attributes\Param;
use Dbout\WpRestApi\Permissions\PermissionInterface;

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
            $descriptors[] = new ParameterDescriptor(
                name: $parameter->getName(),
                requestName: $paramInstance instanceof Param && $paramInstance->name !== null
                    ? $paramInstance->name
                    : $parameter->getName(),
                position: $parameter->getPosition(),
                typeName: $type instanceof \ReflectionNamedType ? $type->getName() : null,
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
     * Clear the cache. Intended for tests that swap class definitions
     * between scenarios; production code never needs this.
     */
    public static function clear(): void
    {
        self::$parameters = [];
        self::$implementsPermissionInterface = [];
    }
}

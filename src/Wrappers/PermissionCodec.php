<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Wrappers;

use Dbout\WpRestApi\Permissions\CacheablePermissionInterface;

/**
 * Turns a permissionCallback into a JSON-safe value for the route cache,
 * and back.
 *
 * Class names, function names and [class, method] arrays are kept as they
 * are. A CacheablePermissionInterface object is stored as its class name and
 * constructor arguments, and rebuilt with `new $class(...$arguments)`. Any
 * other object, such as a Closure, cannot be cached.
 *
 * @internal
 */
final class PermissionCodec
{
    private const CLASS_KEY = '@permission';
    private const ARGUMENTS_KEY = 'arguments';

    /**
     * @param mixed $permission
     * @throws \InvalidArgumentException When the value contains an object
     *                                   that cannot be rebuilt.
     * @return mixed
     */
    public static function encode(mixed $permission): mixed
    {
        if ($permission === null || is_scalar($permission)) {
            return $permission;
        }

        if (is_array($permission)) {
            return array_map(self::encode(...), $permission);
        }

        if ($permission instanceof CacheablePermissionInterface) {
            return [
                self::CLASS_KEY => $permission::class,
                self::ARGUMENTS_KEY => array_map(self::encode(...), $permission->cacheArguments()),
            ];
        }

        throw new \InvalidArgumentException(sprintf('A %s permission cannot be cached.', get_debug_type($permission)));
    }

    /**
     * @param mixed $payload Value read from the cache; not yet validated.
     * @throws \InvalidArgumentException When an encoded object is invalid.
     * @throws \Throwable When the permission constructor fails.
     * @return mixed
     */
    public static function decode(mixed $payload): mixed
    {
        if (!is_array($payload)) {
            return $payload;
        }

        if (!array_key_exists(self::CLASS_KEY, $payload)) {
            return array_map(self::decode(...), $payload);
        }

        $class = $payload[self::CLASS_KEY];
        $arguments = $payload[self::ARGUMENTS_KEY] ?? null;
        if (
            !is_string($class)
            || !is_array($arguments)
            || !array_is_list($arguments)
            || !class_exists($class)
            || !is_subclass_of($class, CacheablePermissionInterface::class)
            || !(new \ReflectionClass($class))->isInstantiable()
        ) {
            throw new \InvalidArgumentException('Invalid cached permission.');
        }

        return new $class(...array_map(self::decode(...), $arguments));
    }
}

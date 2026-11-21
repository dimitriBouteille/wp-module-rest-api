<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Wrappers;

use Dbout\WpRestApi\Exceptions\ApiException;
use Psr\Container\ContainerInterface;

/**
 * Creates the user classes the package gets as class names: controllers,
 * permissions, #[Resolve] resolvers and middlewares.
 *
 * The PSR-11 container of RouteLoaderOptions is used when it has the class,
 * otherwise the class is instantiated without arguments.
 *
 * @internal
 */
class ClassFactory
{
    /**
     * @param ContainerInterface|null $container
     */
    public function __construct(
        protected ?ContainerInterface $container = null,
    ) {
    }

    /**
     * Whether create() can build the class: the container has it, or it can
     * be instantiated without arguments.
     *
     * @param class-string $className
     * @return bool
     */
    public function canCreate(string $className): bool
    {
        return $this->container?->has($className) === true
            || ReflectionCache::isInstantiableWithoutArguments($className);
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @throws ApiException When the class cannot be instantiated without
     *                      arguments and the container does not have it, or
     *                      when the container returns another type.
     * @return T
     */
    public function create(string $className): object
    {
        if ($this->container?->has($className) === true) {
            $instance = $this->container->get($className);
            if (!$instance instanceof $className) {
                throw new ApiException(sprintf(
                    'The container returned %s for the id %s, an instance of this class was expected.',
                    get_debug_type($instance),
                    $className,
                ));
            }

            return $instance;
        }

        if (!ReflectionCache::isInstantiableWithoutArguments($className)) {
            throw new ApiException(self::cannotCreateMessage($className, $this->container instanceof ContainerInterface));
        }

        return new $className();
    }

    /**
     * @param string $className
     * @param bool $hasContainer
     * @return string
     */
    public static function cannotCreateMessage(string $className, bool $hasContainer): string
    {
        return $hasContainer
            ? sprintf(
                'The class %s cannot be instantiated without arguments and the container does not provide it: register it as a public service.',
                $className,
            )
            : sprintf(
                'The class %s cannot be instantiated without arguments: set RouteLoaderOptions::$container to inject its dependencies.',
                $className,
            );
    }
}

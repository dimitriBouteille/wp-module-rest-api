<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Container;

use Psr\Container\ContainerInterface;

/**
 * Minimal PSR-11 container: services are given as instances.
 */
class ArrayContainer implements ContainerInterface
{
    /**
     * @var list<string>
     */
    public array $requested = [];

    /**
     * @param array<string, mixed> $services
     */
    public function __construct(private readonly array $services = [])
    {
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new ServiceNotFoundException(sprintf('Service %s not found.', $id));
        }

        $this->requested[] = $id;

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}

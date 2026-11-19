<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\Container;

use Dbout\WpRestApi\Attributes\Resolve;

class ControllerWithDependency
{
    public function __construct(private readonly Greeter $greeter)
    {
    }

    public function hello(): \WP_REST_Response
    {
        return new \WP_REST_Response(['message' => $this->greeter->greeting]);
    }

    public function resolved(#[Resolve(ResolverWithDependency::class)] string $value): \WP_REST_Response
    {
        return new \WP_REST_Response(['value' => $value]);
    }
}

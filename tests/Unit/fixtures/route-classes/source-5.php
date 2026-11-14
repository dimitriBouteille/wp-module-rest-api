<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace App\Routes;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;
use Dbout\WpRestApi\Permissions\IsAdministrator;
use Dbout\WpRestApi\Permissions\IsPublic;

#[Route('app/v2', '/status', permissionCallback: IsAdministrator::class)]
class MyRoute
{
    #[Action(Method::GET, permissionCallback: IsPublic::class)]
    public function get(): \WP_REST_Response
    {
        $payload = new class () implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['class' => self::class];
            }
        };

        return new \WP_REST_Response($payload);
    }
}

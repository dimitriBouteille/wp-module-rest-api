<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\WordPress\fixtures\CrudRoute;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;
use Dbout\WpRestApi\Permissions\IsAdministrator;

#[Route('integration/v1', '/documents')]
class CrudRoute
{
    #[Action(Method::GET)]
    public function list(): array
    {
        return ['action' => 'list'];
    }

    #[Action(Method::POST)]
    public function create(): array
    {
        return ['action' => 'create'];
    }

    #[Action(Method::GET, path: '/(?P<id>\d+)')]
    public function get(int $id): array
    {
        return ['action' => 'get', 'id' => $id];
    }

    #[Action([Method::PUT, Method::PATCH], path: '/(?P<id>\d+)')]
    public function update(int $id): array
    {
        return ['action' => 'update', 'id' => $id];
    }

    #[Action(Method::DELETE, permissionCallback: IsAdministrator::class, path: '/(?P<id>\d+)')]
    public function delete(int $id): array
    {
        return ['action' => 'delete', 'id' => $id];
    }
}

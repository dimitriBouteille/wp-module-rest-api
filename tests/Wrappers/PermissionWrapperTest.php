<?php
/**
 * Copyright (c) 2024 Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Wrappers;

use Dbout\WpRestApi\RouteAction;
use Dbout\WpRestApi\Wrappers\PermissionWrapper;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Dbout\WpRestApi\Wrappers\PermissionWrapper
 */
class PermissionWrapperTest extends TestCase
{
    /**
     * @throws \Exception
     * @return void
     * @covers ::execute
     */
    public function testWithoutPermission(): void
    {
        $wrapper = $this->createWrapper(new RouteAction('MyClass', 'execute', [
            'GET',
            'POST',
        ], null));

        $request = new \WP_REST_Request();
        $this->assertTrue($wrapper->execute($request));
    }

    /**
     * @param RouteAction $action
     * @return PermissionWrapper
     */
    protected function createWrapper(RouteAction $action): PermissionWrapper
    {
        return new PermissionWrapper($action);
    }
}

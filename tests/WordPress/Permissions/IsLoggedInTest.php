<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\WordPress\Permissions;

use Dbout\WpRestApi\Permissions\IsLoggedIn;

class IsLoggedInTest extends \WP_UnitTestCase
{
    public function testAllowsLoggedInUsersOnly(): void
    {
        $permission = new IsLoggedIn();

        wp_set_current_user(0);
        $this->assertFalse($permission->allow(new \WP_REST_Request()));

        wp_set_current_user(self::factory()->user->create(['role' => 'subscriber']));
        $this->assertTrue($permission->allow(new \WP_REST_Request()));
    }
}

<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\WordPress\Permissions;

use Dbout\WpRestApi\Permissions\HasRole;

class HasRoleTest extends \WP_UnitTestCase
{
    public function testChecksTheRoleOnly(): void
    {
        $permission = new HasRole('editor');

        wp_set_current_user(0);
        $this->assertFalse($permission->allow(new \WP_REST_Request()));

        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));
        $this->assertTrue($permission->allow(new \WP_REST_Request()));

        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $this->assertFalse($permission->allow(new \WP_REST_Request()), 'An administrator does not have the editor role.');
    }
}

<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\WordPress\Permissions;

use Dbout\WpRestApi\Permissions\HasCapability;

class HasCapabilityTest extends \WP_UnitTestCase
{
    public function testChecksTheCapability(): void
    {
        $permission = new HasCapability('manage_options');

        wp_set_current_user(0);
        $this->assertFalse($permission->allow(new \WP_REST_Request()));

        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));
        $this->assertFalse($permission->allow(new \WP_REST_Request()));

        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $this->assertTrue($permission->allow(new \WP_REST_Request()));
    }

    public function testPassesTheArgumentsOfAMetaCapability(): void
    {
        $author = self::factory()->user->create(['role' => 'author']);
        $other = self::factory()->user->create(['role' => 'author']);
        $postId = self::factory()->post->create(['post_author' => $author]);
        $permission = new HasCapability('edit_post', $postId);

        wp_set_current_user($author);
        $this->assertTrue($permission->allow(new \WP_REST_Request()));

        wp_set_current_user($other);
        $this->assertFalse($permission->allow(new \WP_REST_Request()));
    }
}

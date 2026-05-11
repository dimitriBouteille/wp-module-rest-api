<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\WordPress;

use Dbout\WpRestApi\RouteLoader;

class RouteLoaderIntegrationTest extends \WP_UnitTestCase
{
    private const FIXTURES_DIR = __DIR__ . '/fixtures';

    public function testRegisteredRouteIsReachable(): void
    {
        (new RouteLoader(self::FIXTURES_DIR . '/PingRoute'))->register();
        do_action('rest_api_init');

        $response = rest_do_request(new \WP_REST_Request('GET', '/integration/v1/ping'));

        $this->assertSame(200, $response->get_status());
        $this->assertSame(['pong' => true], $response->get_data());
    }

    public function testAdminRouteReturns401WhenLoggedOut(): void
    {
        wp_set_current_user(0);
        (new RouteLoader(self::FIXTURES_DIR . '/AdminRoute'))->register();
        do_action('rest_api_init');

        $response = rest_do_request(new \WP_REST_Request('GET', '/integration/v1/admin/secret'));

        $this->assertSame(401, $response->get_status());
    }

    public function testAdminRoutePassesWithAdministrator(): void
    {
        $admin = self::factory()->user->create_and_get(['role' => 'administrator']);
        wp_set_current_user($admin->ID);

        (new RouteLoader(self::FIXTURES_DIR . '/AdminRoute'))->register();
        do_action('rest_api_init');

        $response = rest_do_request(new \WP_REST_Request('GET', '/integration/v1/admin/secret'));

        $this->assertSame(200, $response->get_status());
        $this->assertSame(['secret' => 42], $response->get_data());
    }
}

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

    public function testPathParameterIsExtractedAndCast(): void
    {
        (new RouteLoader(self::FIXTURES_DIR . '/PathParamRoute'))->register();
        do_action('rest_api_init');

        $response = rest_do_request(new \WP_REST_Request('GET', '/integration/v1/items/42'));

        $this->assertSame(200, $response->get_status());
        $this->assertSame(['id' => 42, 'type' => 'int'], $response->get_data());
    }

    public function testMultipleActionsOnSameRouteAreBothRegistered(): void
    {
        (new RouteLoader(self::FIXTURES_DIR . '/MultiMethodRoute'))->register();
        do_action('rest_api_init');

        $get = rest_do_request(new \WP_REST_Request('GET', '/integration/v1/resources'));
        $this->assertSame(200, $get->get_status());
        $this->assertSame(['action' => 'list'], $get->get_data());

        $post = rest_do_request(new \WP_REST_Request('POST', '/integration/v1/resources'));
        $this->assertSame(201, $post->get_status());
        $this->assertSame(['action' => 'create'], $post->get_data());
    }

    public function testRouteExceptionIsConvertedToJsonResponse(): void
    {
        (new RouteLoader(self::FIXTURES_DIR . '/ExceptionRoute'))->register();
        do_action('rest_api_init');

        $response = rest_do_request(new \WP_REST_Request('GET', '/integration/v1/missing'));

        $this->assertSame(404, $response->get_status());
        $error = $response->get_data()['error'] ?? null;
        $this->assertSame('not-found', $error['code']);
        $this->assertSame('Resource not found.', $error['message']);
    }
}

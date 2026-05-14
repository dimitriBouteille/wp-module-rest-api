<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\WordPress;

use Dbout\WpRestApi\ErrorFormat\ProblemJsonFormatter;
use Dbout\WpRestApi\RouteLoader;
use Dbout\WpRestApi\RouteLoaderOptions;

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

    public function testRequiredParamIsEnforcedByWordPress(): void
    {
        (new RouteLoader(self::FIXTURES_DIR . '/ParamRoute'))->register();
        do_action('rest_api_init');

        $missing = rest_do_request(new \WP_REST_Request('GET', '/integration/v1/articles'));
        $this->assertSame(
            400,
            $missing->get_status(),
            'WordPress MUST reject the call with 400 when a required #[Param] is missing.'
        );

        $request = new \WP_REST_Request('GET', '/integration/v1/articles');
        $request->set_param('id', '42'); // strings from query strings, absint coerces.
        $ok = rest_do_request($request);

        $this->assertSame(200, $ok->get_status());
        $this->assertSame(['id' => 42], $ok->get_data());
    }

    public function testRequiredParamIsHonoredOnPostJsonBody(): void
    {
        (new RouteLoader(self::FIXTURES_DIR . '/PostBodyParamRoute'))->register();
        do_action('rest_api_init');

        $missing = rest_do_request(new \WP_REST_Request('POST', '/integration/v1/articles/create'));
        $this->assertSame(
            400,
            $missing->get_status(),
            'WordPress MUST reject the POST when a required #[Param] is missing from every source.'
        );

        $jsonRequest = new \WP_REST_Request('POST', '/integration/v1/articles/create');
        $jsonRequest->set_header('content-type', 'application/json');
        $jsonRequest->set_body((string) json_encode(['id' => 7, 'title' => 'Hello']));
        $jsonResponse = rest_do_request($jsonRequest);

        $this->assertSame(201, $jsonResponse->get_status(), 'JSON body params should reach the handler.');
        $this->assertSame(['id' => 7, 'title' => 'Hello'], $jsonResponse->get_data());

        // Same route, form-encoded body — also resolves via has_param/get_param.
        $formRequest = new \WP_REST_Request('POST', '/integration/v1/articles/create');
        $formRequest->set_body_params(['id' => '12', 'title' => 'Form']);
        $formResponse = rest_do_request($formRequest);

        $this->assertSame(201, $formResponse->get_status());
        $this->assertSame(['id' => 12, 'title' => 'Form'], $formResponse->get_data());
    }

    public function testProblemJsonFormatterIsUsedWhenConfigured(): void
    {
        $options = new RouteLoaderOptions(errorFormatter: new ProblemJsonFormatter());
        (new RouteLoader(self::FIXTURES_DIR . '/ProblemJsonRoute', $options))->register();
        do_action('rest_api_init');

        $response = rest_do_request(new \WP_REST_Request('GET', '/integration/v1/problem'));

        $this->assertSame(404, $response->get_status());
        $this->assertSame(
            ProblemJsonFormatter::CONTENT_TYPE,
            $response->get_headers()['Content-Type'] ?? null,
            'application/problem+json header MUST be set when ProblemJsonFormatter is used.'
        );

        $body = $response->get_data();
        $this->assertSame('about:blank', $body['type']);
        $this->assertSame('not-found', $body['title']);
        $this->assertSame(404, $body['status']);
        $this->assertSame('Problem not found.', $body['detail']);
        $this->assertArrayNotHasKey('error', $body);
    }
}

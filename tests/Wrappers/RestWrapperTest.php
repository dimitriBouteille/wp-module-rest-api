<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Wrappers;

use Dbout\WpRestApi\RouteAction;
use Dbout\WpRestApi\Tests\fixtures\RouteWithException;
use Dbout\WpRestApi\Tests\fixtures\RouteWithNotFoundException;
use Dbout\WpRestApi\Tests\fixtures\RouteWithRouteException;
use Dbout\WpRestApi\Wrappers\RestWrapper;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Dbout\WpRestApi\Wrappers\RestWrapper
 */
class RestWrapperTest extends TestCase
{
    /**
     * @param string $className
     * @param bool $debug
     * @param string $expectedMessage
     * @param int $expectedHttpCode
     * @return void
     * @dataProvider providerActionThrowException
     * @covers ::execute
     * @covers ::onError
     */
    public function testActionThrowException(
        string $className,
        bool $debug,
        string $expectedMessage,
        int $expectedHttpCode
    ): void {
        $action = new RouteAction($className, 'execute', ['GET'], null);
        $wrapper = new RestWrapper($action, $debug);

        $response = $wrapper->execute(new \WP_REST_Request());
        $error = $response->get_data()['error'] ?? null;
        $this->exceptionAsserts($response, $expectedMessage, $expectedHttpCode);
        if ($debug === true) {
            $data = $error['data'] ?? [];
            $this->assertArrayHasKey('exception', $data, 'Key error.data.exception not found.');
        }
    }

    /**
     * @return \Generator
     */
    public static function providerActionThrowException(): \Generator
    {
        yield 'With \Exception and debug mode' => [
            RouteWithException::class,
            true,
            'My custom exception.',
            500,
        ];

        yield 'With \Exception and without debug mode' => [
            RouteWithException::class,
            false,
            'Something went wrong. Please try again.',
            500,
        ];

        yield 'With RouteException and debug mode' => [
            RouteWithRouteException::class,
            true,
            'My route exception.',
            400,
        ];

        yield 'With RouteException and without debug mode' => [
            RouteWithRouteException::class,
            false,
            'My route exception.',
            400,
        ];
    }

    /**
     * @return void
     * @covers ::execute
     */
    public function testNotFoundException(): void
    {
        $action = new RouteAction(
            RouteWithNotFoundException::class,
            'execute',
            ['GET'],
            null
        );

        $wrapper = new RestWrapper($action);

        $response = $wrapper->execute(new \WP_REST_Request());
        $this->exceptionAsserts($response, 'Object not found.', 404);
    }

    /**
     * @param \WP_REST_Response $response
     * @param string $expectedMessage
     * @param int $expectedHttpCode
     * @return void
     */
    protected function exceptionAsserts(
        \WP_REST_Response $response,
        string $expectedMessage,
        int $expectedHttpCode
    ): void {
        $error = $response->get_data()['error'] ?? null;
        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals($expectedHttpCode, $response->get_status());
        $this->assertEquals($expectedMessage, $error['message'] ?? null);
    }
}

<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\Wrappers;

use Dbout\WpRestApi\ErrorFormat\ProblemJsonFormatter;
use Dbout\WpRestApi\RouteAction;
use Dbout\WpRestApi\Tests\Unit\fixtures\RouteReturningResponse;
use Dbout\WpRestApi\Tests\Unit\fixtures\RouteReturningWpError;
use Dbout\WpRestApi\Tests\Unit\fixtures\RouteWithException;
use Dbout\WpRestApi\Tests\Unit\fixtures\RouteWithFatalError;
use Dbout\WpRestApi\Tests\Unit\fixtures\RouteWithNotFoundException;
use Dbout\WpRestApi\Tests\Unit\fixtures\RouteWithRequestParam;
use Dbout\WpRestApi\Tests\Unit\fixtures\RouteWithRouteException;
use Dbout\WpRestApi\Tests\Unit\fixtures\RouteWithTypedParams;
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

        yield 'With \TypeError and debug mode' => [
            RouteWithFatalError::class,
            true,
            'My custom type error.',
            500,
        ];

        yield 'With \TypeError and without debug mode' => [
            RouteWithFatalError::class,
            false,
            'Something went wrong. Please try again.',
            500,
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
     * @covers ::execute
     */
    public function testHandlerReturningWpRestResponseIsReturnedAsIs(): void
    {
        $action = new RouteAction(RouteReturningResponse::class, 'execute', ['GET'], null);
        $wrapper = new RestWrapper($action);

        $response = $wrapper->execute(new \WP_REST_Request());

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $this->assertSame(['ok' => true], $response->get_data());
    }

    /**
     * @covers ::execute
     * @covers ::collectDependencies
     */
    public function testWpRestRequestIsInjectedIntoHandler(): void
    {
        $action = new RouteAction(RouteWithRequestParam::class, 'execute', ['POST'], null);
        $wrapper = new RestWrapper($action);

        $request = new \WP_REST_Request('POST', '/dummy');
        $response = $wrapper->execute($request);

        $this->assertSame(200, $response->get_status());
        $this->assertSame(['method' => 'POST', 'received' => true], $response->get_data());
    }

    /**
     * @covers ::execute
     * @covers ::collectDependencies
     * @covers ::castRequestArgument
     */
    public function testTypedRequestParametersAreCast(): void
    {
        $action = new RouteAction(RouteWithTypedParams::class, 'execute', ['GET'], null);
        $wrapper = new RestWrapper($action);

        $request = new \WP_REST_Request('GET', '/dummy');
        $request->set_param('id', '42');     // strings from query strings
        $request->set_param('sort', 'asc');
        $response = $wrapper->execute($request);

        $this->assertSame(200, $response->get_status());
        $this->assertSame([
            'id' => 42,
            'idType' => 'int',
            'sort' => 'asc',
            'sortType' => 'string',
        ], $response->get_data());
    }

    /**
     * @covers ::execute
     * @covers ::parseErrorToRestResponse
     */
    public function testHandlerReturningWpErrorIsConvertedToResponse(): void
    {
        $action = new RouteAction(RouteReturningWpError::class, 'execute', ['GET'], null);
        $wrapper = new RestWrapper($action);

        $response = $wrapper->execute(new \WP_REST_Request());

        $this->assertSame(500, $response->get_status());
        $error = $response->get_data()['error'] ?? null;
        $this->assertSame('forbidden_action', $error['code']);
        $this->assertSame('You may not do this.', $error['message']);
        $this->assertSame(['details' => 'no'], $error['data']);
    }

    /**
     * @covers ::onError
     * @covers ::buildErrorResponse
     */
    public function testProblemJsonContentType(): void
    {
        $action = new RouteAction(RouteWithNotFoundException::class, 'execute', ['GET'], null);
        $wrapper = new RestWrapper(
            $action,
            false,
            new ProblemJsonFormatter(),
        );

        $response = $wrapper->execute(new \WP_REST_Request());

        $this->assertSame(404, $response->get_status());
        $this->assertSame(ProblemJsonFormatter::CONTENT_TYPE, $response->get_headers()['Content-Type'] ?? null);

        $body = $response->get_data();
        $this->assertSame('about:blank', $body['type']);
        $this->assertSame('not-found', $body['title']);
        $this->assertSame(404, $body['status']);
        $this->assertSame('Object not found.', $body['detail']);
        $this->assertArrayNotHasKey('error', $body, 'Problem+json body MUST NOT carry the legacy "error" envelope.');
    }

    /**
     * @covers ::onError
     * @covers ::isWpDebugEnabled
     */
    public function testDebugModeRequiresWpDebug(): void
    {
        $action = new RouteAction(RouteWithRouteException::class, 'execute', ['GET'], null);

        // Subclass overrides isWpDebugEnabled() to simulate WP_DEBUG=false;
        // the trace must NOT be exposed even though $debug is true.
        $wrapper = new class ($action, true) extends RestWrapper {
            protected function isWpDebugEnabled(): bool
            {
                return false;
            }
        };

        $response = $wrapper->execute(new \WP_REST_Request());
        $data = $response->get_data()['error']['data'] ?? [];
        $this->assertArrayNotHasKey(
            'exception',
            $data,
            'Stack trace MUST stay out of the response when WP_DEBUG is false, even with debug=true.'
        );

        // Sanity: with WP_DEBUG=true (bootstrap default) the trace IS attached.
        $wrapperWithWpDebug = new RestWrapper($action, true);
        $dataWithWpDebug = $wrapperWithWpDebug->execute(new \WP_REST_Request())->get_data()['error']['data'] ?? [];
        $this->assertArrayHasKey('exception', $dataWithWpDebug);
    }

    /**
     * @covers ::onError
     */
    public function testUnknownExceptionMessageIsRedactedByDefault(): void
    {
        $action = new RouteAction(RouteWithException::class, 'execute', ['GET'], null);

        // Without debug mode: the original message must not leak.
        $wrapper = new RestWrapper($action);
        $error = $wrapper->execute(new \WP_REST_Request())->get_data()['error'] ?? [];

        $this->assertSame('Something went wrong. Please try again.', $error['message']);
        $this->assertStringNotContainsString(
            'My custom exception.',
            json_encode($error) ?: '',
            'Original exception message must not leak anywhere in the response when debug=false.'
        );

        // Debug mode: the raw message comes through.
        $debugError = (new RestWrapper($action, true))
            ->execute(new \WP_REST_Request())
            ->get_data()['error'] ?? [];
        $this->assertSame('My custom exception.', $debugError['message']);
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

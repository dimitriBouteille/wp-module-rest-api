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
use Dbout\WpRestApi\Tests\Unit\fixtures\IntPriority;
use Dbout\WpRestApi\Tests\Unit\fixtures\RouteReturningResponse;
use Dbout\WpRestApi\Tests\Unit\fixtures\RouteWithInjectionCases;
use Dbout\WpRestApi\Tests\Unit\fixtures\StringStatus;
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
     * @covers ::collectDependencies
     */
    public function testMissingOptionalArgumentDoesNotShiftNextOnes(): void
    {
        $response = $this->executeInjectionCase('optional', ['format' => 'xml']);

        $this->assertSame(200, $response->get_status());
        $this->assertSame(['search' => '', 'format' => 'xml'], $response->get_data());
    }

    /**
     * @covers ::collectDependencies
     */
    public function testMissingRequiredArgumentReturns400(): void
    {
        $response = $this->executeInjectionCase('required', []);

        $this->assertSame(400, $response->get_status());
        $error = $response->get_data()['error'] ?? [];
        $this->assertSame('missing-parameter', $error['code']);
        $this->assertSame(['parameter' => 'id'], $error['data']);
    }

    /**
     * @covers ::collectDependencies
     */
    public function testMissingNullableArgumentReceivesNull(): void
    {
        $response = $this->executeInjectionCase('nullable', []);

        $this->assertSame(200, $response->get_status());
        $this->assertSame(['id' => null], $response->get_data());
    }

    /**
     * @covers ::collectDependencies
     * @covers ::castRequestArgument
     */
    public function testValidEnumValuesAreCast(): void
    {
        $response = $this->executeInjectionCase('enums', ['status' => 'draft', 'priority' => '2']);

        $this->assertSame(200, $response->get_status());
        $this->assertSame(
            ['status' => StringStatus::Draft, 'priority' => IntPriority::High],
            $response->get_data()
        );
    }

    /**
     * @param array<string, mixed> $params
     * @param string $parameter
     * @param array<int|string> $accepted
     * @dataProvider providerInvalidEnumValues
     * @covers ::collectDependencies
     * @covers ::castRequestArgument
     */
    public function testInvalidEnumValueReturns400(array $params, string $parameter, array $accepted): void
    {
        $response = $this->executeInjectionCase('enums', $params);

        $this->assertSame(400, $response->get_status());
        $error = $response->get_data()['error'] ?? [];
        $this->assertSame('invalid-parameter', $error['code']);
        $this->assertSame(['parameter' => $parameter, 'accepted' => $accepted], $error['data']);
    }

    /**
     * @return \Generator
     */
    public static function providerInvalidEnumValues(): \Generator
    {
        yield 'Unknown string value' => [['status' => 'archived'], 'status', ['draft', 'published']];
        yield 'Unknown int value' => [['status' => 'draft', 'priority' => '9'], 'priority', [1, 2]];
        yield 'Non-numeric value for an int enum' => [['status' => 'draft', 'priority' => 'abc'], 'priority', [1, 2]];
        yield 'Array value' => [['status' => ['draft']], 'status', ['draft', 'published']];
    }

    /**
     * @param mixed $value
     * @param bool $expected
     * @dataProvider providerBoolValues
     * @covers ::castRequestArgument
     */
    public function testBoolCastFollowsRestSanitizeBoolean(mixed $value, bool $expected): void
    {
        $response = $this->executeInjectionCase('boolean', ['enabled' => $value]);

        $this->assertSame(200, $response->get_status());
        $this->assertSame(['enabled' => $expected], $response->get_data());
    }

    /**
     * @return \Generator
     */
    public static function providerBoolValues(): \Generator
    {
        yield '"false"' => ['false', false];
        yield '"FALSE"' => ['FALSE', false];
        yield '"0"' => ['0', false];
        yield 'empty string' => ['', false];
        yield '"true"' => ['true', true];
        yield '"1"' => ['1', true];
        yield 'native false' => [false, false];
        yield 'native true' => [true, true];
    }

    /**
     * @covers ::collectDependencies
     */
    public function testRequestIsInjectedBetweenOtherArguments(): void
    {
        $response = $this->executeInjectionCase('requestInTheMiddle', ['id' => '7'], 'POST');

        $this->assertSame(200, $response->get_status());
        $this->assertSame(['id' => 7, 'method' => 'POST', 'sort' => 'asc'], $response->get_data());
    }

    /**
     * @param string $method
     * @param array<string, mixed> $params
     * @param string $httpMethod
     * @return \WP_REST_Response
     */
    protected function executeInjectionCase(string $method, array $params, string $httpMethod = 'GET'): \WP_REST_Response
    {
        $action = new RouteAction(RouteWithInjectionCases::class, $method, [$httpMethod], null);
        $request = new \WP_REST_Request($httpMethod, '/dummy');
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }

        return (new RestWrapper($action))->execute($request);
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

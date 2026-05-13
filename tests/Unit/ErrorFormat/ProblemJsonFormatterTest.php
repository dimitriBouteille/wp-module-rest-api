<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\ErrorFormat;

use Dbout\WpRestApi\ErrorFormat\ProblemJsonFormatter;
use Dbout\WpRestApi\Exceptions\RouteException;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Dbout\WpRestApi\ErrorFormat\ProblemJsonFormatter
 */
class ProblemJsonFormatterTest extends TestCase
{
    /**
     * @covers ::format
     * @covers ::contentType
     */
    public function testShapeMatchesSpec(): void
    {
        $formatter = new ProblemJsonFormatter();

        $exception = new RouteException(
            message: 'Object not found.',
            errorCode: 'not-found',
            httpStatusCode: 404,
        );

        $body = $formatter->format($exception, false);

        $this->assertSame([
            'type' => 'about:blank',
            'title' => 'not-found',
            'status' => 404,
            'detail' => 'Object not found.',
        ], $body);

        $this->assertSame('application/problem+json', $formatter->contentType());
    }

    /**
     * @covers ::format
     */
    public function testAdditionalDataIsAppendedWhenPresent(): void
    {
        $formatter = new ProblemJsonFormatter();

        $exception = new RouteException(
            message: 'Validation failed.',
            errorCode: 'invalid-payload',
            httpStatusCode: 422,
            additionalData: ['fields' => ['name' => 'required']],
        );

        $body = $formatter->format($exception, false);

        $this->assertSame(['fields' => ['name' => 'required']], $body['data']);
    }

    /**
     * @covers ::format
     */
    public function testEmptyAdditionalDataIsOmitted(): void
    {
        $body = (new ProblemJsonFormatter())->format(
            new RouteException(message: 'Boom', errorCode: 'boom', httpStatusCode: 500),
            false,
        );

        $this->assertArrayNotHasKey('data', $body, 'RFC 7807 body should not carry an empty "data" key.');
    }

    /**
     * @covers ::format
     */
    public function testTitleFallsBackToDefaultWhenErrorCodeIsNull(): void
    {
        $body = (new ProblemJsonFormatter())->format(
            new RouteException(message: 'Boom', errorCode: null, httpStatusCode: 500),
            false,
        );

        $this->assertSame(ProblemJsonFormatter::DEFAULT_TITLE, $body['title']);
    }
}
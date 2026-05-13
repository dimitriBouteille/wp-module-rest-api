<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\ErrorFormat;

use Dbout\WpRestApi\ErrorFormat\DefaultFormatter;
use Dbout\WpRestApi\Exceptions\RouteException;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Dbout\WpRestApi\ErrorFormat\DefaultFormatter
 */
class DefaultFormatterTest extends TestCase
{
    /**
     * @covers ::format
     * @covers ::contentType
     */
    public function testHistoricalShapeIsPreserved(): void
    {
        $formatter = new DefaultFormatter();

        $exception = new RouteException(
            message: 'Object not found.',
            errorCode: 'not-found',
            httpStatusCode: 404,
            additionalData: ['hint' => 'check the id'],
        );

        $this->assertSame([
            'error' => [
                'code' => 'not-found',
                'message' => 'Object not found.',
                'data' => ['hint' => 'check the id'],
            ],
        ], $formatter->format($exception, false));

        $this->assertNull($formatter->contentType(), 'Default formatter must not override Content-Type.');
    }

    /**
     * @covers ::format
     */
    public function testCodeFallsBackWhenErrorCodeIsNull(): void
    {
        $body = (new DefaultFormatter())->format(
            new RouteException(message: 'Boom', errorCode: null, httpStatusCode: 500),
            false,
        );

        $this->assertSame(DefaultFormatter::DEFAULT_ERROR_CODE, $body['error']['code']);
    }
}
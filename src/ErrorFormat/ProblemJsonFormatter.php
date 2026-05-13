<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\ErrorFormat;

use Dbout\WpRestApi\Exceptions\RouteException;

/**
 * RFC 7807 (application/problem+json) error formatter.
 *
 * @see https://www.rfc-editor.org/rfc/rfc7807
 */
final class ProblemJsonFormatter implements ErrorFormatterInterface
{
    public const CONTENT_TYPE = 'application/problem+json';
    public const DEFAULT_TITLE = 'route-exception';

    /**
     * @inheritDoc
     */
    public function format(RouteException $exception, bool $debug): array
    {
        $body = [
            'type' => 'about:blank',
            'title' => $exception->getErrorCode() ?? self::DEFAULT_TITLE,
            'status' => $exception->getHttpStatusCode(),
            'detail' => $exception->getMessage(),
        ];

        $data = $exception->getAdditionalData();
        if ($data !== []) {
            $body['data'] = $data;
        }

        return $body;
    }

    public function contentType(): string
    {
        return self::CONTENT_TYPE;
    }
}

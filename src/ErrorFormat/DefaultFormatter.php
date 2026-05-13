<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\ErrorFormat;

use Dbout\WpRestApi\Exceptions\RouteException;

final class DefaultFormatter implements ErrorFormatterInterface
{
    public const DEFAULT_ERROR_CODE = 'route-exception';

    /**
     * @inheritDoc
     */
    public function format(RouteException $exception, bool $debug): array
    {
        return [
            'error' => [
                'code' => $exception->getErrorCode() ?? self::DEFAULT_ERROR_CODE,
                'message' => $exception->getMessage(),
                'data' => $exception->getAdditionalData(),
            ],
        ];
    }

    public function contentType(): ?string
    {
        return null;
    }
}

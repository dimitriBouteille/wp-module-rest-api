<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\ErrorFormat;

use Dbout\WpRestApi\Exceptions\RouteException;

interface ErrorFormatterInterface
{
    /**
     * Build the response body for the given exception.
     *
     * @param RouteException $exception
     * @param bool $debug
     * @return array<string, mixed>
     */
    public function format(RouteException $exception, bool $debug): array;

    /**
     * Return the Content-Type header to set on the error response,
     * or null to leave the default JSON Content-Type unchanged.
     */
    public function contentType(): ?string;
}

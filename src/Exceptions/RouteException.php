<?php
/**
 * Copyright (c) 2024 Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Exceptions;

class RouteException extends \Exception
{
    /**
     * @param string $message
     * @param string|null $errorCode
     * @param int $httpStatusCode
     * @param array $additionalData
     */
    public function __construct(
        string $message,
        protected ?string $errorCode = null,
        public int $httpStatusCode = 400,
        public array $additionalData = []
    ) {
        parent::__construct(
            $message,
            $httpStatusCode
        );
    }

    /**
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * @return array
     */
    public function getAdditionalData(): array
    {
        return $this->additionalData;
    }

    /**
     * @return int
     */
    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }
}

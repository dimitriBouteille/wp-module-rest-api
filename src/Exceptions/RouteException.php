<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
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
     * @param array<string, mixed> $additionalData
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message,
        protected ?string $errorCode = null,
        public int $httpStatusCode = 400,
        public array $additionalData = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message,
            $httpStatusCode,
            $previous,
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
     * @return array<string, mixed>
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

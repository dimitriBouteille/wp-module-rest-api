<?php
/**
 * Copyright (c) 2024 Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Exceptions;

class NotFoundException extends RouteException
{
    /**
     * @param string|null $objectType
     * @param array<string, mixed> $additionalData
     */
    public function __construct(
        string $objectType = null,
        array $additionalData = []
    ) {
        parent::__construct(
            sprintf('%s not found.', $objectType ?: 'Object'),
            'not-found',
            404,
            $additionalData
        );
    }
}

<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures;

class RouteWithReturnValues
{
    /**
     * @return array<string, bool>
     */
    public function arrayValue(): array
    {
        return ['ok' => true];
    }

    public function jsonSerializable(): \JsonSerializable
    {
        return new class () implements \JsonSerializable {
            /**
             * @return array<string, int>
             */
            public function jsonSerialize(): array
            {
                return ['id' => 18];
            }
        };
    }

    public function nullValue(): mixed
    {
        return null;
    }

    public function closure(): \Closure
    {
        return static fn (): bool => true;
    }

    public function wpErrorNotFound(): \WP_Error
    {
        return new \WP_Error('not-found', 'Document not found.', ['status' => 404, 'id' => 18]);
    }

    public function wpErrorStringStatus(): \WP_Error
    {
        return new \WP_Error('forbidden', 'Forbidden.', ['status' => '403']);
    }

    public function wpErrorSuccessStatus(): \WP_Error
    {
        return new \WP_Error('weird', 'Weird.', ['status' => 200]);
    }

    public function wpErrorInvalidStatus(): \WP_Error
    {
        return new \WP_Error('weird', 'Weird.', ['status' => 'nope']);
    }
}

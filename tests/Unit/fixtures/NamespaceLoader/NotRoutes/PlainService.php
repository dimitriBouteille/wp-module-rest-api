<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\fixtures\NamespaceLoader\NotRoutes;

/**
 * Intentionally has no #[Route] attribute: NamespaceRouteLoader must skip it
 * even though it lives under the scanned namespace.
 */
class PlainService
{
    public function nothingToSeeHere(): void
    {
    }
}
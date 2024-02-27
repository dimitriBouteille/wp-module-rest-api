<?php
/**
 * Copyright (c) 2024 Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Helpers;

class Parser
{
    /**
     * @param string|null $fileContent
     * @return string|null
     */
    public static function findClassName(?string $fileContent): ?string
    {
        if ($fileContent === null || $fileContent === '') {
            return null;
        }

        $tokens = token_get_all($fileContent);
        if (1 === \count($tokens) && \T_INLINE_HTML === $tokens[0][0]) {
            throw new \InvalidArgumentException('The content does not contain PHP code.');
        }

        if (preg_match('#^namespace\s+(.+?);.*class\s+(\w+).+;$#sm', $fileContent, $m)) {
            return $m[1].'\\'.$m[2];
        }

        return null;
    }
}

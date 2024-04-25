<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
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

        $class = null;
        $i = 0;
        $counter = count($tokens);
        for (;$i < $counter;$i++) {
            if ($tokens[$i][0] === T_CLASS) {
                for ($j = $i + 1;$j < $counter;$j++) {
                    if ($tokens[$j] === '{') {
                        $class = $tokens[$i + 2][1];
                    }
                }
            }
        }

        if ($class === null || $class === '') {
            return null;
        }

        $namespace =  null;
        if (preg_match('#(^|\s)namespace(.*?)\s*;#sm', $fileContent, $m)) {
            $namespace = $m[2] ?? null;
            $namespace = $namespace !== null ? trim($namespace) : null;
        }

        return $namespace ? $namespace . "\\" . $class : $class;
    }
}

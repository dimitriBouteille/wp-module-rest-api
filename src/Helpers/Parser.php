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

        $class = self::findFirstClassDeclaration($tokens);

        if ($class === null || $class === '') {
            return null;
        }

        $namespace =  null;
        if (preg_match('#(^|\s)namespace(.*?)\s*;#sm', $fileContent, $m)) {
            // @phpstan-ignore-next-line
            $namespace = $m[2] ?? null;

            // @phpstan-ignore-next-line
            $namespace = $namespace !== null ? trim($namespace) : null;
        }

        return $namespace ? $namespace . "\\" . $class : $class;
    }

    /**
     * Returns the name of the first class declared in the tokens.
     *
     * T_CLASS is also produced by `Foo::class` (for example in
     * `#[Action(permissionCallback: IsPublic::class)]`) and by anonymous
     * classes (`new class () {}`): both are skipped.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     * @return string|null
     */
    private static function findFirstClassDeclaration(array $tokens): ?string
    {
        $ignored = [\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT];
        $previous = null;
        foreach ($tokens as $index => $token) {
            if (is_array($token) && in_array($token[0], $ignored, true)) {
                continue;
            }

            $isDeclaration = is_array($token)
                && $token[0] === \T_CLASS
                && (!is_array($previous) || !in_array($previous[0], [\T_DOUBLE_COLON, \T_NEW], true));
            $previous = $token;
            if (!$isDeclaration) {
                continue;
            }

            for ($next = $index + 1, $count = count($tokens); $next < $count; $next++) {
                $nextToken = $tokens[$next];
                if (is_array($nextToken) && in_array($nextToken[0], $ignored, true)) {
                    continue;
                }

                if (is_array($nextToken) && $nextToken[0] === \T_STRING) {
                    return $nextToken[1];
                }

                break;
            }
        }

        return null;
    }
}

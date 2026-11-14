<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Helpers;

use Dbout\WpRestApi\Exceptions\ApiException;

/**
 * Compiles readable path placeholders to the named groups expected by
 * register_rest_route():
 *
 * - `{id}`       → `(?P<id>[^/]+)`
 * - `{id:\d+}`   → `(?P<id>\d+)`
 *
 * Raw regex is passed through unchanged: a `{` followed by a digit or a comma
 * is a quantifier (`[A-Z]{3}`, `\d{2,4}`), and an escaped `\{` is a literal.
 *
 * @internal
 */
final class PathCompiler
{
    private const DEFAULT_PATTERN = '[^/]+';
    private const NAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /**
     * @param string $path
     * @throws ApiException When a placeholder is invalid: bad name, empty
     *                      pattern, missing closing brace or duplicate name.
     * @return string
     */
    public static function compile(string $path): string
    {
        if (!str_contains($path, '{')) {
            return $path;
        }

        $compiled = '';
        $names = [];
        $length = strlen($path);
        $i = 0;
        while ($i < $length) {
            $char = $path[$i];

            if ($char === '\\' && $i + 1 < $length) {
                $compiled .= $char . $path[$i + 1];
                $i += 2;
                continue;
            }

            if ($char !== '{' || self::isQuantifier($path, $i)) {
                $compiled .= $char;
                $i++;
                continue;
            }

            $end = self::findClosingBrace($path, $i);
            if ($end === null) {
                throw new ApiException(sprintf('Missing closing brace for the placeholder at position %d in path "%s".', $i, $path));
            }

            $placeholder = substr($path, $i + 1, $end - $i - 1);
            [$name, $pattern] = array_pad(explode(':', $placeholder, 2), 2, null);
            self::assertValid($path, $name, $pattern, $names);
            $names[] = $name;

            $compiled .= sprintf('(?P<%s>%s)', $name, $pattern ?? self::DEFAULT_PATTERN);
            $i = $end + 1;
        }

        return $compiled;
    }

    /**
     * @param string $path
     * @param int $position Position of the `{`.
     * @return bool
     */
    private static function isQuantifier(string $path, int $position): bool
    {
        $next = $path[$position + 1] ?? '';
        return $next === ',' || ctype_digit($next);
    }

    /**
     * Finds the `}` closing the placeholder, skipping nested braces of the
     * pattern (`{code:[A-Z]{3}}`) and escaped characters.
     *
     * @param string $path
     * @param int $start Position of the opening `{`.
     * @return int|null
     */
    private static function findClosingBrace(string $path, int $start): ?int
    {
        $depth = 0;
        $length = strlen($path);
        for ($i = $start; $i < $length; $i++) {
            $char = $path[$i];
            if ($char === '\\') {
                $i++;
                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * @param string $path
     * @param string|null $name
     * @param string|null $pattern
     * @param array<string> $names Names already used in the path.
     * @throws ApiException
     * @phpstan-assert string $name
     */
    private static function assertValid(string $path, ?string $name, ?string $pattern, array $names): void
    {
        if ($name === null || preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new ApiException(sprintf(
                'Invalid placeholder name "%s" in path "%s": it must be a valid PHP variable name, as it is injected as an argument.',
                (string) $name,
                $path,
            ));
        }

        if ($pattern === '') {
            throw new ApiException(sprintf('Empty pattern for the placeholder "%s" in path "%s".', $name, $path));
        }

        if (in_array($name, $names, true)) {
            throw new ApiException(sprintf('The placeholder "%s" is used several times in path "%s".', $name, $path));
        }
    }
}

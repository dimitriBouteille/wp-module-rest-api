<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\Helpers;

use Dbout\WpRestApi\Exceptions\ApiException;
use Dbout\WpRestApi\Helpers\PathCompiler;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Dbout\WpRestApi\Helpers\PathCompiler
 */
class PathCompilerTest extends TestCase
{
    /**
     * @param string $path
     * @param string $expected
     * @return void
     * @dataProvider providerCompile
     * @covers ::compile
     * @covers ::isQuantifier
     * @covers ::findClosingBrace
     */
    public function testCompile(string $path, string $expected): void
    {
        $compiled = PathCompiler::compile($path);

        $this->assertSame($expected, $compiled);
        // Same delimiter as WP_REST_Server::match_request_to_handler().
        $this->assertNotFalse(@preg_match('@^' . $compiled . '$@i', ''), 'The compiled path MUST be a valid regex.');
    }

    /**
     * @return \Generator
     */
    public static function providerCompile(): \Generator
    {
        yield 'Without placeholder' => ['/documents', '/documents'];
        yield 'Default pattern' => ['/documents/{id}', '/documents/(?P<id>[^/]+)'];
        yield 'Custom pattern' => ['/documents/{id:\d+}', '/documents/(?P<id>\d+)'];
        yield 'Character class' => ['/tags/{slug:[a-z0-9-]+}', '/tags/(?P<slug>[a-z0-9-]+)'];
        yield 'Nested braces in the pattern' => ['/countries/{code:[A-Z]{3}}', '/countries/(?P<code>[A-Z]{3})'];
        yield 'Several placeholders' => [
            '/users/{userId:\d+}/posts/{post_slug}',
            '/users/(?P<userId>\d+)/posts/(?P<post_slug>[^/]+)',
        ];
        yield 'Raw named group unchanged' => ['/documents/(?P<id>\d+)', '/documents/(?P<id>\d+)'];
        yield 'Raw quantifier unchanged' => ['/years/(?P<year>\d{4})', '/years/(?P<year>\d{4})'];
        yield 'Raw range quantifier unchanged' => ['/codes/(?P<code>[A-Z]{2,3})', '/codes/(?P<code>[A-Z]{2,3})'];
        yield 'Escaped brace unchanged' => ['/raw/\{id\}', '/raw/\{id\}'];
        yield 'Mixed raw and placeholder' => [
            '/years/(?P<year>\d{4})/{slug}',
            '/years/(?P<year>\d{4})/(?P<slug>[^/]+)',
        ];
    }

    /**
     * @param string $path
     * @param string $message
     * @return void
     * @dataProvider providerInvalidPlaceholder
     * @covers ::compile
     * @covers ::assertValid
     */
    public function testInvalidPlaceholderThrows(string $path, string $message): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage($message);

        PathCompiler::compile($path);
    }

    /**
     * @return \Generator
     */
    public static function providerInvalidPlaceholder(): \Generator
    {
        yield 'Hyphen in the name' => ['/documents/{document-id}', 'Invalid placeholder name "document-id"'];
        yield 'Empty name' => ['/documents/{}', 'Invalid placeholder name ""'];
        yield 'Empty name with pattern' => ['/documents/{:\d+}', 'Invalid placeholder name ""'];
        yield 'Empty pattern' => ['/documents/{id:}', 'Empty pattern for the placeholder "id"'];
        yield 'Missing closing brace' => ['/documents/{id', 'Missing closing brace'];
        yield 'Duplicate name' => ['/documents/{id}/versions/{id}', 'The placeholder "id" is used several times'];
    }
}

<?php
/**
 * Copyright (c) 2024 Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Helpers;

use Dbout\WpRestApi\Helpers\Parser;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Dbout\WpRestApi\Helpers\Parser
 */
class ParserTest extends TestCase
{
    /**
     * @param string|null $content
     * @param string $expectedClass
     * @return void
     * @dataProvider providerFindClassName
     * @covers ::findClassName
     */
    public function testFindClassName(?string $content, string $expectedClass): void
    {
        $class = Parser::findClassName($content);
        $this->assertEquals($expectedClass, $class);
    }

    /**
     * @return \Generator
     */
    public static function providerFindClassName(): \Generator
    {
        $load = function ($file) {
            $fileContent = file_get_contents(__DIR__ . '/../fixtures/route-classes/' . $file);
            if ($fileContent === false) {
                return null;
            }

            return $fileContent;
        };

       yield 'Light php file' => [
            $load('source-1.php'),
            'App\Routes\MyRoute',
        ];

        yield 'With phpdoc intro' => [
            $load('source-2.php'),
            'App\Routes\MyRoute',
        ];

        yield 'With phpdoc intro & named attributes' => [
            $load('source-3.php'),
            'App\Routes\MyRoute',
        ];

        yield 'With class string in file content' => [
            $load('source-4.php'),
            'App\Routes\MyRoute',
        ];
    }

    /**
     * @return void
     */
    public function testInvalidContent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The content does not contain PHP code.');
        Parser::findClassName('<p>Hello world</p>');
    }
}

<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Tests\Unit\Wrappers;

use Dbout\WpRestApi\Permissions\AllOf;
use Dbout\WpRestApi\Permissions\AnyOf;
use Dbout\WpRestApi\Permissions\CompositePermission;
use Dbout\WpRestApi\Permissions\HasCapability;
use Dbout\WpRestApi\Permissions\HasRole;
use Dbout\WpRestApi\Permissions\IsLoggedIn;
use Dbout\WpRestApi\Permissions\IsPublic;
use Dbout\WpRestApi\Tests\Unit\fixtures\AlwaysAllowPermission;
use Dbout\WpRestApi\Tests\Unit\fixtures\PermissionCallbacks;
use Dbout\WpRestApi\Wrappers\PermissionCodec;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Dbout\WpRestApi\Wrappers\PermissionCodec
 */
class PermissionCodecTest extends TestCase
{
    /**
     * @param mixed $permission
     * @dataProvider providerPlainValues
     * @covers ::encode
     * @covers ::decode
     */
    public function testPlainValuesAreKept(mixed $permission): void
    {
        $this->assertSame($permission, PermissionCodec::encode($permission));
        $this->assertSame($permission, PermissionCodec::decode($permission));
    }

    /**
     * @return \Generator
     */
    public static function providerPlainValues(): \Generator
    {
        yield 'null' => [null];
        yield 'class name' => [AlwaysAllowPermission::class];
        yield 'function name' => ['is_user_logged_in'];
        yield 'static method array' => [[PermissionCallbacks::class, 'allow']];
    }

    /**
     * @covers ::encode
     * @covers ::decode
     */
    public function testPermissionObjectsSurviveAJsonRoundtrip(): void
    {
        $permission = new AnyOf(
            AlwaysAllowPermission::class,
            new AllOf(new IsLoggedIn(), new HasCapability('edit_post', 12), [PermissionCallbacks::class, 'allow']),
            new HasRole('editor'),
            new IsPublic(),
        );

        $json = json_encode(PermissionCodec::encode($permission), \JSON_THROW_ON_ERROR);
        $decoded = PermissionCodec::decode(json_decode($json, true, 512, \JSON_THROW_ON_ERROR));

        $this->assertEquals($permission, $decoded);
    }

    /**
     * @covers ::encode
     */
    public function testClosureCannotBeEncoded(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PermissionCodec::encode(new AnyOf(static fn (): bool => true));
    }

    /**
     * @covers ::encode
     */
    public function testObjectOfAnotherPermissionCannotBeEncoded(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PermissionCodec::encode(new AlwaysAllowPermission());
    }

    /**
     * @param mixed $payload
     * @dataProvider providerInvalidPayloads
     * @covers ::decode
     */
    public function testInvalidPayloadThrows(mixed $payload): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PermissionCodec::decode($payload);
    }

    /**
     * @return \Generator
     */
    public static function providerInvalidPayloads(): \Generator
    {
        yield 'Class outside the package' => [['@permission' => \ArrayObject::class, 'arguments' => []]];
        yield 'Permission that is not cacheable' => [['@permission' => AlwaysAllowPermission::class, 'arguments' => []]];
        yield 'Unknown class' => [['@permission' => 'App\\DoesNotExist', 'arguments' => []]];
        yield 'Abstract class' => [['@permission' => CompositePermission::class, 'arguments' => [IsPublic::class]]];
        yield 'Missing arguments' => [['@permission' => IsLoggedIn::class]];
        yield 'Arguments not a list' => [['@permission' => HasRole::class, 'arguments' => ['role' => 'editor']]];
        yield 'Nested invalid' => [['@permission' => AnyOf::class, 'arguments' => [['@permission' => 42, 'arguments' => []]]]];
    }
}

<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Character\CharacterId;

final class CharacterIdTest extends TestCase
{
    public function testValidCharacterIdNormalization(): void
    {
        $id = new CharacterId(Region::EU, ' La-Croisade-Ecarlate ', ' Norigosa ');

        self::assertSame(Region::EU, $id->region);
        self::assertSame('la-croisade-ecarlate', $id->realmSlug);
        self::assertSame('norigosa', $id->characterName);
    }

    public function testEmptyRealmSlugThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Realm slug cannot be empty.');

        new CharacterId(Region::EU, '   ', 'Norigosa');
    }

    public function testEmptyCharacterNameThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Character name cannot be empty.');

        new CharacterId(Region::EU, 'la-croisade-ecarlate', '');
    }

    public function testUnicodeCharacterNameNormalization(): void
    {
        $id1 = new CharacterId(Region::EU, 'Kael-Thas', ' Nörigosa ');
        self::assertSame('kael-thas', $id1->realmSlug);
        self::assertSame('nörigosa', $id1->characterName);

        $id2 = new CharacterId(Region::EU, 'Hyjal', ' Élise ');
        self::assertSame('hyjal', $id2->realmSlug);
        self::assertSame('élise', $id2->characterName);
    }

    public function testRealmSlugWithWhitespaceThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Realm slug cannot contain whitespace.');

        new CharacterId(Region::EU, 'la croisade ecarlate', 'Norigosa');
    }
}

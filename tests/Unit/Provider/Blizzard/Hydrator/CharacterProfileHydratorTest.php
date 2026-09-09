<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Provider\Blizzard\Hydrator;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Enum\Faction;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Provider\Blizzard\Hydrator\CharacterProfileHydrator;

final class CharacterProfileHydratorTest extends TestCase
{
    private CharacterProfileHydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new CharacterProfileHydrator();
    }

    public function testHydrateSuccessfulFixture(): void
    {
        $jsonRaw = (string) file_get_contents(__DIR__ . '/../../../../Fixtures/Blizzard/character_profile_200.json');
        /** @var array<string, mixed> $data */
        $data = json_decode($jsonRaw, true);

        $profile = $this->hydrator->hydrate(Region::EU, $data);

        self::assertSame('Norigosa', $profile->name);
        self::assertSame(80, $profile->level);
        self::assertSame(Region::EU, $profile->id->region);
        self::assertSame('la-croisade-ecarlate', $profile->id->realmSlug);
        self::assertSame('norigosa', $profile->id->characterName);
        self::assertSame(1305, $profile->realm->id);
        self::assertSame('La Croisade écarlate', $profile->realm->name);
        self::assertSame(8, $profile->playableClass->id);
        self::assertSame('Mage', $profile->playableClass->name);
        self::assertSame(Faction::HORDE, $profile->faction);
    }

    public function testMissingNameFieldThrowsInvalidResponseException(): void
    {
        $data = ['level' => 80, 'realm' => ['id' => 1, 'slug' => 'r', 'name' => 'R'], 'character_class' => ['id' => 1, 'name' => 'C']];

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Character profile response missing valid "name" field.');

        $this->hydrator->hydrate(Region::EU, $data);
    }

    public function testMissingLevelFieldThrowsInvalidResponseException(): void
    {
        $data = ['name' => 'Norigosa', 'realm' => ['id' => 1, 'slug' => 'r', 'name' => 'R'], 'character_class' => ['id' => 1, 'name' => 'C']];

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Character profile response missing valid "level" integer field.');

        $this->hydrator->hydrate(Region::EU, $data);
    }

    public function testIncompleteRealmPayloadThrowsInvalidResponseException(): void
    {
        $data = ['name' => 'Norigosa', 'level' => 80, 'realm' => ['slug' => 'r'], 'character_class' => ['id' => 1, 'name' => 'C']];

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Character profile "realm" payload is incomplete or invalid.');

        $this->hydrator->hydrate(Region::EU, $data);
    }
}

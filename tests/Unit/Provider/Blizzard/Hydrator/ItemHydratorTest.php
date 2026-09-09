<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Provider\Blizzard\Hydrator;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Enum\ItemQuality;
use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Provider\Blizzard\Hydrator\ItemHydrator;

final class ItemHydratorTest extends TestCase
{
    private ItemHydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new ItemHydrator();
    }

    public function testHydrateCompleteValidPayload(): void
    {
        $data = [
            'id' => 19019,
            'name' => 'Lame-tonnerre, épée bénie du Cherchevent',
            'quality' => [
                'type' => 'LEGENDARY',
                'name' => 'Légendaire',
            ],
            'level' => 29,
            'required_level' => 25,
            'item_class' => [
                'id' => 2,
                'name' => 'Arme',
            ],
            'item_subclass' => [
                'id' => 7,
                'name' => 'Épée',
            ],
            'inventory_type' => [
                'type' => 'WEAPON',
                'name' => 'À une main',
            ],
            'max_count' => 1,
            'is_equippable' => true,
        ];

        $item = $this->hydrator->hydrate($data);

        self::assertSame(19019, $item->id);
        self::assertSame('Lame-tonnerre, épée bénie du Cherchevent', $item->name);
        self::assertSame(ItemQuality::LEGENDARY, $item->quality);
        self::assertSame(29, $item->level);
        self::assertSame(25, $item->requiredLevel);
        self::assertNotNull($item->itemClass);
        self::assertSame(2, $item->itemClass->id);
        self::assertSame('Arme', $item->itemClass->name);
        self::assertNotNull($item->itemSubclass);
        self::assertSame(7, $item->itemSubclass->id);
        self::assertSame('Épée', $item->itemSubclass->name);
        self::assertNotNull($item->inventoryType);
        self::assertSame('WEAPON', $item->inventoryType->type);
        self::assertSame('À une main', $item->inventoryType->name);
        self::assertSame(1, $item->maxCount);
        self::assertTrue($item->isEquippable);
    }

    public function testHydrateMinimalPayloadWithDefaults(): void
    {
        $data = [
            'id' => 1234,
            'name' => 'Linen Cloth',
            'quality' => [
                'type' => 'COMMON',
            ],
        ];

        $item = $this->hydrator->hydrate($data);

        self::assertSame(1234, $item->id);
        self::assertSame('Linen Cloth', $item->name);
        self::assertSame(ItemQuality::COMMON, $item->quality);
        self::assertSame(0, $item->level);
        self::assertSame(0, $item->requiredLevel);
        self::assertNull($item->itemClass);
        self::assertNull($item->itemSubclass);
        self::assertNull($item->inventoryType);
        self::assertSame(1, $item->maxCount);
        self::assertFalse($item->isEquippable);
    }

    public function testHydrateAllSupportedQualities(): void
    {
        $map = [
            'POOR' => ItemQuality::POOR,
            'COMMON' => ItemQuality::COMMON,
            'UNCOMMON' => ItemQuality::UNCOMMON,
            'RARE' => ItemQuality::RARE,
            'EPIC' => ItemQuality::EPIC,
            'LEGENDARY' => ItemQuality::LEGENDARY,
            'ARTIFACT' => ItemQuality::ARTIFACT,
            'HEIRLOOM' => ItemQuality::HEIRLOOM,
            'WOW_TOKEN' => ItemQuality::WOW_TOKEN,
        ];

        foreach ($map as $blizzardType => $expectedEnum) {
            $item = $this->hydrator->hydrate([
                'id' => 100,
                'name' => 'Test Item',
                'quality' => ['type' => $blizzardType],
            ]);
            self::assertSame($expectedEnum, $item->quality);
        }
    }

    public function testHydrateMissingIdThrowsInvalidResponseException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Missing or invalid "id" in item payload.');

        $this->hydrator->hydrate([
            'name' => 'No ID Item',
            'quality' => ['type' => 'COMMON'],
        ]);
    }

    public function testHydrateMissingNameThrowsInvalidResponseException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Missing or invalid "name" in item payload.');

        $this->hydrator->hydrate([
            'id' => 19019,
            'quality' => ['type' => 'COMMON'],
        ]);
    }

    public function testHydrateEmptyNameThrowsInvalidResponseException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Missing or invalid "name" in item payload.');

        $this->hydrator->hydrate([
            'id' => 19019,
            'name' => '   ',
            'quality' => ['type' => 'COMMON'],
        ]);
    }

    public function testHydrateMissingQualityThrowsInvalidResponseException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Missing or invalid "quality" in item payload.');

        $this->hydrator->hydrate([
            'id' => 19019,
            'name' => 'Thunderfury',
        ]);
    }

    public function testHydrateUnknownQualityThrowsInvalidResponseException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Unsupported or unknown Blizzard item quality "UNKNOWN_TIER".');

        $this->hydrator->hydrate([
            'id' => 19019,
            'name' => 'Thunderfury',
            'quality' => ['type' => 'UNKNOWN_TIER'],
        ]);
    }
}

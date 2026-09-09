<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\Item;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Enum\ItemQuality;
use UncannyWoW\Core\Domain\Model\Item\InventoryType;
use UncannyWoW\Core\Domain\Model\Item\Item;
use UncannyWoW\Core\Domain\Model\Item\ItemClass;
use UncannyWoW\Core\Domain\Model\Item\ItemSubclass;

final class ItemTest extends TestCase
{
    public function testItemInstantiationAndProperties(): void
    {
        $itemClass = new ItemClass(2, 'Weapon');
        $itemSubclass = new ItemSubclass(7, 'Sword');
        $inventoryType = new InventoryType('WEAPON', 'One-Hand');

        $item = new Item(
            id: 19019,
            name: 'Thunderfury, Blessed Blade of the Windseeker',
            quality: ItemQuality::LEGENDARY,
            level: 29,
            requiredLevel: 25,
            itemClass: $itemClass,
            itemSubclass: $itemSubclass,
            inventoryType: $inventoryType,
            maxCount: 1,
            isEquippable: true,
        );

        self::assertSame(19019, $item->id);
        self::assertSame('Thunderfury, Blessed Blade of the Windseeker', $item->name);
        self::assertSame(ItemQuality::LEGENDARY, $item->quality);
        self::assertSame(29, $item->level);
        self::assertSame(25, $item->requiredLevel);
        self::assertSame($itemClass, $item->itemClass);
        self::assertNotNull($item->itemClass);
        self::assertSame(2, $item->itemClass->id);
        self::assertSame('Weapon', $item->itemClass->name);
        self::assertSame($itemSubclass, $item->itemSubclass);
        self::assertNotNull($item->itemSubclass);
        self::assertSame(7, $item->itemSubclass->id);
        self::assertSame('Sword', $item->itemSubclass->name);
        self::assertSame($inventoryType, $item->inventoryType);
        self::assertNotNull($item->inventoryType);
        self::assertSame('WEAPON', $item->inventoryType->type);
        self::assertSame('One-Hand', $item->inventoryType->name);
        self::assertSame(1, $item->maxCount);
        self::assertTrue($item->isEquippable);
    }

    public function testItemWithMinimalNullableFields(): void
    {
        $item = new Item(
            id: 1234,
            name: 'Linen Cloth',
            quality: ItemQuality::COMMON,
            level: 5,
            requiredLevel: 0,
        );

        self::assertSame(1234, $item->id);
        self::assertSame('Linen Cloth', $item->name);
        self::assertSame(ItemQuality::COMMON, $item->quality);
        self::assertSame(5, $item->level);
        self::assertSame(0, $item->requiredLevel);
        self::assertNull($item->itemClass);
        self::assertNull($item->itemSubclass);
        self::assertNull($item->inventoryType);
        self::assertSame(1, $item->maxCount);
        self::assertFalse($item->isEquippable);
    }

    public function testItemQualityEnumCases(): void
    {
        self::assertSame('poor', ItemQuality::POOR->value);
        self::assertSame('common', ItemQuality::COMMON->value);
        self::assertSame('uncommon', ItemQuality::UNCOMMON->value);
        self::assertSame('rare', ItemQuality::RARE->value);
        self::assertSame('epic', ItemQuality::EPIC->value);
        self::assertSame('legendary', ItemQuality::LEGENDARY->value);
        self::assertSame('artifact', ItemQuality::ARTIFACT->value);
        self::assertSame('heirloom', ItemQuality::HEIRLOOM->value);
        self::assertSame('wow_token', ItemQuality::WOW_TOKEN->value);
    }
}

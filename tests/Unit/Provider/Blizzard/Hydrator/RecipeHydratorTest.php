<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Provider\Blizzard\Hydrator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Core\Domain\Model\Recipe\RecipeModifiedCraftingSlot;
use UncannyWoW\Provider\Blizzard\Hydrator\RecipeHydrator;

#[CoversClass(RecipeHydrator::class)]
#[CoversClass(RecipeModifiedCraftingSlot::class)]
final class RecipeHydratorTest extends TestCase
{
    private RecipeHydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new RecipeHydrator();
    }

    public function testHydrateCompleteRecipeWithFixedQuantityAndModifiedCraftingSlots(): void
    {
        $payload = [
            'id' => 37000,
            'name' => 'Algari Healing Potion',
            'crafted_item' => [
                'id' => 212241,
                'name' => 'Algari Healing Potion',
            ],
            'crafted_quantity' => [
                'value' => 1,
            ],
            'reagents' => [
                [
                    'reagent' => [
                        'id' => 210796,
                        'name' => 'Mycobloom',
                    ],
                    'quantity' => 3,
                ],
                [
                    'reagent' => [
                        'id' => 210798,
                        'name' => 'Luredrop',
                    ],
                    'quantity' => 1,
                ],
            ],
            'modified_crafting_slots' => [
                [
                    'slot_type' => [
                        'id' => 405,
                        'name' => 'Sunglass Vial',
                    ],
                    'display_order' => 0,
                ],
                [
                    'slot_type' => [
                        'id' => 432,
                        'name' => 'Peacebloom',
                    ],
                    'display_order' => 1,
                ],
            ],
        ];

        $recipe = $this->hydrator->hydrate($payload);

        $this->assertSame(37000, $recipe->id);
        $this->assertSame('Algari Healing Potion', $recipe->name);
        $this->assertSame(212241, $recipe->craftedItemId);
        $this->assertSame('Algari Healing Potion', $recipe->craftedItemName);
        $this->assertTrue($recipe->hasCraftedItemReference());
        $this->assertTrue($recipe->craftedQuantity->isKnown());
        $this->assertTrue($recipe->craftedQuantity->isFixed());
        $this->assertSame(1, $recipe->craftedQuantity->minimum);
        $this->assertSame(1, $recipe->craftedQuantity->maximum);
        $this->assertCount(2, $recipe->reagents);
        $this->assertSame(210796, $recipe->reagents[0]->itemId);
        $this->assertSame('Mycobloom', $recipe->reagents[0]->name);
        $this->assertSame(3, $recipe->reagents[0]->quantity);

        $this->assertCount(2, $recipe->modifiedCraftingSlots);
        $this->assertSame(405, $recipe->modifiedCraftingSlots[0]->slotTypeId);
        $this->assertSame('Sunglass Vial', $recipe->modifiedCraftingSlots[0]->name);
        $this->assertSame(0, $recipe->modifiedCraftingSlots[0]->displayOrder);
        $this->assertSame(432, $recipe->modifiedCraftingSlots[1]->slotTypeId);
        $this->assertSame('Peacebloom', $recipe->modifiedCraftingSlots[1]->name);
        $this->assertSame(1, $recipe->modifiedCraftingSlots[1]->displayOrder);
    }

    public function testHydrateRecipeWithoutModifiedCraftingSlotsDefaultsToEmpty(): void
    {
        $payload = [
            'id' => 37001,
            'name' => 'Bulk Smelt',
            'crafted_item' => [
                'id' => 212000,
                'name' => 'Refined Ingot',
            ],
            'crafted_quantity' => [
                'minimum' => 2,
                'maximum' => 5,
            ],
            'reagents' => [],
        ];

        $recipe = $this->hydrator->hydrate($payload);

        $this->assertSame(37001, $recipe->id);
        $this->assertTrue($recipe->craftedQuantity->isKnown());
        $this->assertTrue($recipe->craftedQuantity->isRange());
        $this->assertSame(2, $recipe->craftedQuantity->minimum);
        $this->assertSame(5, $recipe->craftedQuantity->maximum);
        $this->assertSame([], $recipe->modifiedCraftingSlots);
    }

    public function testHydrateRecipeWithOmittedCraftedQuantityIsUnknown(): void
    {
        $payload = [
            'id' => 37002,
            'name' => 'Enchant Weapon',
            'crafted_item' => [
                'id' => 212005,
                'name' => 'Enchanted Scroll',
            ],
            'reagents' => [],
        ];

        $recipe = $this->hydrator->hydrate($payload);

        $this->assertFalse($recipe->craftedQuantity->isKnown());
        $this->assertNull($recipe->craftedQuantity->minimum);
        $this->assertNull($recipe->craftedQuantity->maximum);
    }

    public function testHydrateRecipeWithNullCraftedQuantityIsUnknown(): void
    {
        $payload = [
            'id' => 37003,
            'name' => 'Transmute Ore',
            'crafted_quantity' => null,
            'reagents' => [],
        ];

        $recipe = $this->hydrator->hydrate($payload);

        $this->assertFalse($recipe->craftedQuantity->isKnown());
        $this->assertNull($recipe->craftedItemId);
        $this->assertFalse($recipe->hasCraftedItemReference());
    }

    public function testHydrateRecipeWithMalformedModifiedCraftingSlotsThrows(): void
    {
        $this->expectException(InvalidResponseException::class);

        $payload = [
            'id' => 37006,
            'name' => 'Broken Recipe',
            'modified_crafting_slots' => 'string_not_array',
        ];

        $this->hydrator->hydrate($payload);
    }

    public function testHydrateRecipeWithMalformedSlotEntryThrows(): void
    {
        $this->expectException(InvalidResponseException::class);

        $payload = [
            'id' => 37007,
            'name' => 'Broken Recipe',
            'modified_crafting_slots' => ['not_an_array'],
        ];

        $this->hydrator->hydrate($payload);
    }

    public function testHydrateRecipeWithMalformedSlotTypeIdThrows(): void
    {
        $this->expectException(InvalidResponseException::class);

        $payload = [
            'id' => 37008,
            'name' => 'Broken Recipe',
            'modified_crafting_slots' => [
                [
                    'slot_type' => [
                        'id' => 'invalid_string_id',
                    ],
                    'display_order' => 0,
                ],
            ],
        ];

        $this->hydrator->hydrate($payload);
    }

    public function testHydrateRecipeWithMalformedDisplayOrderThrows(): void
    {
        $this->expectException(InvalidResponseException::class);

        $payload = [
            'id' => 37009,
            'name' => 'Broken Recipe',
            'modified_crafting_slots' => [
                [
                    'slot_type' => [
                        'id' => 386,
                    ],
                    'display_order' => -1,
                ],
            ],
        ];

        $this->hydrator->hydrate($payload);
    }

    public function testHydrateRecipeWithMalformedCraftedQuantityThrows(): void
    {
        $this->expectException(InvalidResponseException::class);

        $payload = [
            'id' => 37004,
            'name' => 'Broken Recipe',
            'crafted_quantity' => [
                'invalid_key' => 123,
            ],
            'reagents' => [],
        ];

        $this->hydrator->hydrate($payload);
    }

    public function testHydrateRecipeWithNonArrayCraftedQuantityThrows(): void
    {
        $this->expectException(InvalidResponseException::class);

        $payload = [
            'id' => 37005,
            'name' => 'Broken Recipe',
            'crafted_quantity' => 'string_value',
            'reagents' => [],
        ];

        $this->hydrator->hydrate($payload);
    }

    public function testHydrateRecipeWithoutIdThrows(): void
    {
        $this->expectException(InvalidResponseException::class);

        $this->hydrator->hydrate(['name' => 'No ID']);
    }

    public function testHydrateRecipeWithoutNameThrows(): void
    {
        $this->expectException(InvalidResponseException::class);

        $this->hydrator->hydrate(['id' => 123]);
    }
}

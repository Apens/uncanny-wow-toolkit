<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\Recipe;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Model\Recipe\Recipe;
use UncannyWoW\Core\Domain\Model\Recipe\RecipeCraftedQuantity;
use UncannyWoW\Core\Domain\Model\Recipe\RecipeModifiedCraftingSlot;
use UncannyWoW\Core\Domain\Model\Recipe\RecipeReagent;

#[CoversClass(Recipe::class)]
#[CoversClass(RecipeReagent::class)]
#[CoversClass(RecipeModifiedCraftingSlot::class)]
final class RecipeTest extends TestCase
{
    public function testRecipeWithCraftedItemReference(): void
    {
        $reagents = [
            new RecipeReagent(1001, 'Iron Ore', 2),
            new RecipeReagent(1002, 'Coal', 1),
        ];

        $slots = [
            new RecipeModifiedCraftingSlot(386, 'Refulgent Copper Ingot', 0),
            new RecipeModifiedCraftingSlot(390, 'Add Embellishment', 1),
        ];

        $quantity = RecipeCraftedQuantity::fixed(1);

        $recipe = new Recipe(
            id: 200,
            name: 'Steel Bar',
            craftedItemId: 1003,
            craftedItemName: 'Steel Bar',
            craftedQuantity: $quantity,
            reagents: $reagents,
            modifiedCraftingSlots: $slots,
        );

        $this->assertSame(200, $recipe->id);
        $this->assertSame('Steel Bar', $recipe->name);
        $this->assertSame(1003, $recipe->craftedItemId);
        $this->assertSame('Steel Bar', $recipe->craftedItemName);
        $this->assertTrue($recipe->hasCraftedItemReference());
        $this->assertSame($quantity, $recipe->craftedQuantity);
        $this->assertCount(2, $recipe->reagents);
        $this->assertSame(1001, $recipe->reagents[0]->itemId);
        $this->assertSame('Iron Ore', $recipe->reagents[0]->name);
        $this->assertSame(2, $recipe->reagents[0]->quantity);

        $this->assertCount(2, $recipe->modifiedCraftingSlots);
        $this->assertSame(386, $recipe->modifiedCraftingSlots[0]->slotTypeId);
        $this->assertSame('Refulgent Copper Ingot', $recipe->modifiedCraftingSlots[0]->name);
        $this->assertSame(0, $recipe->modifiedCraftingSlots[0]->displayOrder);
    }

    public function testRecipeWithoutCraftedItemReference(): void
    {
        $recipe = new Recipe(
            id: 300,
            name: 'Transmute: Iron to Gold',
            craftedItemId: null,
            craftedItemName: null,
            craftedQuantity: RecipeCraftedQuantity::unknown(),
            reagents: [],
            modifiedCraftingSlots: [],
        );

        $this->assertFalse($recipe->hasCraftedItemReference());
        $this->assertNull($recipe->craftedItemId);
        $this->assertNull($recipe->craftedItemName);
        $this->assertEmpty($recipe->reagents);
        $this->assertEmpty($recipe->modifiedCraftingSlots);
    }

    public function testInvalidModifiedCraftingSlotThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RecipeModifiedCraftingSlot(0, 'Slot Name', 0);
    }

    public function testEmptyModifiedCraftingSlotNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RecipeModifiedCraftingSlot(386, '  ', 0);
    }

    public function testNegativeDisplayOrderThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RecipeModifiedCraftingSlot(386, 'Slot', -1);
    }

    public function testInvalidReagentQuantityThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RecipeReagent(1001, 'Iron Ore', 0);
    }

    public function testInvalidReagentItemIdThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RecipeReagent(0, 'Iron Ore', 1);
    }
}

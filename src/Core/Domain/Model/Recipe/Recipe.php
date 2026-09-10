<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Recipe;

final readonly class Recipe
{
    /**
     * @param int $id Blizzard recipe ID.
     * @param string $name Localized recipe name.
     * @param int|null $craftedItemId Blizzard Item ID of the primary output, or null if the provider did not supply crafted_item (e.g. services, enchantments, or dynamic modified-crafting outputs).
     * @param string|null $craftedItemName Localized crafted item name, or null.
     * @param RecipeCraftedQuantity $craftedQuantity Output quantity model (fixed, range, or unknown).
     * @param list<RecipeReagent> $reagents Directly exposed reagents in the Blizzard Recipe payload. Note: This is only the reagent list directly exposed by Blizzard's Recipe payload and may be incomplete for modern modified-crafting recipes.
     * @param list<RecipeModifiedCraftingSlot> $modifiedCraftingSlots List of modern modified crafting reagent slots, if any.
     * @param string|null $description Optional localized description.
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?int $craftedItemId,
        public ?string $craftedItemName,
        public RecipeCraftedQuantity $craftedQuantity,
        public array $reagents = [],
        public array $modifiedCraftingSlots = [],
        public ?string $description = null,
    ) {
        if ($this->id <= 0) {
            throw new \InvalidArgumentException(sprintf('Recipe ID must be positive, got %d.', $this->id));
        }

        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('Recipe name must not be empty.');
        }

        if ($this->craftedItemId !== null && $this->craftedItemId <= 0) {
            throw new \InvalidArgumentException(sprintf('Crafted item ID must be positive when present, got %d.', $this->craftedItemId));
        }
    }

    /**
     * True if the Blizzard Recipe payload explicitly supplied crafted_item with a valid Item ID.
     *
     * Note: Returning false strictly means the provider did not supply crafted_item.
     * It MUST NOT be interpreted as implying that the recipe does not produce an item in-game.
     */
    public function hasCraftedItemReference(): bool
    {
        return $this->craftedItemId !== null;
    }
}

<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Recipe;

final readonly class RecipeModifiedCraftingSlot
{
    /**
     * @param int $slotTypeId Blizzard modified crafting reagent slot type ID.
     * @param string $name Localized slot type name.
     * @param int $displayOrder Display order of the slot in the craft UI.
     */
    public function __construct(
        public int $slotTypeId,
        public string $name,
        public int $displayOrder,
    ) {
        if ($this->slotTypeId <= 0) {
            throw new \InvalidArgumentException(sprintf('Slot type ID must be positive, got %d.', $this->slotTypeId));
        }

        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('Slot type name must not be empty.');
        }

        if ($this->displayOrder < 0) {
            throw new \InvalidArgumentException(sprintf('Display order must be non-negative, got %d.', $this->displayOrder));
        }
    }
}

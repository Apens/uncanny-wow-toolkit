<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Recipe;

final readonly class RecipeReagent
{
    /**
     * @param int $itemId Blizzard Item ID of the required reagent.
     * @param string $name Localized reagent name.
     * @param int $quantity Required quantity (strictly positive integer).
     */
    public function __construct(
        public int $itemId,
        public string $name,
        public int $quantity,
    ) {
        if ($this->itemId <= 0) {
            throw new \InvalidArgumentException(sprintf('Reagent item ID must be positive, got %d.', $this->itemId));
        }

        if ($this->quantity <= 0) {
            throw new \InvalidArgumentException(sprintf('Reagent quantity must be positive, got %d.', $this->quantity));
        }
    }
}

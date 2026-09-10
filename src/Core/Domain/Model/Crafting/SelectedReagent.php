<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Crafting;

final readonly class SelectedReagent
{
    public function __construct(
        public string $key,
        public int $itemId,
        public int $quantity,
        public ReagentCostSource $costSource,
        public ?string $name = null,
    ) {
        if (trim($this->key) === '') {
            throw new \InvalidArgumentException('Reagent key cannot be empty.');
        }

        if ($this->itemId <= 0) {
            throw new \InvalidArgumentException(sprintf('Reagent item ID must be positive, got %d.', $this->itemId));
        }

        if ($this->quantity <= 0) {
            throw new \InvalidArgumentException(sprintf('Reagent quantity must be positive, got %d.', $this->quantity));
        }
    }
}

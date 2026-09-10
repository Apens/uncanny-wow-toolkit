<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Crafting;

final readonly class FixedUnitCost extends ReagentCostSource
{
    public function __construct(
        public int $unitCostCopper,
    ) {
        if ($this->unitCostCopper < 0) {
            throw new \InvalidArgumentException(sprintf('Fixed unit cost must be non-negative, got %d copper.', $this->unitCostCopper));
        }
    }

    public function isCustom(): bool
    {
        return true;
    }
}

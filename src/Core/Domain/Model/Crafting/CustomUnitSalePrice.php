<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Crafting;

final readonly class CustomUnitSalePrice extends OutputSalePriceAssumption
{
    public function __construct(
        public int $unitPriceCopper,
    ) {
        if ($this->unitPriceCopper < 0) {
            throw new \InvalidArgumentException(sprintf('Custom unit sale price must be non-negative, got %d copper.', $this->unitPriceCopper));
        }
    }

    public function isCustom(): bool
    {
        return true;
    }
}

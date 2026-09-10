<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Crafting;

final readonly class CommodityOutputTarget extends CraftOutputTarget
{
    public function __construct(
        public int $itemId,
    ) {
        if ($this->itemId <= 0) {
            throw new \InvalidArgumentException(sprintf('Commodity output target item ID must be positive, got %d.', $this->itemId));
        }
    }

    public function isCommodity(): bool
    {
        return true;
    }
}

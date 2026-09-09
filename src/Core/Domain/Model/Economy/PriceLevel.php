<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Economy;

/**
 * Immutable representation of a distinct price level in the market depth.
 */
final readonly class PriceLevel
{
    public function __construct(
        public int $priceCopper,
        public int $quantity,
        public int $listingCount,
    ) {
        if ($this->priceCopper < 0) {
            throw new \InvalidArgumentException(sprintf('Price copper must be non-negative, got %d.', $this->priceCopper));
        }

        if ($this->quantity <= 0) {
            throw new \InvalidArgumentException(sprintf('Quantity must be positive, got %d.', $this->quantity));
        }

        if ($this->listingCount <= 0) {
            throw new \InvalidArgumentException(sprintf('Listing count must be positive, got %d.', $this->listingCount));
        }
    }
}

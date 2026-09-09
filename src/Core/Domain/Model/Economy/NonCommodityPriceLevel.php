<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Economy;

/**
 * Immutable representation of a distinct buyout price point and lot size for non-commodity items.
 *
 * Preserves exact listing buyout and exact lot size (quantity per listing) without lossy division.
 */
final readonly class NonCommodityPriceLevel
{
    public function __construct(
        public int $buyoutCopper,
        public int $quantityPerListing,
        public int $listingCount,
        public int $totalQuantity,
    ) {
        if ($this->buyoutCopper < 0) {
            throw new \InvalidArgumentException(sprintf('Buyout copper must be non-negative, got %d.', $this->buyoutCopper));
        }

        if ($this->quantityPerListing <= 0) {
            throw new \InvalidArgumentException(sprintf('Quantity per listing must be positive, got %d.', $this->quantityPerListing));
        }

        if ($this->listingCount <= 0) {
            throw new \InvalidArgumentException(sprintf('Listing count must be positive, got %d.', $this->listingCount));
        }

        if ($this->totalQuantity <= 0) {
            throw new \InvalidArgumentException(sprintf('Total quantity must be positive, got %d.', $this->totalQuantity));
        }
    }
}

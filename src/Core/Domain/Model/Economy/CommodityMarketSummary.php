<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Economy;

/**
 * Immutable aggregated market summary for a regional commodity.
 */
final readonly class CommodityMarketSummary
{
    /**
     * @param int $itemId
     * @param int $auctionCount
     * @param int $totalQuantity
     * @param int $lowestUnitPriceCopper
     * @param int $quantityAtLowestPrice
     * @param int $highestUnitPriceCopper
     * @param list<PriceLevel> $priceLevels
     */
    public function __construct(
        public int $itemId,
        public int $auctionCount,
        public int $totalQuantity,
        public int $lowestUnitPriceCopper,
        public int $quantityAtLowestPrice,
        public int $highestUnitPriceCopper,
        public array $priceLevels = [],
    ) {
        if ($this->itemId <= 0) {
            throw new \InvalidArgumentException(sprintf('Item ID must be positive, got %d.', $this->itemId));
        }

        if ($this->auctionCount < 0) {
            throw new \InvalidArgumentException(sprintf('Auction count must be non-negative, got %d.', $this->auctionCount));
        }

        if ($this->totalQuantity < 0) {
            throw new \InvalidArgumentException(sprintf('Total quantity must be non-negative, got %d.', $this->totalQuantity));
        }

        if ($this->lowestUnitPriceCopper < 0) {
            throw new \InvalidArgumentException(sprintf('Lowest unit price copper must be non-negative, got %d.', $this->lowestUnitPriceCopper));
        }

        if ($this->quantityAtLowestPrice < 0) {
            throw new \InvalidArgumentException(sprintf('Quantity at lowest price must be non-negative, got %d.', $this->quantityAtLowestPrice));
        }

        if ($this->highestUnitPriceCopper < 0) {
            throw new \InvalidArgumentException(sprintf('Highest unit price copper must be non-negative, got %d.', $this->highestUnitPriceCopper));
        }
    }

    /**
     * Total quantity represented across the retained price levels.
     */
    public function getQuantityAtPriceLevels(): int
    {
        $sum = 0;
        foreach ($this->priceLevels as $level) {
            $sum += $level->quantity;
        }
        return $sum;
    }
}

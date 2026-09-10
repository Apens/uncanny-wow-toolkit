<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Opportunity;

/**
 * Immutable representation of a structural opportunity candidate for a regional commodity.
 *
 * Captures an observed positive spread between one or more low-priced acquisition price levels
 * and a higher observed target supply level in the current market snapshot.
 *
 * Prospective only: assumes successful resale at target unit price, after deducting the
 * configured Auction House fee. Does not account for deposit losses or market velocity.
 */
final readonly class CommodityOpportunity
{
    /**
     * @param int $itemId Blizzard Item ID.
     * @param int $clearedPriceLevelCount Number of low price levels completely acquired (>= 1).
     * @param int $acquisitionQuantity Total units acquired across cleared price levels.
     * @param int $acquisitionCostCopper Total cost in copper to acquire all units across cleared levels.
     * @param int $targetUnitPriceCopper Observed unit price in copper at target price level.
     * @param int $targetLevelQuantity Total units listed at target price level.
     * @param int $targetLevelListingCount Total listings at target price level.
     * @param int $grossTargetRevenueCopper Gross prospective revenue in copper (acquisitionQuantity * targetUnitPriceCopper).
     * @param int $saleFeeCopper Auction House commission in copper on prospective gross revenue.
     * @param int $netTargetRevenueCopper Net prospective revenue in copper (grossTargetRevenue - saleFee).
     * @param int $prospectiveProfitCopper Net prospective profit in copper (netTargetRevenue - acquisitionCost). Strictly positive.
     * @param int $unitPriceSpreadCopper Exact copper spread (targetUnitPrice - lowestUnitPrice).
     * @param Roi $roi Exact Return on Investment value object.
     */
    public function __construct(
        public int $itemId,
        public int $clearedPriceLevelCount,
        public int $acquisitionQuantity,
        public int $acquisitionCostCopper,
        public int $targetUnitPriceCopper,
        public int $targetLevelQuantity,
        public int $targetLevelListingCount,
        public int $grossTargetRevenueCopper,
        public int $saleFeeCopper,
        public int $netTargetRevenueCopper,
        public int $prospectiveProfitCopper,
        public int $unitPriceSpreadCopper,
        public Roi $roi,
    ) {
        if ($this->itemId <= 0) {
            throw new \InvalidArgumentException(sprintf('Item ID must be positive, got %d.', $this->itemId));
        }

        if ($this->clearedPriceLevelCount < 1) {
            throw new \InvalidArgumentException(sprintf('Cleared price level count must be at least 1, got %d.', $this->clearedPriceLevelCount));
        }

        if ($this->acquisitionQuantity <= 0) {
            throw new \InvalidArgumentException(sprintf('Acquisition quantity must be positive, got %d.', $this->acquisitionQuantity));
        }

        if ($this->acquisitionCostCopper <= 0) {
            throw new \InvalidArgumentException(sprintf('Acquisition cost must be positive, got %d.', $this->acquisitionCostCopper));
        }

        if ($this->targetUnitPriceCopper <= 0) {
            throw new \InvalidArgumentException(sprintf('Target unit price must be positive, got %d.', $this->targetUnitPriceCopper));
        }

        if ($this->targetLevelQuantity <= 0) {
            throw new \InvalidArgumentException(sprintf('Target level quantity must be positive, got %d.', $this->targetLevelQuantity));
        }

        if ($this->targetLevelListingCount <= 0) {
            throw new \InvalidArgumentException(sprintf('Target level listing count must be positive, got %d.', $this->targetLevelListingCount));
        }

        if ($this->grossTargetRevenueCopper <= 0) {
            throw new \InvalidArgumentException(sprintf('Gross target revenue must be positive, got %d.', $this->grossTargetRevenueCopper));
        }

        if ($this->saleFeeCopper < 0) {
            throw new \InvalidArgumentException(sprintf('Sale fee must be non-negative, got %d.', $this->saleFeeCopper));
        }

        if ($this->netTargetRevenueCopper <= 0) {
            throw new \InvalidArgumentException(sprintf('Net target revenue must be positive, got %d.', $this->netTargetRevenueCopper));
        }

        if ($this->prospectiveProfitCopper <= 0) {
            throw new \InvalidArgumentException(sprintf('Prospective profit must be strictly positive, got %d.', $this->prospectiveProfitCopper));
        }

        if ($this->unitPriceSpreadCopper <= 0) {
            throw new \InvalidArgumentException(sprintf('Unit price spread must be strictly positive, got %d.', $this->unitPriceSpreadCopper));
        }
    }
}

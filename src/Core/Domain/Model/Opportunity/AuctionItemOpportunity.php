<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Opportunity;

use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItem;
use UncannyWoW\Core\Domain\Model\Economy\MarketItemIdentity;

/**
 * Immutable representation of a structural opportunity candidate for a non-commodity auction item.
 *
 * Evaluated strictly within a single exact MarketItemIdentity variant where all cleared listings
 * and target listings have quantityPerListing === 1.
 *
 * Prospective only: assumes successful resale at target buyout price after deducting the
 * configured Auction House fee. Does not account for deposit losses or market velocity.
 */
final readonly class AuctionItemOpportunity
{
    /**
     * @param AuctionItem $item Underlying auction item.
     * @param MarketItemIdentity $identity Exact semantic identity of the item variant.
     * @param int $clearedPriceLevelCount Number of low price levels completely acquired (>= 1).
     * @param int $acquiredListingCount Total individual listings acquired across cleared price levels.
     * @param int $acquisitionCostCopper Total cost in copper to acquire all listings across cleared levels.
     * @param int $targetBuyoutCopper Observed buyout price in copper at target price level.
     * @param int $targetListingCount Total individual listings at target price level.
     * @param int $grossTargetRevenueCopper Gross prospective revenue in copper (acquiredListingCount * targetBuyoutCopper).
     * @param int $saleFeeCopper Auction House commission in copper on prospective gross revenue.
     * @param int $netTargetRevenueCopper Net prospective revenue in copper (grossTargetRevenue - saleFee).
     * @param int $prospectiveProfitCopper Net prospective profit in copper (netTargetRevenue - acquisitionCost). Strictly positive.
     * @param int $buyoutSpreadCopper Exact copper spread (targetBuyout - lowestBuyout).
     * @param Roi $roi Exact Return on Investment value object.
     */
    public function __construct(
        public AuctionItem $item,
        public MarketItemIdentity $identity,
        public int $clearedPriceLevelCount,
        public int $acquiredListingCount,
        public int $acquisitionCostCopper,
        public int $targetBuyoutCopper,
        public int $targetListingCount,
        public int $grossTargetRevenueCopper,
        public int $saleFeeCopper,
        public int $netTargetRevenueCopper,
        public int $prospectiveProfitCopper,
        public int $buyoutSpreadCopper,
        public Roi $roi,
    ) {
        if ($this->clearedPriceLevelCount < 1) {
            throw new \InvalidArgumentException(sprintf('Cleared price level count must be at least 1, got %d.', $this->clearedPriceLevelCount));
        }

        if ($this->acquiredListingCount <= 0) {
            throw new \InvalidArgumentException(sprintf('Acquired listing count must be positive, got %d.', $this->acquiredListingCount));
        }

        if ($this->acquisitionCostCopper <= 0) {
            throw new \InvalidArgumentException(sprintf('Acquisition cost must be positive, got %d.', $this->acquisitionCostCopper));
        }

        if ($this->targetBuyoutCopper <= 0) {
            throw new \InvalidArgumentException(sprintf('Target buyout must be positive, got %d.', $this->targetBuyoutCopper));
        }

        if ($this->targetListingCount <= 0) {
            throw new \InvalidArgumentException(sprintf('Target listing count must be positive, got %d.', $this->targetListingCount));
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

        if ($this->buyoutSpreadCopper <= 0) {
            throw new \InvalidArgumentException(sprintf('Buyout spread must be strictly positive, got %d.', $this->buyoutSpreadCopper));
        }
    }
}

<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Economy;

use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItem;

/**
 * Immutable aggregated market summary for a connected-realm non-commodity item variant.
 */
final readonly class AuctionItemMarketSummary
{
    /**
     * @param AuctionItem $item
     * @param MarketItemIdentity $identity
     * @param int $listingCount
     * @param int $totalQuantity
     * @param int $buyoutListingCount
     * @param int $bidOnlyListingCount
     * @param int|null $lowestBuyoutCopper
     * @param int $quantityAtLowestBuyout
     * @param int|null $lowestBidCopper
     * @param list<NonCommodityPriceLevel> $priceLevels
     */
    public function __construct(
        public AuctionItem $item,
        public MarketItemIdentity $identity,
        public int $listingCount,
        public int $totalQuantity,
        public int $buyoutListingCount,
        public int $bidOnlyListingCount,
        public ?int $lowestBuyoutCopper = null,
        public int $quantityAtLowestBuyout = 0,
        public ?int $lowestBidCopper = null,
        public array $priceLevels = [],
    ) {
        if ($this->listingCount < 0) {
            throw new \InvalidArgumentException(sprintf('Listing count must be non-negative, got %d.', $this->listingCount));
        }

        if ($this->totalQuantity < 0) {
            throw new \InvalidArgumentException(sprintf('Total quantity must be non-negative, got %d.', $this->totalQuantity));
        }

        if ($this->buyoutListingCount < 0) {
            throw new \InvalidArgumentException(sprintf('Buyout listing count must be non-negative, got %d.', $this->buyoutListingCount));
        }

        if ($this->bidOnlyListingCount < 0) {
            throw new \InvalidArgumentException(sprintf('Bid-only listing count must be non-negative, got %d.', $this->bidOnlyListingCount));
        }

        if ($this->lowestBuyoutCopper !== null && $this->lowestBuyoutCopper < 0) {
            throw new \InvalidArgumentException(sprintf('Lowest buyout copper must be non-negative, got %d.', $this->lowestBuyoutCopper));
        }

        if ($this->quantityAtLowestBuyout < 0) {
            throw new \InvalidArgumentException(sprintf('Quantity at lowest buyout must be non-negative, got %d.', $this->quantityAtLowestBuyout));
        }

        if ($this->lowestBidCopper !== null && $this->lowestBidCopper < 0) {
            throw new \InvalidArgumentException(sprintf('Lowest bid copper must be non-negative, got %d.', $this->lowestBidCopper));
        }
    }

    public function hasBuyout(): bool
    {
        return $this->lowestBuyoutCopper !== null;
    }

    /**
     * Total quantity represented across the retained buyout price levels.
     */
    public function getQuantityAtPriceLevels(): int
    {
        $sum = 0;
        foreach ($this->priceLevels as $level) {
            $sum += $level->totalQuantity;
        }
        return $sum;
    }
}

<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Service;

use UncannyWoW\Core\Aggregator\CommodityMarketAggregator;
use UncannyWoW\Core\Aggregator\ConnectedRealmMarketAggregator;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionHouseSnapshot;
use UncannyWoW\Core\Domain\Model\AuctionHouse\CommodityMarketSnapshot;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketData;
use UncannyWoW\Core\Domain\Model\Economy\ConnectedRealmMarketData;

/**
 * Public service providing aggregated market depth and pricing summaries.
 */
class EconomyService
{
    private CommodityMarketAggregator $commodityAggregator;
    private ConnectedRealmMarketAggregator $connectedRealmAggregator;

    public function __construct(
        private readonly AuctionHouseService $auctionHouseService,
        private readonly ClientConfiguration $config,
        ?CommodityMarketAggregator $commodityAggregator = null,
        ?ConnectedRealmMarketAggregator $connectedRealmAggregator = null,
    ) {
        $this->commodityAggregator = $commodityAggregator ?? new CommodityMarketAggregator();
        $this->connectedRealmAggregator = $connectedRealmAggregator ?? new ConnectedRealmMarketAggregator();
    }

    /**
     * Retrieve and aggregate non-commodity market data for a connected realm in a single streaming pass.
     *
     * @param int $connectedRealmId Positive integer connected realm ID.
     * @param Region|null $region Optional override for target region.
     * @param int $maxPriceLevels Maximum lowest buyout price levels to retain per item variant.
     */
    public function connectedRealm(int $connectedRealmId, ?Region $region = null, int $maxPriceLevels = 5): ConnectedRealmMarketData
    {
        $this->validateMaxPriceLevels($maxPriceLevels);

        if ($connectedRealmId <= 0) {
            throw new \InvalidArgumentException(sprintf('Connected realm ID must be a positive integer, got %d.', $connectedRealmId));
        }

        $snapshot = $this->auctionHouseService->auctions($connectedRealmId, $region);

        return $this->summarizeAuctions($snapshot, $maxPriceLevels);
    }

    /**
     * Retrieve and aggregate regional commodity market data in a single streaming pass.
     *
     * @param Region|null $region Optional override for target region.
     * @param int $maxPriceLevels Maximum lowest price levels to retain per commodity item (1..10).
     */
    public function commodities(?Region $region = null, int $maxPriceLevels = 5): CommodityMarketData
    {
        $this->validateMaxPriceLevels($maxPriceLevels);

        $targetRegion = $region ?? $this->config->region;
        $snapshot = $this->auctionHouseService->commodities($targetRegion);

        return $this->summarizeCommodities($snapshot, $maxPriceLevels);
    }

    /**
     * Aggregate an existing connected-realm auction snapshot in a single pass.
     *
     * Note: This consumes the single-pass snapshot completely.
     */
    public function summarizeAuctions(AuctionHouseSnapshot $snapshot, int $maxPriceLevels = 5): ConnectedRealmMarketData
    {
        $this->validateMaxPriceLevels($maxPriceLevels);

        return $this->connectedRealmAggregator->aggregate(
            auctions: $snapshot,
            connectedRealmId: $snapshot->connectedRealmId,
            maxPriceLevels: $maxPriceLevels,
        );
    }

    /**
     * Aggregate an existing commodity market snapshot in a single pass.
     *
     * Note: This consumes the single-pass snapshot completely.
     */
    public function summarizeCommodities(CommodityMarketSnapshot $snapshot, int $maxPriceLevels = 5): CommodityMarketData
    {
        $this->validateMaxPriceLevels($maxPriceLevels);

        return $this->commodityAggregator->aggregate(
            auctions: $snapshot,
            region: $snapshot->region,
            maxPriceLevels: $maxPriceLevels,
        );
    }

    private function validateMaxPriceLevels(int $maxPriceLevels): void
    {
        if ($maxPriceLevels < 1 || $maxPriceLevels > 10) {
            throw new \InvalidArgumentException(sprintf('Max price levels must be between 1 and 10, got %d.', $maxPriceLevels));
        }
    }
}

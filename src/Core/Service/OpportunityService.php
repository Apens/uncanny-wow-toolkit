<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Service;

use UncannyWoW\Core\Analyzer\CommodityOpportunityAnalyzer;
use UncannyWoW\Core\Analyzer\ConnectedRealmOpportunityAnalyzer;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketData;
use UncannyWoW\Core\Domain\Model\Economy\ConnectedRealmMarketData;
use UncannyWoW\Core\Domain\Model\Opportunity\AuctionHouseFeePolicy;
use UncannyWoW\Core\Domain\Model\Opportunity\AuctionItemOpportunity;
use UncannyWoW\Core\Domain\Model\Opportunity\CommodityOpportunity;
use UncannyWoW\Core\Domain\Model\Opportunity\OpportunityAnalysis;

/**
 * Public service providing lazy, re-iterable structural opportunity analysis.
 *
 * Coordinates EconomyService retrieval and pure domain analyzers.
 */
class OpportunityService
{
    public function __construct(
        private readonly EconomyService $economyService,
        private readonly ClientConfiguration $config,
        private readonly ?CommodityOpportunityAnalyzer $commodityAnalyzer = null,
        private readonly ?ConnectedRealmOpportunityAnalyzer $connectedRealmAnalyzer = null,
    ) {}

    /**
     * Retrieve and analyze commodity market data lazily for a region.
     *
     * @param Region|null $region Target region (defaults to client configured region).
     * @param AuctionHouseFeePolicy|null $feePolicy Optional fee policy override (defaults to 500 bps = 5%).
     * @return OpportunityAnalysis<CommodityOpportunity>
     */
    public function commodities(?Region $region = null, ?AuctionHouseFeePolicy $feePolicy = null): OpportunityAnalysis
    {
        $targetRegion = $region ?? $this->config->region;
        $marketData = $this->economyService->commodities($targetRegion);

        return $this->analyzeCommodities($marketData, $feePolicy);
    }

    /**
     * Retrieve and analyze connected-realm non-commodity market data lazily.
     *
     * @param int $connectedRealmId Target connected realm ID.
     * @param Region|null $region Optional region override.
     * @param AuctionHouseFeePolicy|null $feePolicy Optional fee policy override (defaults to 500 bps = 5%).
     * @return OpportunityAnalysis<AuctionItemOpportunity>
     */
    public function connectedRealm(int $connectedRealmId, ?Region $region = null, ?AuctionHouseFeePolicy $feePolicy = null): OpportunityAnalysis
    {
        $targetRegion = $region ?? $this->config->region;
        $marketData = $this->economyService->connectedRealm($connectedRealmId, $targetRegion);

        return $this->analyzeConnectedRealm($marketData, $feePolicy);
    }

    /**
     * Pure analysis of existing CommodityMarketData without network calls.
     *
     * @return OpportunityAnalysis<CommodityOpportunity>
     */
    public function analyzeCommodities(CommodityMarketData $marketData, ?AuctionHouseFeePolicy $feePolicy = null): OpportunityAnalysis
    {
        $analyzer = ($feePolicy !== null)
            ? new CommodityOpportunityAnalyzer($feePolicy)
            : ($this->commodityAnalyzer ?? new CommodityOpportunityAnalyzer());

        return $analyzer->analyze($marketData);
    }

    /**
     * Pure analysis of existing ConnectedRealmMarketData without network calls.
     *
     * @return OpportunityAnalysis<AuctionItemOpportunity>
     */
    public function analyzeConnectedRealm(ConnectedRealmMarketData $marketData, ?AuctionHouseFeePolicy $feePolicy = null): OpportunityAnalysis
    {
        $analyzer = ($feePolicy !== null)
            ? new ConnectedRealmOpportunityAnalyzer($feePolicy)
            : ($this->connectedRealmAnalyzer ?? new ConnectedRealmOpportunityAnalyzer());

        return $analyzer->analyze($marketData);
    }
}

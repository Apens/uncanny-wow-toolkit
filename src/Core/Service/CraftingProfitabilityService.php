<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Service;

use UncannyWoW\Core\Analyzer\CraftingProfitabilityAnalyzer;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Crafting\CommodityOutputTarget;
use UncannyWoW\Core\Domain\Model\Crafting\CrafterState;
use UncannyWoW\Core\Domain\Model\Crafting\CraftingProfitabilityResult;
use UncannyWoW\Core\Domain\Model\Crafting\CraftPlan;
use UncannyWoW\Core\Domain\Model\Crafting\CurrentLowestAsk;
use UncannyWoW\Core\Domain\Model\Crafting\NonCommodityOutputTarget;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketData;
use UncannyWoW\Core\Domain\Model\Economy\ConnectedRealmMarketData;
use UncannyWoW\Core\Domain\Model\Opportunity\AuctionHouseFeePolicy;

/**
 * Public service for analyzing crafting profitability.
 *
 * Provides:
 * - Pure evaluate() with zero network I/O
 * - Explicitly networked evaluateCurrentMarket() with single-fetch orchestration
 */
class CraftingProfitabilityService
{
    private CraftingProfitabilityAnalyzer $analyzer;

    public function __construct(
        private readonly EconomyService $economyService,
        ?CraftingProfitabilityAnalyzer $analyzer = null,
    ) {
        $this->analyzer = $analyzer ?? new CraftingProfitabilityAnalyzer();
    }

    /**
     * Pure, offline evaluation of a CraftPlan against supplied market snapshots and crafter expectations.
     *
     * ZERO network I/O, ZERO Recipe API access, ZERO caching operations.
     */
    public function evaluate(
        CraftPlan $plan,
        CrafterState $crafterState,
        ?CommodityMarketData $commodityMarket = null,
        ?ConnectedRealmMarketData $connectedRealmMarket = null,
        ?AuctionHouseFeePolicy $feePolicy = null,
    ): CraftingProfitabilityResult {
        return $this->analyzer->evaluate(
            plan: $plan,
            crafterState: $crafterState,
            commodityMarket: $commodityMarket,
            connectedRealmMarket: $connectedRealmMarket,
            feePolicy: $feePolicy,
        );
    }

    /**
     * Orchestrated current market evaluation.
     *
     * Inspects CraftPlan requirements:
     * - Invokes EconomyService::commodities() AT MOST ONCE if commodity market data is required.
     * - Invokes EconomyService::connectedRealm() AT MOST ONCE if connected realm data is required and connectedRealmId is provided.
     * - Never invokes market endpoints that the craft plan does not require.
     */
    public function evaluateCurrentMarket(
        CraftPlan $plan,
        CrafterState $crafterState,
        ?Region $region = null,
        ?int $connectedRealmId = null,
        ?AuctionHouseFeePolicy $feePolicy = null,
    ): CraftingProfitabilityResult {
        $commodityMarket = null;
        $connectedRealmMarket = null;

        $needsCommodities = false;
        foreach ($plan->reagents as $reagent) {
            if (! $reagent->costSource->isCustom()) {
                $needsCommodities = true;
                break;
            }
        }

        if ($plan->output instanceof CommodityOutputTarget && $plan->salePriceAssumption instanceof CurrentLowestAsk) {
            $needsCommodities = true;
        }

        if ($needsCommodities) {
            $commodityMarket = $this->economyService->commodities(
                region: $region,
                maxPriceLevels: 10,
            );
        }

        $needsConnectedRealm = (
            $plan->output instanceof NonCommodityOutputTarget
            && $plan->salePriceAssumption instanceof CurrentLowestAsk
        );

        if ($needsConnectedRealm && $connectedRealmId !== null) {
            $connectedRealmMarket = $this->economyService->connectedRealm(
                connectedRealmId: $connectedRealmId,
                region: $region,
                maxPriceLevels: 10,
            );
        }

        return $this->evaluate(
            plan: $plan,
            crafterState: $crafterState,
            commodityMarket: $commodityMarket,
            connectedRealmMarket: $connectedRealmMarket,
            feePolicy: $feePolicy,
        );
    }
}

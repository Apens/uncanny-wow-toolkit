<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Analyzer;

use Generator;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketData;
use UncannyWoW\Core\Domain\Model\Opportunity\AuctionHouseFeePolicy;
use UncannyWoW\Core\Domain\Model\Opportunity\CommodityOpportunity;
use UncannyWoW\Core\Domain\Model\Opportunity\OpportunityAnalysis;
use UncannyWoW\Core\Domain\Model\Opportunity\Roi;
use UncannyWoW\Core\Domain\Model\Opportunity\SafeIntegerMath;

/**
 * Pure domain analyzer evaluating structural spread opportunities across regional commodities.
 *
 * Operates strictly on already-materialized CommodityMarketData without secondary HTTP calls.
 */
final readonly class CommodityOpportunityAnalyzer
{
    public function __construct(
        private AuctionHouseFeePolicy $feePolicy = new AuctionHouseFeePolicy(),
    ) {}

    /**
     * Analyze commodity market data lazily for structural opportunity candidates.
     *
     * @return OpportunityAnalysis<CommodityOpportunity>
     */
    public function analyze(CommodityMarketData $marketData): OpportunityAnalysis
    {
        return new OpportunityAnalysis(
            generatorFactory: fn(): Generator => $this->generateOpportunities($marketData),
        );
    }

    /**
     * @return Generator<int, CommodityOpportunity>
     */
    private function generateOpportunities(CommodityMarketData $marketData): Generator
    {
        foreach ($marketData as $summary) {
            $levels = $summary->priceLevels;
            $levelCount = count($levels);

            // Requires at least 2 price levels to have an acquisition level and target level
            if ($levelCount < 2) {
                continue;
            }

            $acquisitionQuantity = 0;
            $acquisitionCostCopper = 0;
            $lowestUnitPriceCopper = $levels[0]->priceCopper;

            // Evaluate each boundary K (0 <= K < levelCount - 1)
            for ($k = 0; $k < $levelCount - 1; $k++) {
                $clearedLevel = $levels[$k];
                $targetLevel = $levels[$k + 1];

                // Accumulate acquisition quantity and cost for level K
                $acquisitionQuantity = SafeIntegerMath::checkedAdd($acquisitionQuantity, $clearedLevel->quantity);
                $levelCost = SafeIntegerMath::checkedMultiply($clearedLevel->priceCopper, $clearedLevel->quantity);
                $acquisitionCostCopper = SafeIntegerMath::checkedAdd($acquisitionCostCopper, $levelCost);

                $targetUnitPrice = $targetLevel->priceCopper;

                // If target unit price is not strictly higher than lowest price, no positive spread
                if ($targetUnitPrice <= $lowestUnitPriceCopper) {
                    continue;
                }

                // Compute economics
                $grossTargetRevenue = SafeIntegerMath::checkedMultiply($acquisitionQuantity, $targetUnitPrice);
                $saleFee = $this->feePolicy->calculateFee($grossTargetRevenue);
                $netTargetRevenue = SafeIntegerMath::checkedSubtract($grossTargetRevenue, $saleFee);
                $prospectiveProfit = SafeIntegerMath::checkedSubtract($netTargetRevenue, $acquisitionCostCopper);

                // Only emit when prospective profit is strictly positive
                if ($prospectiveProfit <= 0) {
                    continue;
                }

                $roi = new Roi(
                    profitCopper: $prospectiveProfit,
                    acquisitionCostCopper: $acquisitionCostCopper,
                );

                $spreadCopper = SafeIntegerMath::checkedSubtract($targetUnitPrice, $lowestUnitPriceCopper);

                yield new CommodityOpportunity(
                    itemId: $summary->itemId,
                    clearedPriceLevelCount: $k + 1,
                    acquisitionQuantity: $acquisitionQuantity,
                    acquisitionCostCopper: $acquisitionCostCopper,
                    targetUnitPriceCopper: $targetUnitPrice,
                    targetLevelQuantity: $targetLevel->quantity,
                    targetLevelListingCount: $targetLevel->listingCount,
                    grossTargetRevenueCopper: $grossTargetRevenue,
                    saleFeeCopper: $saleFee,
                    netTargetRevenueCopper: $netTargetRevenue,
                    prospectiveProfitCopper: $prospectiveProfit,
                    unitPriceSpreadCopper: $spreadCopper,
                    roi: $roi,
                );
            }
        }
    }
}

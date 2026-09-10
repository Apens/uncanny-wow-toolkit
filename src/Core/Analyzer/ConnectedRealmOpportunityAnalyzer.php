<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Analyzer;

use Generator;
use UncannyWoW\Core\Domain\Model\Economy\ConnectedRealmMarketData;
use UncannyWoW\Core\Domain\Model\Economy\NonCommodityPriceLevel;
use UncannyWoW\Core\Domain\Model\Opportunity\AuctionHouseFeePolicy;
use UncannyWoW\Core\Domain\Model\Opportunity\AuctionItemOpportunity;
use UncannyWoW\Core\Domain\Model\Opportunity\OpportunityAnalysis;
use UncannyWoW\Core\Domain\Model\Opportunity\Roi;
use UncannyWoW\Core\Domain\Model\Opportunity\SafeIntegerMath;

/**
 * Pure domain analyzer evaluating structural spread opportunities across connected-realm non-commodity auctions.
 *
 * Operates strictly within single MarketItemIdentity variants without cross-variant comparison.
 *
 * Multi-Quantity Barrier Rule:
 * Only listings with quantityPerListing === 1 are analyzable as acquisition sources or target listings.
 * Any multi-quantity listing (quantityPerListing > 1) at or below the target price acts as an absolute
 * structural barrier and invalidates that target price (no skipping or normalizing lot prices).
 */
final readonly class ConnectedRealmOpportunityAnalyzer
{
    public function __construct(
        private AuctionHouseFeePolicy $feePolicy = new AuctionHouseFeePolicy(),
    ) {}

    /**
     * Analyze connected realm market data lazily for structural opportunity candidates.
     *
     * @return OpportunityAnalysis<AuctionItemOpportunity>
     */
    public function analyze(ConnectedRealmMarketData $marketData): OpportunityAnalysis
    {
        return new OpportunityAnalysis(
            generatorFactory: fn(): Generator => $this->generateOpportunities($marketData),
        );
    }

    /**
     * @return Generator<int, AuctionItemOpportunity>
     */
    private function generateOpportunities(ConnectedRealmMarketData $marketData): Generator
    {
        foreach ($marketData as $summary) {
            $levels = $summary->priceLevels;
            if (empty($levels)) {
                continue;
            }

            // Group price levels by buyout price bucket
            // Each bucket maps buyoutCopper => array of NonCommodityPriceLevel
            /** @var array<int, list<NonCommodityPriceLevel>> $buckets */
            $buckets = [];
            foreach ($levels as $level) {
                $buckets[$level->buyoutCopper][] = $level;
            }

            // Sorted unique buyout price points
            ksort($buckets, \SORT_NUMERIC);
            $buyoutPrices = array_keys($buckets);
            $bucketCount = count($buyoutPrices);

            if ($bucketCount < 2) {
                continue;
            }

            // Check if the cheapest price bucket contains any multi-quantity listing.
            // If so, the cheapest available supply cannot be acquired as pure qty=1 lots,
            // and subsequent levels cannot bypass it.
            if ($this->bucketContainsMultiQuantity($buckets[$buyoutPrices[0]])) {
                continue;
            }

            $acquiredListingCount = 0;
            $acquisitionCostCopper = 0;
            $lowestBuyoutCopper = $buyoutPrices[0];
            $clearedPriceLevelCount = 0;

            // Iterate through candidate target price buckets (target index T from 1 to bucketCount - 1)
            for ($t = 1; $t < $bucketCount; $t++) {
                $prevBuyout = $buyoutPrices[$t - 1];
                $prevBucket = $buckets[$prevBuyout];

                // If previous bucket contained multi-quantity, it blocked proceeding further
                if ($this->bucketContainsMultiQuantity($prevBucket)) {
                    break;
                }

                // Accumulate all qty=1 listings in the previous bucket into acquisition
                foreach ($prevBucket as $level) {
                    $acquiredListingCount = SafeIntegerMath::checkedAdd($acquiredListingCount, $level->listingCount);
                    $levelCost = SafeIntegerMath::checkedMultiply($level->buyoutCopper, $level->listingCount);
                    $acquisitionCostCopper = SafeIntegerMath::checkedAdd($acquisitionCostCopper, $levelCost);
                    $clearedPriceLevelCount++;
                }

                $targetBuyout = $buyoutPrices[$t];
                $targetBucket = $buckets[$targetBuyout];

                // Price-Semantic Target Barrier Rule:
                // If the target bucket ITSELF contains unresolved multi-quantity supply,
                // it cannot serve as an apples-to-apples target price.
                if ($this->bucketContainsMultiQuantity($targetBucket)) {
                    // Cannot target this bucket, but check if we can continue scanning higher buckets
                    // Actually, since this bucket has multi-quantity supply, it also blocks higher targets!
                    break;
                }

                // Sum pure qty=1 listings available at the target price
                $targetListingCount = 0;
                foreach ($targetBucket as $level) {
                    $targetListingCount = SafeIntegerMath::checkedAdd($targetListingCount, $level->listingCount);
                }

                if ($targetListingCount <= 0) {
                    continue;
                }

                // Compute economics
                $grossTargetRevenue = SafeIntegerMath::checkedMultiply($acquiredListingCount, $targetBuyout);
                $saleFee = $this->feePolicy->calculateFee($grossTargetRevenue);
                $netTargetRevenue = SafeIntegerMath::checkedSubtract($grossTargetRevenue, $saleFee);
                $prospectiveProfit = SafeIntegerMath::checkedSubtract($netTargetRevenue, $acquisitionCostCopper);

                if ($prospectiveProfit <= 0) {
                    continue;
                }

                $roi = new Roi(
                    profitCopper: $prospectiveProfit,
                    acquisitionCostCopper: $acquisitionCostCopper,
                );

                $spreadCopper = SafeIntegerMath::checkedSubtract($targetBuyout, $lowestBuyoutCopper);

                yield new AuctionItemOpportunity(
                    item: $summary->item,
                    identity: $summary->identity,
                    clearedPriceLevelCount: $clearedPriceLevelCount,
                    acquiredListingCount: $acquiredListingCount,
                    acquisitionCostCopper: $acquisitionCostCopper,
                    targetBuyoutCopper: $targetBuyout,
                    targetListingCount: $targetListingCount,
                    grossTargetRevenueCopper: $grossTargetRevenue,
                    saleFeeCopper: $saleFee,
                    netTargetRevenueCopper: $netTargetRevenue,
                    prospectiveProfitCopper: $prospectiveProfit,
                    buyoutSpreadCopper: $spreadCopper,
                    roi: $roi,
                );
            }
        }
    }

    /**
     * @param list<NonCommodityPriceLevel> $bucketLevels
     */
    private function bucketContainsMultiQuantity(array $bucketLevels): bool
    {
        foreach ($bucketLevels as $level) {
            if ($level->quantityPerListing > 1) {
                return true;
            }
        }

        return false;
    }
}

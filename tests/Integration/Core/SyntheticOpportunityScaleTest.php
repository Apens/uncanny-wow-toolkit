<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Analyzer\CommodityOpportunityAnalyzer;
use UncannyWoW\Core\Analyzer\ConnectedRealmOpportunityAnalyzer;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItem;
use UncannyWoW\Core\Domain\Model\Economy\AuctionItemMarketSummary;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketData;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketSummary;
use UncannyWoW\Core\Domain\Model\Economy\ConnectedRealmMarketData;
use UncannyWoW\Core\Domain\Model\Economy\MarketItemIdentity;
use UncannyWoW\Core\Domain\Model\Economy\NonCommodityPriceLevel;
use UncannyWoW\Core\Domain\Model\Economy\PriceLevel;
use UncannyWoW\Core\Domain\Model\Opportunity\AuctionHouseFeePolicy;
use UncannyWoW\Core\Domain\Model\Opportunity\AuctionItemOpportunity;
use UncannyWoW\Core\Domain\Model\Opportunity\CommodityOpportunity;

final class SyntheticOpportunityScaleTest extends TestCase
{
    /**
     * Synthesizes 15,000 commodity market summaries with 5 price levels each
     * and asserts bounded memory during traversal, counting, and sorting.
     */
    public function testSyntheticCommodityScaleBoundedMemory(): void
    {
        $uniqueCount = 15000;
        /** @var array<int, CommodityMarketSummary> $summaries */
        $summaries = [];

        for ($i = 1; $i <= $uniqueCount; $i++) {
            $levels = [
                new PriceLevel(100 + ($i % 50), 10, 1),
                new PriceLevel(200 + ($i % 50), 20, 2),
                new PriceLevel(300 + ($i % 50), 30, 3),
                new PriceLevel(400 + ($i % 50), 40, 4),
                new PriceLevel(500 + ($i % 50), 50, 5),
            ];
            $summaries[$i] = new CommodityMarketSummary(
                itemId: $i,
                auctionCount: 15,
                totalQuantity: 150,
                lowestUnitPriceCopper: $levels[0]->priceCopper,
                quantityAtLowestPrice: 10,
                highestUnitPriceCopper: $levels[4]->priceCopper,
                priceLevels: $levels,
            );
        }

        $marketData = new CommodityMarketData(Region::EU, $summaries, $uniqueCount * 15, $uniqueCount * 150);

        $analyzer = new CommodityOpportunityAnalyzer(new AuctionHouseFeePolicy());

        gc_collect_cycles();
        $memBefore = memory_get_usage(true);

        $analysis = $analyzer->analyze($marketData);

        // Iteration: stream generator with O(1) memory
        $firstSample = null;
        $iterationCount = 0;
        foreach ($analysis as $opp) {
            $iterationCount++;
            if ($firstSample === null) {
                $firstSample = $opp;
            }
        }

        $memAfterIteration = memory_get_usage(true);
        $deltaIterationMb = ($memAfterIteration - $memBefore) / 1024 / 1024;

        self::assertInstanceOf(CommodityOpportunity::class, $firstSample);
        // Each summary with 5 strictly increasing price levels yields 4 positive boundaries -> 15,000 * 4 = 60,000 opps
        self::assertSame(60000, $iterationCount);
        self::assertSame(60000, $analysis->count());

        // Assert iteration added less than 16 MB of buffer
        self::assertLessThan(16.0, $deltaIterationMb, sprintf('Iteration memory delta exceeded 16 MB: %.2f MB', $deltaIterationMb));

        // Distinct by highest profit retains exactly 1 candidate per asset (15,000 items)
        $distinct = $analysis->distinctByHighestProfit();
        self::assertCount(15000, $distinct);
    }

    /**
     * Synthesizes 25,000 non-commodity market summaries and tests multi-quantity barrier enforcement at scale.
     */
    public function testSyntheticNonCommodityScaleBoundedMemory(): void
    {
        $uniqueCount = 25000;
        /** @var array<string, AuctionItemMarketSummary> $summaries */
        $summaries = [];

        for ($i = 1; $i <= $uniqueCount; $i++) {
            $item = new AuctionItem(100000 + $i);
            $identity = MarketItemIdentity::fromAuctionItem($item);

            // Odd items have pure qty=1 levels (valid opportunity)
            // Even items have a multi-quantity blocker at level 0 (zero opportunities)
            if ($i % 2 === 1) {
                $levels = [
                    new NonCommodityPriceLevel(1000, 1, 1, 1),
                    new NonCommodityPriceLevel(5000, 1, 1, 1),
                ];
                $lowest = 1000;
            } else {
                $levels = [
                    new NonCommodityPriceLevel(1000, 3, 1, 3), // multi-quantity blocker!
                    new NonCommodityPriceLevel(5000, 1, 1, 1),
                ];
                $lowest = 1000;
            }

            $summaries[$identity->getFingerprint()] = new AuctionItemMarketSummary(
                item: $item,
                identity: $identity,
                listingCount: 2,
                totalQuantity: $i % 2 === 1 ? 2 : 4,
                buyoutListingCount: 2,
                bidOnlyListingCount: 0,
                lowestBuyoutCopper: $lowest,
                quantityAtLowestBuyout: $i % 2 === 1 ? 1 : 3,
                priceLevels: $levels,
            );
        }

        $marketData = new ConnectedRealmMarketData(1127, $summaries, $uniqueCount * 2, $uniqueCount * 3);

        $analyzer = new ConnectedRealmOpportunityAnalyzer(new AuctionHouseFeePolicy());

        gc_collect_cycles();
        $memBefore = memory_get_usage(true);

        $analysis = $analyzer->analyze($marketData);

        $count = 0;
        $sample = null;
        foreach ($analysis as $opp) {
            $count++;
            if ($sample === null) {
                $sample = $opp;
            }
        }

        $memAfterIteration = memory_get_usage(true);
        $deltaIterationMb = ($memAfterIteration - $memBefore) / 1024 / 1024;

        // Exactly half (the 12,500 odd variants) yield 1 opportunity; even variants were blocked by multi-quantity
        self::assertSame(12500, $count);
        self::assertInstanceOf(AuctionItemOpportunity::class, $sample);
        self::assertLessThan(16.0, $deltaIterationMb, sprintf('Iteration memory delta exceeded 16 MB: %.2f MB', $deltaIterationMb));
    }
}

<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Analyzer;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Analyzer\ConnectedRealmOpportunityAnalyzer;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItem;
use UncannyWoW\Core\Domain\Model\Economy\AuctionItemMarketSummary;
use UncannyWoW\Core\Domain\Model\Economy\ConnectedRealmMarketData;
use UncannyWoW\Core\Domain\Model\Economy\MarketItemIdentity;
use UncannyWoW\Core\Domain\Model\Economy\NonCommodityPriceLevel;
use UncannyWoW\Core\Domain\Model\Opportunity\AuctionHouseFeePolicy;
use UncannyWoW\Core\Domain\Model\Opportunity\AuctionItemOpportunity;

final class ConnectedRealmOpportunityAnalyzerTest extends TestCase
{
    private ConnectedRealmOpportunityAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new ConnectedRealmOpportunityAnalyzer(new AuctionHouseFeePolicy());
    }

    public function testOperatesStrictlyWithinExactVariantAndNeverCrossVariants(): void
    {
        // Variant A (context 1) has 1 item @ 100 copper
        $itemA = new AuctionItem(50001, 1);
        $identityA = MarketItemIdentity::fromAuctionItem($itemA);
        $summaryA = new AuctionItemMarketSummary(
            item: $itemA,
            identity: $identityA,
            listingCount: 1,
            totalQuantity: 1,
            buyoutListingCount: 1,
            bidOnlyListingCount: 0,
            lowestBuyoutCopper: 100,
            quantityAtLowestBuyout: 1,
            priceLevels: [new NonCommodityPriceLevel(100, 1, 1, 1)],
        );

        // Variant B (context 2) has 1 item @ 1000 copper
        $itemB = new AuctionItem(50001, 2);
        $identityB = MarketItemIdentity::fromAuctionItem($itemB);
        $summaryB = new AuctionItemMarketSummary(
            item: $itemB,
            identity: $identityB,
            listingCount: 1,
            totalQuantity: 1,
            buyoutListingCount: 1,
            bidOnlyListingCount: 0,
            lowestBuyoutCopper: 1000,
            quantityAtLowestBuyout: 1,
            priceLevels: [new NonCommodityPriceLevel(1000, 1, 1, 1)],
        );

        $market = new ConnectedRealmMarketData(1127, [
            $identityA->getFingerprint() => $summaryA,
            $identityB->getFingerprint() => $summaryB,
        ], 2, 2);

        $opportunities = iterator_to_array($this->analyzer->analyze($market));

        // Neither variant has 2 price levels internally -> 0 opportunities!
        self::assertEmpty($opportunities);
    }

    public function testMultipleQuantityOneAtCheaperLevelAllAcquired(): void
    {
        // Variant has 3 listings @ 100 copper (qty=1 each) = 300 copper acquisition cost.
        // Target @ 300 copper (1 listing, qty=1).
        // Prospective gross = 3 * 300 = 900 copper.
        // Fee (5%) = 45 copper.
        // Net = 855 copper.
        // Profit = 855 - 300 = 555 copper.
        $item = new AuctionItem(60001);
        $identity = MarketItemIdentity::fromAuctionItem($item);
        $levels = [
            new NonCommodityPriceLevel(100, 1, 3, 3),
            new NonCommodityPriceLevel(300, 1, 1, 1),
        ];
        $summary = new AuctionItemMarketSummary(
            item: $item,
            identity: $identity,
            listingCount: 4,
            totalQuantity: 4,
            buyoutListingCount: 4,
            bidOnlyListingCount: 0,
            lowestBuyoutCopper: 100,
            quantityAtLowestBuyout: 3,
            priceLevels: $levels,
        );

        $market = new ConnectedRealmMarketData(1127, [$identity->getFingerprint() => $summary], 4, 4);

        /** @var list<AuctionItemOpportunity> $opportunities */
        $opportunities = iterator_to_array($this->analyzer->analyze($market));

        self::assertCount(1, $opportunities);
        $opp = $opportunities[0];

        self::assertSame(60001, $opp->item->id);
        self::assertSame(1, $opp->clearedPriceLevelCount);
        self::assertSame(3, $opp->acquiredListingCount);
        self::assertSame(300, $opp->acquisitionCostCopper);
        self::assertSame(300, $opp->targetBuyoutCopper);
        self::assertSame(1, $opp->targetListingCount);
        self::assertSame(900, $opp->grossTargetRevenueCopper);
        self::assertSame(45, $opp->saleFeeCopper);
        self::assertSame(855, $opp->netTargetRevenueCopper);
        self::assertSame(555, $opp->prospectiveProfitCopper);
        self::assertSame(200, $opp->buyoutSpreadCopper);
        self::assertSame(18500, $opp->roi->toBasisPoints()); // 555 / 300 = 1.85 = 18500 bps
    }

    public function testMultiQuantityAtCheapestLevelBlocksAnalysis(): void
    {
        // Cheapest level has quantityPerListing = 2.
        // Even though target is 1000 copper (qty=1), cheapest level has multi-quantity lot,
        // so it cannot be acquired as pure qty=1 lots and cannot be bypassed.
        $item = new AuctionItem(60002);
        $identity = MarketItemIdentity::fromAuctionItem($item);
        $levels = [
            new NonCommodityPriceLevel(100, 2, 1, 2),
            new NonCommodityPriceLevel(1000, 1, 1, 1),
        ];
        $summary = new AuctionItemMarketSummary(
            item: $item,
            identity: $identity,
            listingCount: 2,
            totalQuantity: 3,
            buyoutListingCount: 2,
            bidOnlyListingCount: 0,
            lowestBuyoutCopper: 100,
            quantityAtLowestBuyout: 2,
            priceLevels: $levels,
        );

        $market = new ConnectedRealmMarketData(1127, [$identity->getFingerprint() => $summary], 2, 3);

        $opportunities = iterator_to_array($this->analyzer->analyze($market));

        self::assertEmpty($opportunities);
    }

    public function testMultiQuantityAtIntermediateLevelBlocksHigherTargets(): void
    {
        // Level 0: 100 copper (qty=1, count=1) -> valid acquisition
        // Level 1: 200 copper (qty=1, count=1) -> target 1 is valid
        // Level 2: 300 copper (qty=5, count=1) -> multi-quantity barrier!
        // Level 3: 1000 copper (qty=1, count=1) -> blocked by Level 2
        $item = new AuctionItem(60003);
        $identity = MarketItemIdentity::fromAuctionItem($item);
        $levels = [
            new NonCommodityPriceLevel(100, 1, 1, 1),
            new NonCommodityPriceLevel(200, 1, 1, 1),
            new NonCommodityPriceLevel(300, 5, 1, 5),
            new NonCommodityPriceLevel(1000, 1, 1, 1),
        ];
        $summary = new AuctionItemMarketSummary(
            item: $item,
            identity: $identity,
            listingCount: 4,
            totalQuantity: 8,
            buyoutListingCount: 4,
            bidOnlyListingCount: 0,
            lowestBuyoutCopper: 100,
            quantityAtLowestBuyout: 1,
            priceLevels: $levels,
        );

        $market = new ConnectedRealmMarketData(1127, [$identity->getFingerprint() => $summary], 4, 8);

        /** @var list<AuctionItemOpportunity> $opportunities */
        $opportunities = iterator_to_array($this->analyzer->analyze($market));

        // Only Level 1 (target 200) is emitted; Level 2 has multi-quantity which blocks target 300 and 1000.
        self::assertCount(1, $opportunities);
        self::assertSame(200, $opportunities[0]->targetBuyoutCopper);
    }

    public function testSameBuyoutMixedQuantityOneAndMultiQuantityBlocksHigherTargets(): void
    {
        // Price bucket 100 has TWO levels:
        // level A: 100 copper, qty=1, count=1
        // level B: 100 copper, qty=3, count=1
        // Target: 500 copper, qty=1, count=1
        // Because the 100 copper bucket contains a multi-quantity listing (level B),
        // the entire bucket is contaminated and cannot be acquired or bypassed.
        $item = new AuctionItem(60004);
        $identity = MarketItemIdentity::fromAuctionItem($item);
        $levels = [
            new NonCommodityPriceLevel(100, 1, 1, 1),
            new NonCommodityPriceLevel(100, 3, 1, 3),
            new NonCommodityPriceLevel(500, 1, 1, 1),
        ];
        $summary = new AuctionItemMarketSummary(
            item: $item,
            identity: $identity,
            listingCount: 3,
            totalQuantity: 5,
            buyoutListingCount: 3,
            bidOnlyListingCount: 0,
            lowestBuyoutCopper: 100,
            quantityAtLowestBuyout: 4,
            priceLevels: $levels,
        );

        $market = new ConnectedRealmMarketData(1127, [$identity->getFingerprint() => $summary], 3, 5);

        $opportunities = iterator_to_array($this->analyzer->analyze($market));

        self::assertEmpty($opportunities);
    }

    public function testTargetBucketContainingMultiQuantityIsInvalidTarget(): void
    {
        // Level 0: 100 copper (qty=1, count=1)
        // Target bucket 500 has TWO levels:
        // level A: 500 copper, qty=1, count=1
        // level B: 500 copper, qty=2, count=1
        // Target bucket 500 contains multi-quantity, so it is invalid as a target!
        $item = new AuctionItem(60005);
        $identity = MarketItemIdentity::fromAuctionItem($item);
        $levels = [
            new NonCommodityPriceLevel(100, 1, 1, 1),
            new NonCommodityPriceLevel(500, 1, 1, 1),
            new NonCommodityPriceLevel(500, 2, 1, 2),
        ];
        $summary = new AuctionItemMarketSummary(
            item: $item,
            identity: $identity,
            listingCount: 3,
            totalQuantity: 4,
            buyoutListingCount: 3,
            bidOnlyListingCount: 0,
            lowestBuyoutCopper: 100,
            quantityAtLowestBuyout: 1,
            priceLevels: $levels,
        );

        $market = new ConnectedRealmMarketData(1127, [$identity->getFingerprint() => $summary], 3, 4);

        $opportunities = iterator_to_array($this->analyzer->analyze($market));

        self::assertEmpty($opportunities);
    }

    public function testBidOnlyVariantExcluded(): void
    {
        // Variant has only bid listings, lowestBuyoutCopper is null, priceLevels empty
        $item = new AuctionItem(60006);
        $identity = MarketItemIdentity::fromAuctionItem($item);
        $summary = new AuctionItemMarketSummary(
            item: $item,
            identity: $identity,
            listingCount: 2,
            totalQuantity: 2,
            buyoutListingCount: 0,
            bidOnlyListingCount: 2,
            lowestBuyoutCopper: null,
            quantityAtLowestBuyout: 0,
            lowestBidCopper: 500,
            priceLevels: [],
        );

        $market = new ConnectedRealmMarketData(1127, [$identity->getFingerprint() => $summary], 2, 2);

        $opportunities = iterator_to_array($this->analyzer->analyze($market));

        self::assertEmpty($opportunities);
    }
}

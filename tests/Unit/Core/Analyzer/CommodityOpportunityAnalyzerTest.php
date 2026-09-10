<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Analyzer;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Analyzer\CommodityOpportunityAnalyzer;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketData;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketSummary;
use UncannyWoW\Core\Domain\Model\Economy\PriceLevel;
use UncannyWoW\Core\Domain\Model\Opportunity\AuctionHouseFeePolicy;
use UncannyWoW\Core\Domain\Model\Opportunity\CommodityOpportunity;

final class CommodityOpportunityAnalyzerTest extends TestCase
{
    private CommodityOpportunityAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new CommodityOpportunityAnalyzer(new AuctionHouseFeePolicy());
    }

    public function testSinglePriceLevelProducesZeroOpportunities(): void
    {
        $levels = [
            new PriceLevel(100, 10, 1),
        ];
        $summary = new CommodityMarketSummary(12345, 1, 10, 100, 10, 100, $levels);
        $market = new CommodityMarketData(Region::EU, [12345 => $summary], 1, 10);

        $opportunities = iterator_to_array($this->analyzer->analyze($market));

        self::assertEmpty($opportunities);
    }

    public function testTwoLevelPositiveSpreadCalculatesCorrectly(): void
    {
        // Buy 10 @ 100 copper = 1,000 copper.
        // Target @ 200 copper:
        // Prospective gross = 10 * 200 = 2,000 copper.
        // Fee (500 bps = 5%) = 2000 * 5% = 100 copper.
        // Net = 1,900 copper.
        // Net profit = 1,900 - 1,000 = 900 copper.
        // Target supply = 5.
        $levels = [
            new PriceLevel(100, 10, 2),
            new PriceLevel(200, 5, 1),
        ];
        $summary = new CommodityMarketSummary(1001, 3, 15, 100, 10, 200, $levels);
        $market = new CommodityMarketData(Region::EU, [1001 => $summary], 3, 15);

        /** @var list<CommodityOpportunity> $opportunities */
        $opportunities = iterator_to_array($this->analyzer->analyze($market));

        self::assertCount(1, $opportunities);
        $opp = $opportunities[0];

        self::assertSame(1001, $opp->itemId);
        self::assertSame(1, $opp->clearedPriceLevelCount);
        self::assertSame(10, $opp->acquisitionQuantity);
        self::assertSame(1000, $opp->acquisitionCostCopper);
        self::assertSame(200, $opp->targetUnitPriceCopper);
        self::assertSame(5, $opp->targetLevelQuantity);
        self::assertSame(1, $opp->targetLevelListingCount);
        self::assertSame(2000, $opp->grossTargetRevenueCopper);
        self::assertSame(100, $opp->saleFeeCopper);
        self::assertSame(1900, $opp->netTargetRevenueCopper);
        self::assertSame(900, $opp->prospectiveProfitCopper);
        self::assertSame(100, $opp->unitPriceSpreadCopper);
        self::assertSame(9000, $opp->roi->toBasisPoints()); // 900 / 1000 = 90% = 9000 bps
    }

    public function testVisualSpreadWipedOutByFeeYieldsNoOpportunity(): void
    {
        // Buy 10 @ 100 copper = 1,000 copper.
        // Target @ 104 copper:
        // Gross = 10 * 104 = 1,040 copper.
        // Fee (5% of 1040 = 52) -> Net = 1040 - 52 = 988 copper.
        // Profit = 988 - 1,000 = -12 copper <= 0.
        $levels = [
            new PriceLevel(100, 10, 1),
            new PriceLevel(104, 10, 1),
        ];
        $summary = new CommodityMarketSummary(1002, 2, 20, 100, 10, 104, $levels);
        $market = new CommodityMarketData(Region::EU, [1002 => $summary], 2, 20);

        $opportunities = iterator_to_array($this->analyzer->analyze($market));

        self::assertEmpty($opportunities);
    }

    public function testBreakEvenSpreadYieldsNoOpportunity(): void
    {
        // Buy 1 @ 95 copper = 95 copper. Target 100 copper.
        // Gross = 100. Fee = ceil(100 * 500 / 10000) = 5.
        // Net = 100 - 5 = 95.
        // Profit = 95 - 95 = 0 copper.
        $levels = [
            new PriceLevel(95, 1, 1),
            new PriceLevel(100, 1, 1),
        ];
        $summary = new CommodityMarketSummary(1003, 2, 2, 95, 1, 100, $levels);
        $market = new CommodityMarketData(Region::EU, [1003 => $summary], 2, 2);

        $opportunities = iterator_to_array($this->analyzer->analyze($market));

        self::assertEmpty($opportunities);
    }

    public function testMultipleBoundariesEmitted(): void
    {
        // Level 0: 10 @ 100 (cum cost: 1000, cum qty: 10)
        // Level 1: 10 @ 200 (cum cost: 1000 + 2000 = 3000, cum qty: 20)
        // Level 2: 5  @ 400
        //
        // Sweep target Level 1 (200):
        // Acq qty 10, cost 1000. Gross = 10 * 200 = 2000. Fee = 100. Net = 1900. Profit = 900.
        // Target supply = 10.
        //
        // Sweep target Level 2 (400):
        // Acq qty 20 (both level 0 and level 1), cost 3000. Gross = 20 * 400 = 8000.
        // Fee = 400. Net = 7600. Profit = 4600.
        // Target supply = 5.
        $levels = [
            new PriceLevel(100, 10, 1),
            new PriceLevel(200, 10, 1),
            new PriceLevel(400, 5, 1),
        ];
        $summary = new CommodityMarketSummary(1004, 3, 25, 100, 10, 400, $levels);
        $market = new CommodityMarketData(Region::EU, [1004 => $summary], 3, 25);

        /** @var list<CommodityOpportunity> $opportunities */
        $opportunities = iterator_to_array($this->analyzer->analyze($market));

        self::assertCount(2, $opportunities);

        // Boundary 1: target 200
        self::assertSame(200, $opportunities[0]->targetUnitPriceCopper);
        self::assertSame(1, $opportunities[0]->clearedPriceLevelCount);
        self::assertSame(10, $opportunities[0]->acquisitionQuantity);
        self::assertSame(1000, $opportunities[0]->acquisitionCostCopper);
        self::assertSame(10, $opportunities[0]->targetLevelQuantity);
        self::assertSame(900, $opportunities[0]->prospectiveProfitCopper);

        // Boundary 2: target 400
        self::assertSame(400, $opportunities[1]->targetUnitPriceCopper);
        self::assertSame(2, $opportunities[1]->clearedPriceLevelCount);
        self::assertSame(20, $opportunities[1]->acquisitionQuantity);
        self::assertSame(3000, $opportunities[1]->acquisitionCostCopper);
        self::assertSame(5, $opportunities[1]->targetLevelQuantity);
        self::assertSame(4600, $opportunities[1]->prospectiveProfitCopper);
    }
}

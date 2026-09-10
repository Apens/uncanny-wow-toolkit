<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Analyzer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Analyzer\ReagentMarketPricer;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Math\ExactFraction;
use UncannyWoW\Core\Domain\Model\Crafting\CommodityMarketCost;
use UncannyWoW\Core\Domain\Model\Crafting\FixedUnitCost;
use UncannyWoW\Core\Domain\Model\Crafting\ReagentCostBreakdown;
use UncannyWoW\Core\Domain\Model\Crafting\ReagentPricingStatus;
use UncannyWoW\Core\Domain\Model\Crafting\SelectedReagent;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketData;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketSummary;
use UncannyWoW\Core\Domain\Model\Economy\PriceLevel;

#[CoversClass(ReagentMarketPricer::class)]
#[CoversClass(ReagentCostBreakdown::class)]
final class ReagentMarketPricerTest extends TestCase
{
    private ReagentMarketPricer $pricer;

    protected function setUp(): void
    {
        $this->pricer = new ReagentMarketPricer();
    }

    public function testFixedUnitCostPositiveAndZero(): void
    {
        // Positive cost
        $reagent = new SelectedReagent('herb', 190315, 10, new FixedUnitCost(500));
        $breakdown = $this->pricer->priceReagent($reagent, ExactFraction::zero(), null);

        $this->assertSame(ReagentPricingStatus::CustomPriced, $breakdown->status);
        $this->assertTrue($breakdown->isPriced());
        $this->assertSame(5000, $breakdown->fullAcquisitionCostCopper);
        $this->assertNull($breakdown->observedPartialMarketCostCopper);
        $this->assertSame(0, $breakdown->levelsConsumed);

        // Zero cost
        $freeReagent = new SelectedReagent('water', 190316, 5, new FixedUnitCost(0));
        $freeBreakdown = $this->pricer->priceReagent($freeReagent, ExactFraction::zero(), null);

        $this->assertSame(ReagentPricingStatus::CustomPriced, $freeBreakdown->status);
        $this->assertSame(0, $freeBreakdown->fullAcquisitionCostCopper);
    }

    public function testCommodityMarketDataRequiredWhenNull(): void
    {
        $reagent = new SelectedReagent('herb', 190315, 10, new CommodityMarketCost());
        $breakdown = $this->pricer->priceReagent($reagent, ExactFraction::zero(), null);

        $this->assertSame(ReagentPricingStatus::MarketDataRequired, $breakdown->status);
        $this->assertFalse($breakdown->isPriced());
        $this->assertNull($breakdown->fullAcquisitionCostCopper);
        $this->assertNull($breakdown->observedPartialMarketCostCopper);
    }

    public function testCommodityMarketUnavailableWhenItemMissing(): void
    {
        $market = new CommodityMarketData(Region::EU, [], 0, 0);
        $reagent = new SelectedReagent('herb', 190315, 10, new CommodityMarketCost());
        $breakdown = $this->pricer->priceReagent($reagent, ExactFraction::zero(), $market);

        $this->assertSame(ReagentPricingStatus::MarketUnavailable, $breakdown->status);
        $this->assertFalse($breakdown->isPriced());
    }

    public function testSinglePriceLevelMarketPricing(): void
    {
        $summary = new CommodityMarketSummary(
            itemId: 190315,
            auctionCount: 1,
            totalQuantity: 100,
            lowestUnitPriceCopper: 250,
            quantityAtLowestPrice: 100,
            highestUnitPriceCopper: 250,
            priceLevels: [
                new PriceLevel(priceCopper: 250, quantity: 100, listingCount: 1),
            ],
        );
        $market = new CommodityMarketData(Region::EU, [190315 => $summary], 1, 100);

        $reagent = new SelectedReagent('herb', 190315, 20, new CommodityMarketCost());
        $breakdown = $this->pricer->priceReagent($reagent, ExactFraction::zero(), $market);

        $this->assertSame(ReagentPricingStatus::Priced, $breakdown->status);
        $this->assertTrue($breakdown->isPriced());
        $this->assertSame(5000, $breakdown->fullAcquisitionCostCopper);
        $this->assertSame(5000, $breakdown->observedPartialMarketCostCopper);
        $this->assertSame(1, $breakdown->levelsConsumed);
        $this->assertSame(20, $breakdown->lastLevelUnitsConsumed);
    }

    public function testMultiplePriceLevelsWithPartialFinalLevel(): void
    {
        // 50 @ 10 copper, 50 @ 12 copper. Required = 100. Cost = 50*10 + 50*12 = 500 + 600 = 1100 copper.
        $summary = new CommodityMarketSummary(
            itemId: 190315,
            auctionCount: 2,
            totalQuantity: 100,
            lowestUnitPriceCopper: 10,
            quantityAtLowestPrice: 50,
            highestUnitPriceCopper: 12,
            priceLevels: [
                new PriceLevel(priceCopper: 10, quantity: 50, listingCount: 1),
                new PriceLevel(priceCopper: 12, quantity: 50, listingCount: 1),
            ],
        );
        $market = new CommodityMarketData(Region::EU, [190315 => $summary], 1, 100);

        $reagent = new SelectedReagent('herb', 190315, 100, new CommodityMarketCost());
        $breakdown = $this->pricer->priceReagent($reagent, ExactFraction::zero(), $market);

        $this->assertSame(ReagentPricingStatus::Priced, $breakdown->status);
        $this->assertSame(1100, $breakdown->fullAcquisitionCostCopper);
        $this->assertSame(2, $breakdown->levelsConsumed);
        $this->assertSame(50, $breakdown->lastLevelUnitsConsumed);

        // Required = 70. 50 @ 10 (500) + 20 @ 12 (240) = 740 copper.
        $reagent70 = new SelectedReagent('herb', 190315, 70, new CommodityMarketCost());
        $breakdown70 = $this->pricer->priceReagent($reagent70, ExactFraction::zero(), $market);

        $this->assertSame(740, $breakdown70->fullAcquisitionCostCopper);
        $this->assertSame(2, $breakdown70->levelsConsumed);
        $this->assertSame(20, $breakdown70->lastLevelUnitsConsumed);
    }

    public function testInsufficientMarketDepthNoExtrapolation(): void
    {
        $summary = new CommodityMarketSummary(
            itemId: 190315,
            auctionCount: 1,
            totalQuantity: 30,
            lowestUnitPriceCopper: 100,
            quantityAtLowestPrice: 30,
            highestUnitPriceCopper: 100,
            priceLevels: [
                new PriceLevel(priceCopper: 100, quantity: 30, listingCount: 1),
            ],
        );
        $market = new CommodityMarketData(Region::EU, [190315 => $summary], 1, 100);

        $reagent = new SelectedReagent('herb', 190315, 50, new CommodityMarketCost());
        $breakdown = $this->pricer->priceReagent($reagent, ExactFraction::zero(), $market);

        $this->assertSame(ReagentPricingStatus::InsufficientMarketDepth, $breakdown->status);
        $this->assertFalse($breakdown->isPriced());
        $this->assertNull($breakdown->fullAcquisitionCostCopper);
        $this->assertSame(30, $breakdown->priceableQuantity);
        $this->assertSame(3000, $breakdown->observedPartialMarketCostCopper);
        $this->assertSame(1, $breakdown->levelsConsumed);
        $this->assertSame(30, $breakdown->lastLevelUnitsConsumed);
    }

    public function testResourcefulnessAverageAcquisitionCostBasis(): void
    {
        // 50 @ 10 copper, 50 @ 12 copper = 100 units for 1100 copper.
        // Average unit cost = 1100 / 100 = 11 copper.
        // Expected saved = 10 units.
        // Expected savings = 10 * 11 = 110 copper.
        // Expected effective cost = 1100 - 110 = 990 copper.
        $summary = new CommodityMarketSummary(
            itemId: 190315,
            auctionCount: 2,
            totalQuantity: 100,
            lowestUnitPriceCopper: 10,
            quantityAtLowestPrice: 50,
            highestUnitPriceCopper: 12,
            priceLevels: [
                new PriceLevel(priceCopper: 10, quantity: 50, listingCount: 1),
                new PriceLevel(priceCopper: 12, quantity: 50, listingCount: 1),
            ],
        );
        $market = new CommodityMarketData(Region::EU, [190315 => $summary], 1, 100);

        $reagent = new SelectedReagent('herb', 190315, 100, new CommodityMarketCost());
        $expectedSaved = ExactFraction::of(10, 1);
        $breakdown = $this->pricer->priceReagent($reagent, $expectedSaved, $market);

        $this->assertTrue($breakdown->isPriced());
        $this->assertNotNull($breakdown->expectedSavingsCopper);
        $this->assertNotNull($breakdown->expectedEffectiveCostCopper);

        $this->assertSame(0, $breakdown->expectedSavingsCopper->compareTo(ExactFraction::of(110, 1)));
        $this->assertSame(0, $breakdown->expectedEffectiveCostCopper->compareTo(ExactFraction::of(990, 1)));
    }
}

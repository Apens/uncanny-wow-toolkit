<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Analyzer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Analyzer\CraftingProfitabilityAnalyzer;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Math\ExactFraction;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItem;
use UncannyWoW\Core\Domain\Model\Crafting\BaseCraftingEconomics;
use UncannyWoW\Core\Domain\Model\Crafting\CommodityMarketCost;
use UncannyWoW\Core\Domain\Model\Crafting\CommodityOutputTarget;
use UncannyWoW\Core\Domain\Model\Crafting\CrafterState;
use UncannyWoW\Core\Domain\Model\Crafting\CraftingProfitabilityResult;
use UncannyWoW\Core\Domain\Model\Crafting\CraftPlan;
use UncannyWoW\Core\Domain\Model\Crafting\CurrentLowestAsk;
use UncannyWoW\Core\Domain\Model\Crafting\CustomUnitSalePrice;
use UncannyWoW\Core\Domain\Model\Crafting\ExpectedCraftingEconomics;
use UncannyWoW\Core\Domain\Model\Crafting\FixedUnitCost;
use UncannyWoW\Core\Domain\Model\Crafting\NonCommodityOutputTarget;
use UncannyWoW\Core\Domain\Model\Crafting\OutputPriceResolution;
use UncannyWoW\Core\Domain\Model\Crafting\OutputPriceStatus;
use UncannyWoW\Core\Domain\Model\Crafting\ReagentCostBreakdown;
use UncannyWoW\Core\Domain\Model\Crafting\ReagentPricingStatus;
use UncannyWoW\Core\Domain\Model\Crafting\SelectedReagent;
use UncannyWoW\Core\Domain\Model\Economy\AuctionItemMarketSummary;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketData;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketSummary;
use UncannyWoW\Core\Domain\Model\Economy\ConnectedRealmMarketData;
use UncannyWoW\Core\Domain\Model\Economy\MarketItemIdentity;
use UncannyWoW\Core\Domain\Model\Economy\NonCommodityPriceLevel;
use UncannyWoW\Core\Domain\Model\Economy\PriceLevel;
use UncannyWoW\Core\Domain\Model\Opportunity\AuctionHouseFeePolicy;

#[CoversClass(CraftingProfitabilityAnalyzer::class)]
#[CoversClass(CraftingProfitabilityResult::class)]
#[CoversClass(CraftPlan::class)]
#[CoversClass(CrafterState::class)]
#[CoversClass(OutputPriceResolution::class)]
#[CoversClass(BaseCraftingEconomics::class)]
#[CoversClass(ExpectedCraftingEconomics::class)]
final class CraftingProfitabilityAnalyzerTest extends TestCase
{
    private CraftingProfitabilityAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new CraftingProfitabilityAnalyzer();
    }

    public function testPureCustomCostCustomSaleCraftWithBothMarketsNullSucceeds(): void
    {
        $plan = new CraftPlan(
            output: new CommodityOutputTarget(190315),
            baseOutputQuantity: 1,
            reagents: [
                new SelectedReagent('herb', 190315, 2, new FixedUnitCost(100)),
            ],
            salePriceAssumption: new CustomUnitSalePrice(300),
            baseConcentrationCost: 20,
            recipeId: null,
            targetQuality: null,
        );

        $result = $this->analyzer->evaluate($plan, CrafterState::none(), null, null);

        $this->assertTrue($result->isFullyPriced());
        $this->assertSame(OutputPriceStatus::CustomPriced, $result->outputResolution->status);
        $this->assertNotNull($result->baseEconomics);
        $this->assertNotNull($result->expectedEconomics);

        // Material cost: 2 * 100 = 200 copper.
        // Gross revenue: 1 * 300 = 300 copper.
        // Fee: ceil(300 * 500 / 10000) = 15 copper.
        // Net revenue: 300 - 15 = 285 copper.
        // Net profit: 285 - 200 = 85 copper.
        $this->assertSame(200, $result->baseEconomics->baseMaterialCostCopper);
        $this->assertSame(300, $result->baseEconomics->baseGrossRevenueCopper);
        $this->assertSame(15, $result->baseEconomics->baseSaleFeeCopper);
        $this->assertSame(285, $result->baseEconomics->baseNetRevenueCopper);
        $this->assertSame(85, $result->baseEconomics->baseNetProfitCopper);

        $this->assertNotNull($result->baseEconomics->baseRoi);
        // ROI = 85 / 200 = 42.5% -> 4250 bps
        $this->assertSame(4250, $result->baseEconomics->baseRoi->toBasisPoints());
    }

    public function testZeroReagentCraftPlanEvaluatesWithZeroMaterialCostAndNullRoi(): void
    {
        $plan = new CraftPlan(
            output: new CommodityOutputTarget(190315),
            baseOutputQuantity: 1,
            reagents: [],
            salePriceAssumption: new CustomUnitSalePrice(500),
            baseConcentrationCost: 0,
        );

        $result = $this->analyzer->evaluate($plan, CrafterState::none(), null, null);

        $this->assertTrue($result->isFullyPriced());
        $this->assertNotNull($result->baseEconomics);
        $this->assertNotNull($result->expectedEconomics);
        $this->assertSame(0, $result->baseEconomics->baseMaterialCostCopper);
        $this->assertSame(500, $result->baseEconomics->baseGrossRevenueCopper);
        $this->assertSame(25, $result->baseEconomics->baseSaleFeeCopper);
        $this->assertSame(475, $result->baseEconomics->baseNetRevenueCopper);
        $this->assertSame(475, $result->baseEconomics->baseNetProfitCopper);
        $this->assertNull($result->baseEconomics->baseRoi);

        $this->assertTrue($result->expectedEconomics->expectedMaterialCostCopper->isZero());
        $this->assertNull($result->expectedEconomics->expectedRoi);
    }

    public function testCommodityCostSourceWithNullMarketYieldsMarketDataRequired(): void
    {
        $plan = new CraftPlan(
            output: new CommodityOutputTarget(190315),
            baseOutputQuantity: 1,
            reagents: [
                new SelectedReagent('herb', 190315, 1, new CommodityMarketCost()),
            ],
            salePriceAssumption: new CustomUnitSalePrice(500),
        );

        $result = $this->analyzer->evaluate($plan, CrafterState::none(), null, null);

        $this->assertFalse($result->isFullyPriced());
        $this->assertNull($result->baseEconomics);
        $this->assertNull($result->expectedEconomics);
        $this->assertSame(ReagentPricingStatus::MarketDataRequired, $result->reagentBreakdowns[0]->status);
    }

    public function testCommoditySnapshotPresentButItemAbsentYieldsMarketUnavailable(): void
    {
        $market = new CommodityMarketData(Region::EU, [], 0, 0);

        $plan = new CraftPlan(
            output: new CommodityOutputTarget(190315),
            baseOutputQuantity: 1,
            reagents: [
                new SelectedReagent('herb', 190315, 1, new CommodityMarketCost()),
            ],
            salePriceAssumption: new CustomUnitSalePrice(500),
        );

        $result = $this->analyzer->evaluate($plan, CrafterState::none(), $market, null);

        $this->assertFalse($result->isFullyPriced());
        $this->assertSame(ReagentPricingStatus::MarketUnavailable, $result->reagentBreakdowns[0]->status);
    }

    public function testNonCommodityCurrentLowestAskWithNullMarketYieldsMarketDataRequired(): void
    {
        $identity = new MarketItemIdentity(210000);
        $plan = new CraftPlan(
            output: new NonCommodityOutputTarget($identity),
            baseOutputQuantity: 1,
            reagents: [
                new SelectedReagent('ore', 190316, 1, new FixedUnitCost(100)),
            ],
            salePriceAssumption: new CurrentLowestAsk(),
        );

        $result = $this->analyzer->evaluate($plan, CrafterState::none(), null, null);

        $this->assertFalse($result->isFullyPriced());
        $this->assertSame(OutputPriceStatus::MarketDataRequired, $result->outputResolution->status);
    }

    public function testNonCommodityOutputLowestBucketSingleItemPriced(): void
    {
        $identity = new MarketItemIdentity(210000);
        $item = new AuctionItem(id: 210000, context: 0);

        $summary = new AuctionItemMarketSummary(
            item: $item,
            identity: $identity,
            listingCount: 2,
            totalQuantity: 2,
            buyoutListingCount: 2,
            bidOnlyListingCount: 0,
            lowestBuyoutCopper: 5000,
            quantityAtLowestBuyout: 2,
            lowestBidCopper: null,
            priceLevels: [
                new NonCommodityPriceLevel(buyoutCopper: 5000, quantityPerListing: 1, listingCount: 2, totalQuantity: 2),
                new NonCommodityPriceLevel(buyoutCopper: 6000, quantityPerListing: 1, listingCount: 1, totalQuantity: 1),
            ],
        );

        $connectedRealmMarket = new ConnectedRealmMarketData(
            connectedRealmId: 1084,
            summaries: [$identity->getFingerprint() => $summary],
            totalAuctions: 3,
            totalQuantity: 3,
        );

        $plan = new CraftPlan(
            output: new NonCommodityOutputTarget($identity),
            baseOutputQuantity: 1,
            reagents: [
                new SelectedReagent('ore', 190316, 1, new FixedUnitCost(1000)),
            ],
            salePriceAssumption: new CurrentLowestAsk(),
        );

        $result = $this->analyzer->evaluate($plan, CrafterState::none(), null, $connectedRealmMarket);

        $this->assertTrue($result->isFullyPriced());
        $this->assertSame(OutputPriceStatus::PricedFromCurrentAsk, $result->outputResolution->status);
        $this->assertSame(5000, $result->outputResolution->unitSalePriceCopper);
    }

    public function testNonCommodityOutputLowestBucketMultiQuantityYieldsAmbiguousNonCommodityLot(): void
    {
        $identity = new MarketItemIdentity(210000);
        $item = new AuctionItem(id: 210000, context: 0);

        // Minimum buyout 5000 has a lot with quantityPerListing = 3
        $summary = new AuctionItemMarketSummary(
            item: $item,
            identity: $identity,
            listingCount: 1,
            totalQuantity: 3,
            buyoutListingCount: 1,
            bidOnlyListingCount: 0,
            lowestBuyoutCopper: 5000,
            quantityAtLowestBuyout: 3,
            lowestBidCopper: null,
            priceLevels: [
                new NonCommodityPriceLevel(buyoutCopper: 5000, quantityPerListing: 3, listingCount: 1, totalQuantity: 3),
            ],
        );

        $connectedRealmMarket = new ConnectedRealmMarketData(
            connectedRealmId: 1084,
            summaries: [$identity->getFingerprint() => $summary],
            totalAuctions: 1,
            totalQuantity: 3,
        );

        $plan = new CraftPlan(
            output: new NonCommodityOutputTarget($identity),
            baseOutputQuantity: 1,
            reagents: [
                new SelectedReagent('ore', 190316, 1, new FixedUnitCost(1000)),
            ],
            salePriceAssumption: new CurrentLowestAsk(),
        );

        $result = $this->analyzer->evaluate($plan, CrafterState::none(), null, $connectedRealmMarket);

        $this->assertFalse($result->isFullyPriced());
        $this->assertSame(OutputPriceStatus::AmbiguousNonCommodityLot, $result->outputResolution->status);
    }

    public function testNonCommodityOutputLowestBucketTieMixedQtyYieldsAmbiguousNonCommodityLot(): void
    {
        $identity = new MarketItemIdentity(210000);
        $item = new AuctionItem(id: 210000, context: 0);

        // Same minimum buyout 5000 contains qty=1 AND qty=3
        $summary = new AuctionItemMarketSummary(
            item: $item,
            identity: $identity,
            listingCount: 2,
            totalQuantity: 4,
            buyoutListingCount: 2,
            bidOnlyListingCount: 0,
            lowestBuyoutCopper: 5000,
            quantityAtLowestBuyout: 4,
            lowestBidCopper: null,
            priceLevels: [
                new NonCommodityPriceLevel(buyoutCopper: 5000, quantityPerListing: 1, listingCount: 1, totalQuantity: 1),
                new NonCommodityPriceLevel(buyoutCopper: 5000, quantityPerListing: 3, listingCount: 1, totalQuantity: 3),
                new NonCommodityPriceLevel(buyoutCopper: 7000, quantityPerListing: 1, listingCount: 1, totalQuantity: 1),
            ],
        );

        $connectedRealmMarket = new ConnectedRealmMarketData(
            connectedRealmId: 1084,
            summaries: [$identity->getFingerprint() => $summary],
            totalAuctions: 3,
            totalQuantity: 5,
        );

        $plan = new CraftPlan(
            output: new NonCommodityOutputTarget($identity),
            baseOutputQuantity: 1,
            reagents: [
                new SelectedReagent('ore', 190316, 1, new FixedUnitCost(1000)),
            ],
            salePriceAssumption: new CurrentLowestAsk(),
        );

        $result = $this->analyzer->evaluate($plan, CrafterState::none(), null, $connectedRealmMarket);

        $this->assertFalse($result->isFullyPriced());
        $this->assertSame(OutputPriceStatus::AmbiguousNonCommodityLot, $result->outputResolution->status);
    }

    public function testExpectationValidationRejectsUnknownKey(): void
    {
        $plan = new CraftPlan(
            output: new CommodityOutputTarget(190315),
            baseOutputQuantity: 1,
            reagents: [
                new SelectedReagent('herb', 190315, 5, new FixedUnitCost(100)),
            ],
            salePriceAssumption: new CustomUnitSalePrice(1000),
        );

        $crafterState = new CrafterState(
            resourcefulnessSavings: [
                'unknown_reagent' => ExactFraction::of(1, 1),
            ],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not match any SelectedReagent key');
        $this->analyzer->evaluate($plan, $crafterState, null, null);
    }

    public function testExpectationValidationRejectsSavingsExceedingRequired(): void
    {
        $plan = new CraftPlan(
            output: new CommodityOutputTarget(190315),
            baseOutputQuantity: 1,
            reagents: [
                new SelectedReagent('herb', 190315, 5, new FixedUnitCost(100)),
            ],
            salePriceAssumption: new CustomUnitSalePrice(1000),
        );

        $crafterState = new CrafterState(
            resourcefulnessSavings: [
                'herb' => ExactFraction::of(6, 1),
            ],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds reagent "herb" required quantity');
        $this->analyzer->evaluate($plan, $crafterState, null, null);
    }

    public function testExpectationValidationRejectsRefundExceedingBaseConcentration(): void
    {
        $plan = new CraftPlan(
            output: new CommodityOutputTarget(190315),
            baseOutputQuantity: 1,
            reagents: [
                new SelectedReagent('herb', 190315, 5, new FixedUnitCost(100)),
            ],
            salePriceAssumption: new CustomUnitSalePrice(1000),
            baseConcentrationCost: 50,
        );

        $crafterState = new CrafterState(
            ingenuityConcentrationRefund: ExactFraction::of(60, 1),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds plan base concentration cost');
        $this->analyzer->evaluate($plan, $crafterState, null, null);
    }

    public function testFullBaseAndExpectedEconomicsSeparation(): void
    {
        // Craft 1 potion selling for 10,000 copper.
        // 10 herbs @ 500 copper = 5,000 copper material cost.
        // Base concentration = 100.
        // Crafter expectations:
        // - Resourcefulness saves 2 herbs (2 * 500 = 1000 copper savings -> effective 4000 copper)
        // - Multicraft extra output = 0.5 potions (total output 1.5)
        // - Ingenuity refund = 30 concentration (net concentration 70)
        $plan = new CraftPlan(
            output: new CommodityOutputTarget(190315),
            baseOutputQuantity: 1,
            reagents: [
                new SelectedReagent('herb', 190315, 10, new FixedUnitCost(500)),
            ],
            salePriceAssumption: new CustomUnitSalePrice(10000),
            baseConcentrationCost: 100,
        );

        $crafterState = new CrafterState(
            resourcefulnessSavings: ['herb' => ExactFraction::of(2, 1)],
            multicraftExtraOutput: ExactFraction::of(1, 2),
            ingenuityConcentrationRefund: ExactFraction::of(30, 1),
        );

        $result = $this->analyzer->evaluate($plan, $crafterState, null, null);
        $this->assertTrue($result->isFullyPriced());

        $base = $result->baseEconomics;
        $expected = $result->expectedEconomics;
        $this->assertNotNull($base);
        $this->assertNotNull($expected);

        // Base economics (0 procs):
        // Material cost: 5,000
        // Gross revenue: 1 * 10,000 = 10,000
        // Fee: ceil(10,000 * 500 / 10000) = 500
        // Net revenue: 9,500
        // Net profit: 9,500 - 5,000 = 4,500
        // Base ROI: 4500 / 5000 = 90% -> 9000 bps
        $this->assertSame(5000, $base->baseMaterialCostCopper);
        $this->assertSame(10000, $base->baseGrossRevenueCopper);
        $this->assertSame(500, $base->baseSaleFeeCopper);
        $this->assertSame(9500, $base->baseNetRevenueCopper);
        $this->assertSame(4500, $base->baseNetProfitCopper);
        $this->assertNotNull($base->baseRoi);
        $this->assertSame(9000, $base->baseRoi->toBasisPoints());
        $this->assertSame(100, $base->baseConcentrationCost);

        // Expected economics (with procs):
        // Expected material cost: 5000 - 1000 = 4000
        $this->assertSame(0, $expected->expectedMaterialCostCopper->compareTo(ExactFraction::of(4000, 1)));

        // Expected total output: 1 + 0.5 = 1.5 = 3/2
        $this->assertSame(0, $expected->expectedTotalOutputQuantity->compareTo(ExactFraction::of(3, 2)));

        // Expected gross revenue: 1.5 * 10,000 = 15,000 copper
        $this->assertSame(0, $expected->expectedGrossRevenueCopper->compareTo(ExactFraction::of(15000, 1)));

        // Expected fee rate: 15,000 * (500 / 10,000) = 750 copper
        $this->assertSame(0, $expected->expectedSaleFeeCopper->compareTo(ExactFraction::of(750, 1)));

        // Expected net revenue: 15,000 - 750 = 14,250 copper
        $this->assertSame(0, $expected->expectedNetRevenueCopper->compareTo(ExactFraction::of(14250, 1)));

        // Expected net profit: 14,250 - 4,000 = 10,250 copper
        $this->assertSame(0, $expected->expectedNetProfitCopper->compareTo(ExactFraction::of(10250, 1)));

        // Expected ROI: 10,250 / 4,000 = 2.5625 -> 256.25% -> 25625 bps
        $this->assertNotNull($expected->expectedRoi);
        $this->assertSame(25625, $expected->expectedRoi->toBasisPoints());

        // Concentration refund: 30, net concentration: 70
        $this->assertSame(0, $expected->expectedConcentrationRefund->compareTo(ExactFraction::of(30, 1)));
        $this->assertSame(0, $expected->expectedNetConcentrationCost->compareTo(ExactFraction::of(70, 1)));

        // Profit per concentration: 10,250 / 70 = 1025 / 7
        $this->assertNotNull($expected->profitPerConcentration);
        $this->assertSame(0, $expected->profitPerConcentration->compareTo(ExactFraction::of(1025, 7)));
    }

    public function testLossMakingCraftGeneratesNegativeProfit(): void
    {
        $plan = new CraftPlan(
            output: new CommodityOutputTarget(190315),
            baseOutputQuantity: 1,
            reagents: [
                new SelectedReagent('herb', 190315, 1, new FixedUnitCost(1000)),
            ],
            salePriceAssumption: new CustomUnitSalePrice(200),
        );

        $result = $this->analyzer->evaluate($plan, CrafterState::none(), null, null);

        $this->assertTrue($result->isFullyPriced());
        $this->assertNotNull($result->baseEconomics);
        $this->assertNotNull($result->baseEconomics->baseRoi);
        // Gross: 200, fee: 10, net rev: 190, cost: 1000 -> profit: -810 copper
        $this->assertSame(-810, $result->baseEconomics->baseNetProfitCopper);
        // ROI: -810 / 1000 = -81% -> -8100 bps
        $this->assertSame(-8100, $result->baseEconomics->baseRoi->toBasisPoints());
    }
}

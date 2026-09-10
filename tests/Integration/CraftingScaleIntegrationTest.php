<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Analyzer\CraftingProfitabilityAnalyzer;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Math\ExactFraction;
use UncannyWoW\Core\Domain\Model\Crafting\CommodityMarketCost;
use UncannyWoW\Core\Domain\Model\Crafting\CommodityOutputTarget;
use UncannyWoW\Core\Domain\Model\Crafting\CrafterState;
use UncannyWoW\Core\Domain\Model\Crafting\CraftPlan;
use UncannyWoW\Core\Domain\Model\Crafting\CurrentLowestAsk;
use UncannyWoW\Core\Domain\Model\Crafting\CustomUnitSalePrice;
use UncannyWoW\Core\Domain\Model\Crafting\FixedUnitCost;
use UncannyWoW\Core\Domain\Model\Crafting\SelectedReagent;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketData;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketSummary;
use UncannyWoW\Core\Domain\Model\Economy\PriceLevel;

#[CoversClass(CraftingProfitabilityAnalyzer::class)]
final class CraftingScaleIntegrationTest extends TestCase
{
    public function testEvaluateOneThousandCraftPlansAgainstSharedCommodityMarket(): void
    {
        // 1. Build a realistic synthetic CommodityMarketData with 50 common crafting commodities
        $summaries = [];
        for ($itemId = 1000; $itemId < 1050; $itemId++) {
            $basePrice = ($itemId - 999) * 100; // 100 to 5000 copper
            $summaries[$itemId] = new CommodityMarketSummary(
                itemId: $itemId,
                auctionCount: 5,
                totalQuantity: 2000,
                lowestUnitPriceCopper: $basePrice,
                quantityAtLowestPrice: 200,
                highestUnitPriceCopper: $basePrice + 80,
                priceLevels: [
                    new PriceLevel(priceCopper: $basePrice, quantity: 200, listingCount: 2),
                    new PriceLevel(priceCopper: $basePrice + 20, quantity: 500, listingCount: 1),
                    new PriceLevel(priceCopper: $basePrice + 40, quantity: 500, listingCount: 1),
                    new PriceLevel(priceCopper: $basePrice + 80, quantity: 800, listingCount: 1),
                ],
            );
        }

        $sharedMarket = new CommodityMarketData(
            region: Region::EU,
            summaries: $summaries,
            totalAuctions: 250,
            totalQuantity: 100000,
        );

        $analyzer = new CraftingProfitabilityAnalyzer();

        // 2. Generate 1,000 heterogeneous CraftPlans:
        // - profitable plans
        // - loss-making plans
        // - zero-cost plans
        // - plans with fractional crafter expectations
        // - plans with insufficient depth
        $plans = [];
        $crafterStates = [];

        for ($i = 0; $i < 1000; $i++) {
            $outputItemId = 1000 + ($i % 50);
            $reagentItemId1 = 1000 + (($i + 1) % 50);
            $reagentItemId2 = 1000 + (($i + 2) % 50);

            $scenario = $i % 5;

            if ($scenario === 0) {
                // Profitable commodity craft with current lowest ask
                $plans[] = new CraftPlan(
                    output: new CommodityOutputTarget($outputItemId),
                    baseOutputQuantity: 2,
                    reagents: [
                        new SelectedReagent('mat1', $reagentItemId1, 1, new CommodityMarketCost()),
                    ],
                    salePriceAssumption: new CurrentLowestAsk(),
                    baseConcentrationCost: 50,
                );
                $crafterStates[] = new CrafterState(
                    resourcefulnessSavings: ['mat1' => ExactFraction::of(1, 4)],
                    multicraftExtraOutput: ExactFraction::of(1, 2),
                    ingenuityConcentrationRefund: ExactFraction::of(10, 1),
                );
            } elseif ($scenario === 1) {
                // Loss-making craft with custom sale price lower than market cost
                $plans[] = new CraftPlan(
                    output: new CommodityOutputTarget($outputItemId),
                    baseOutputQuantity: 1,
                    reagents: [
                        new SelectedReagent('mat1', $reagentItemId1, 10, new CommodityMarketCost()),
                        new SelectedReagent('mat2', $reagentItemId2, 5, new CommodityMarketCost()),
                    ],
                    salePriceAssumption: new CustomUnitSalePrice(10), // severely under-priced
                    baseConcentrationCost: 0,
                );
                $crafterStates[] = CrafterState::none();
            } elseif ($scenario === 2) {
                // Zero-cost craft with custom sale price
                $plans[] = new CraftPlan(
                    output: new CommodityOutputTarget($outputItemId),
                    baseOutputQuantity: 1,
                    reagents: [],
                    salePriceAssumption: new CustomUnitSalePrice(1500),
                    baseConcentrationCost: 10,
                );
                $crafterStates[] = CrafterState::none();
            } elseif ($scenario === 3) {
                // Insufficient depth craft (demanding 10,000 units when market has only 2,000)
                $plans[] = new CraftPlan(
                    output: new CommodityOutputTarget($outputItemId),
                    baseOutputQuantity: 1,
                    reagents: [
                        new SelectedReagent('mat1', $reagentItemId1, 10000, new CommodityMarketCost()),
                    ],
                    salePriceAssumption: new CustomUnitSalePrice(5000),
                );
                $crafterStates[] = CrafterState::none();
            } else {
                // Fixed cost reagents with market output
                $plans[] = new CraftPlan(
                    output: new CommodityOutputTarget($outputItemId),
                    baseOutputQuantity: 1,
                    reagents: [
                        new SelectedReagent('vendorMat', $reagentItemId1, 3, new FixedUnitCost(250)),
                    ],
                    salePriceAssumption: new CurrentLowestAsk(),
                    baseConcentrationCost: 100,
                );
                $crafterStates[] = new CrafterState(
                    resourcefulnessSavings: ['vendorMat' => ExactFraction::of(1, 1)],
                );
            }
        }

        $this->assertCount(1000, $plans);

        $startMemory = memory_get_usage();
        $startTime = microtime(true);

        $resultsPass1 = [];
        for ($i = 0; $i < 1000; $i++) {
            $resultsPass1[] = $analyzer->evaluate($plans[$i], $crafterStates[$i], $sharedMarket, null);
        }

        $elapsedTime = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage() - $startMemory;

        $this->assertCount(1000, $resultsPass1);

        // Verify deterministic reproducibility (pass 2 matches pass 1 exactly)
        for ($i = 0; $i < 1000; $i += 50) {
            $res2 = $analyzer->evaluate($plans[$i], $crafterStates[$i], $sharedMarket, null);
            $this->assertSame($resultsPass1[$i]->isFullyPriced(), $res2->isFullyPriced());
            if ($resultsPass1[$i]->isFullyPriced()) {
                $this->assertNotNull($resultsPass1[$i]->baseEconomics);
                $this->assertNotNull($res2->baseEconomics);
                $this->assertSame(
                    $resultsPass1[$i]->baseEconomics->baseNetProfitCopper,
                    $res2->baseEconomics->baseNetProfitCopper,
                );
            }
        }

        // Validate heterogeneous conditions were handled properly:
        // Scenario 1 (i = 1): loss-making -> negative profit
        $this->assertTrue($resultsPass1[1]->isFullyPriced());
        $this->assertNotNull($resultsPass1[1]->baseEconomics);
        $this->assertLessThan(0, $resultsPass1[1]->baseEconomics->baseNetProfitCopper);

        // Scenario 2 (i = 2): zero cost -> null ROI
        $this->assertTrue($resultsPass1[2]->isFullyPriced());
        $this->assertNotNull($resultsPass1[2]->baseEconomics);
        $this->assertSame(0, $resultsPass1[2]->baseEconomics->baseMaterialCostCopper);
        $this->assertNull($resultsPass1[2]->baseEconomics->baseRoi);

        // Scenario 3 (i = 3): insufficient depth -> unpriced
        $this->assertFalse($resultsPass1[3]->isFullyPriced());
        $this->assertNull($resultsPass1[3]->baseEconomics);

        // Output informational benchmark metrics
        fwrite(\STDERR, sprintf(
            "\n[Scale Test] Evaluated 1,000 CraftPlans in %.3f ms (Memory delta: %.2f KB)\n",
            $elapsedTime * 1000,
            $memoryUsed / 1024,
        ));
    }
}

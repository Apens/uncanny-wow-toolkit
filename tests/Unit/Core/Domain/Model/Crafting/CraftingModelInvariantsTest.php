<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\Crafting;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Math\ExactFraction;
use UncannyWoW\Core\Domain\Model\Crafting\CommodityOutputTarget;
use UncannyWoW\Core\Domain\Model\Crafting\CrafterState;
use UncannyWoW\Core\Domain\Model\Crafting\CraftPlan;
use UncannyWoW\Core\Domain\Model\Crafting\CurrentLowestAsk;
use UncannyWoW\Core\Domain\Model\Crafting\CustomUnitSalePrice;
use UncannyWoW\Core\Domain\Model\Crafting\FixedUnitCost;
use UncannyWoW\Core\Domain\Model\Crafting\OutputPriceResolution;
use UncannyWoW\Core\Domain\Model\Crafting\OutputPriceStatus;
use UncannyWoW\Core\Domain\Model\Crafting\ReagentCostBreakdown;
use UncannyWoW\Core\Domain\Model\Crafting\ReagentPricingStatus;
use UncannyWoW\Core\Domain\Model\Crafting\SelectedReagent;

#[CoversClass(CraftPlan::class)]
#[CoversClass(CrafterState::class)]
#[CoversClass(OutputPriceResolution::class)]
#[CoversClass(ReagentCostBreakdown::class)]
final class CraftingModelInvariantsTest extends TestCase
{
    public function testCraftPlanRejectsNonPositiveBaseQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Base output quantity must be strictly positive');
        new CraftPlan(
            output: new CommodityOutputTarget(100),
            baseOutputQuantity: 0,
            reagents: [],
            salePriceAssumption: new CurrentLowestAsk(),
        );
    }

    public function testCraftPlanRejectsNegativeBaseConcentration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Base concentration cost cannot be negative');
        new CraftPlan(
            output: new CommodityOutputTarget(100),
            baseOutputQuantity: 1,
            reagents: [],
            salePriceAssumption: new CurrentLowestAsk(),
            baseConcentrationCost: -5,
        );
    }

    public function testCraftPlanRejectsDuplicateReagentKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate reagent key "herb" found in CraftPlan');
        new CraftPlan(
            output: new CommodityOutputTarget(100),
            baseOutputQuantity: 1,
            reagents: [
                new SelectedReagent('herb', 101, 1, new FixedUnitCost(10)),
                new SelectedReagent('herb', 102, 1, new FixedUnitCost(20)),
            ],
            salePriceAssumption: new CurrentLowestAsk(),
        );
    }

    public function testCrafterStateRejectsNegativeExpectations(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Multicraft extra output expectation cannot be negative');
        new CrafterState(
            multicraftExtraOutput: ExactFraction::of(-1, 2),
        );
    }

    public function testCrafterStateRejectsNegativeIngenuity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ingenuity concentration refund expectation cannot be negative');
        new CrafterState(
            ingenuityConcentrationRefund: ExactFraction::of(-10, 1),
        );
    }

    public function testCrafterStateRejectsNegativeResourcefulness(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Resourcefulness expected saved quantity for reagent "herb" cannot be negative');
        new CrafterState(
            resourcefulnessSavings: [
                'herb' => ExactFraction::of(-1, 1),
            ],
        );
    }

    public function testOutputPriceResolutionInvariants(): void
    {
        $target = new CommodityOutputTarget(100);

        // Priced without price throws
        $this->expectException(\InvalidArgumentException::class);
        new OutputPriceResolution($target, new CurrentLowestAsk(), OutputPriceStatus::PricedFromCurrentAsk, null);
    }

    public function testOutputPriceResolutionUnpricedCannotHavePrice(): void
    {
        $target = new CommodityOutputTarget(100);

        $this->expectException(\InvalidArgumentException::class);
        new OutputPriceResolution($target, new CurrentLowestAsk(), OutputPriceStatus::MarketDataRequired, 500);
    }

    public function testReagentCostBreakdownInvariants(): void
    {
        $reagent = new SelectedReagent('herb', 101, 5, new FixedUnitCost(10));

        // Priced without full cost throws
        $this->expectException(\InvalidArgumentException::class);
        new ReagentCostBreakdown(
            reagent: $reagent,
            status: ReagentPricingStatus::Priced,
            requiredQuantity: 5,
            priceableQuantity: 5,
            observedPartialMarketCostCopper: 50,
            fullAcquisitionCostCopper: null,
            expectedSavedQuantity: ExactFraction::zero(),
            expectedSavingsCopper: ExactFraction::zero(),
            expectedEffectiveCostCopper: ExactFraction::of(50, 1),
            levelsConsumed: 1,
            lastLevelUnitsConsumed: 5,
        );
    }
}

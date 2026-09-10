<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Analyzer;

use UncannyWoW\Core\Domain\Math\ExactFraction;
use UncannyWoW\Core\Domain\Model\Crafting\ReagentCostBreakdown;
use UncannyWoW\Core\Domain\Model\Crafting\SelectedReagent;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketData;
use UncannyWoW\Core\Domain\Model\Opportunity\SafeIntegerMath;

/**
 * Pure pricer for craft reagents against market depth or fixed costs.
 *
 * @internal
 */
final class ReagentMarketPricer
{
    public function priceReagent(
        SelectedReagent $reagent,
        ExactFraction $expectedSavedQuantity,
        ?CommodityMarketData $commodityMarket,
    ): ReagentCostBreakdown {
        if ($reagent->costSource->isCustom()) {
            /** @var \UncannyWoW\Core\Domain\Model\Crafting\FixedUnitCost $fixedCost */
            $fixedCost = $reagent->costSource;
            $fullAcquisitionCost = SafeIntegerMath::checkedMultiply($reagent->quantity, $fixedCost->unitCostCopper);

            // Average Acquisition Cost Basis:
            // savings = expectedSavedQuantity * (fullCost / quantity)
            $expectedSavings = $this->calculateExpectedSavings($expectedSavedQuantity, $fullAcquisitionCost, $reagent->quantity);
            $expectedEffectiveCost = (ExactFraction::of($fullAcquisitionCost, 1))->subtract($expectedSavings);

            return ReagentCostBreakdown::pricedFromFixedCost(
                reagent: $reagent,
                fullAcquisitionCostCopper: $fullAcquisitionCost,
                expectedSavedQuantity: $expectedSavedQuantity,
                expectedSavingsCopper: $expectedSavings,
                expectedEffectiveCostCopper: $expectedEffectiveCost,
            );
        }

        // Commodity market pricing
        if ($commodityMarket === null) {
            return ReagentCostBreakdown::marketDataRequired($reagent, $expectedSavedQuantity);
        }

        $summary = $commodityMarket->get($reagent->itemId);
        if ($summary === null || empty($summary->priceLevels)) {
            return ReagentCostBreakdown::marketUnavailable($reagent, $expectedSavedQuantity);
        }

        $remaining = $reagent->quantity;
        $priceable = 0;
        $partialCost = 0;
        $levelsConsumed = 0;
        $lastLevelUnits = 0;

        foreach ($summary->priceLevels as $level) {
            if ($remaining === 0) {
                break;
            }

            $take = min($remaining, $level->quantity);
            $costPart = SafeIntegerMath::checkedMultiply($take, $level->priceCopper);
            $partialCost = SafeIntegerMath::checkedAdd($partialCost, $costPart);

            $priceable += $take;
            $remaining -= $take;
            $levelsConsumed++;
            $lastLevelUnits = $take;
        }

        if ($remaining > 0) {
            return ReagentCostBreakdown::insufficientMarketDepth(
                reagent: $reagent,
                priceableQuantity: $priceable,
                observedPartialMarketCostCopper: $partialCost,
                expectedSavedQuantity: $expectedSavedQuantity,
                levelsConsumed: $levelsConsumed,
                lastLevelUnitsConsumed: $lastLevelUnits,
            );
        }

        $fullAcquisitionCost = $partialCost;
        $expectedSavings = $this->calculateExpectedSavings($expectedSavedQuantity, $fullAcquisitionCost, $reagent->quantity);
        $expectedEffectiveCost = (ExactFraction::of($fullAcquisitionCost, 1))->subtract($expectedSavings);

        return ReagentCostBreakdown::pricedFromMarket(
            reagent: $reagent,
            fullAcquisitionCostCopper: $fullAcquisitionCost,
            expectedSavedQuantity: $expectedSavedQuantity,
            expectedSavingsCopper: $expectedSavings,
            expectedEffectiveCostCopper: $expectedEffectiveCost,
            levelsConsumed: $levelsConsumed,
            lastLevelUnitsConsumed: $lastLevelUnits,
        );
    }

    private function calculateExpectedSavings(ExactFraction $expectedSavedQuantity, int $fullAcquisitionCost, int $requiredQuantity): ExactFraction
    {
        if ($expectedSavedQuantity->isZero() || $fullAcquisitionCost === 0) {
            return ExactFraction::zero();
        }

        $averageUnitCost = ExactFraction::of($fullAcquisitionCost, $requiredQuantity);

        return $expectedSavedQuantity->multiply($averageUnitCost);
    }
}

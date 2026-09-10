<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Analyzer;

use UncannyWoW\Core\Domain\Math\ExactFraction;
use UncannyWoW\Core\Domain\Model\Crafting\BaseCraftingEconomics;
use UncannyWoW\Core\Domain\Model\Crafting\CommodityOutputTarget;
use UncannyWoW\Core\Domain\Model\Crafting\CrafterState;
use UncannyWoW\Core\Domain\Model\Crafting\CraftingProfitabilityResult;
use UncannyWoW\Core\Domain\Model\Crafting\CraftingRoi;
use UncannyWoW\Core\Domain\Model\Crafting\CraftPlan;
use UncannyWoW\Core\Domain\Model\Crafting\CurrentLowestAsk;
use UncannyWoW\Core\Domain\Model\Crafting\CustomUnitSalePrice;
use UncannyWoW\Core\Domain\Model\Crafting\ExpectedCraftingEconomics;
use UncannyWoW\Core\Domain\Model\Crafting\NonCommodityOutputTarget;
use UncannyWoW\Core\Domain\Model\Crafting\OutputPriceResolution;
use UncannyWoW\Core\Domain\Model\Crafting\ReagentCostBreakdown;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketData;
use UncannyWoW\Core\Domain\Model\Economy\ConnectedRealmMarketData;
use UncannyWoW\Core\Domain\Model\Opportunity\AuctionHouseFeePolicy;
use UncannyWoW\Core\Domain\Model\Opportunity\SafeIntegerMath;

/**
 * Pure read-only economic analysis engine for one explicit craft execution.
 *
 * Consumes:
 * - caller-supplied CraftPlan
 * - caller-supplied CrafterState expectations
 * - optional already-materialized market data
 *
 * Produces deterministic base economics and exact expected economics without network or extrapolation.
 */
final class CraftingProfitabilityAnalyzer
{
    private ReagentMarketPricer $reagentPricer;

    public function __construct(
        ?ReagentMarketPricer $reagentPricer = null,
    ) {
        $this->reagentPricer = $reagentPricer ?? new ReagentMarketPricer();
    }

    public function evaluate(
        CraftPlan $plan,
        CrafterState $crafterState,
        ?CommodityMarketData $commodityMarket = null,
        ?ConnectedRealmMarketData $connectedRealmMarket = null,
        ?AuctionHouseFeePolicy $feePolicy = null,
    ): CraftingProfitabilityResult {
        $this->validateExpectationsAgainstPlan($plan, $crafterState);

        $activeFeePolicy = $feePolicy ?? new AuctionHouseFeePolicy();

        $outputResolution = $this->resolveOutputPrice($plan, $commodityMarket, $connectedRealmMarket);
        $reagentBreakdowns = $this->resolveReagentCosts($plan, $crafterState, $commodityMarket);

        $isFullyPriced = $outputResolution->isPriced();
        if ($isFullyPriced) {
            foreach ($reagentBreakdowns as $breakdown) {
                if (! $breakdown->isPriced()) {
                    $isFullyPriced = false;
                    break;
                }
            }
        }

        if (! $isFullyPriced) {
            return new CraftingProfitabilityResult(
                plan: $plan,
                crafterState: $crafterState,
                outputResolution: $outputResolution,
                reagentBreakdowns: $reagentBreakdowns,
                baseEconomics: null,
                expectedEconomics: null,
            );
        }

        /** @var int $unitSalePrice */
        $unitSalePrice = $outputResolution->unitSalePriceCopper;

        $baseEconomics = $this->computeBaseEconomics($plan, $reagentBreakdowns, $unitSalePrice, $activeFeePolicy);
        $expectedEconomics = $this->computeExpectedEconomics($plan, $crafterState, $reagentBreakdowns, $unitSalePrice, $activeFeePolicy);

        return new CraftingProfitabilityResult(
            plan: $plan,
            crafterState: $crafterState,
            outputResolution: $outputResolution,
            reagentBreakdowns: $reagentBreakdowns,
            baseEconomics: $baseEconomics,
            expectedEconomics: $expectedEconomics,
        );
    }

    private function validateExpectationsAgainstPlan(CraftPlan $plan, CrafterState $crafterState): void
    {
        $planReagentsByKey = [];
        foreach ($plan->reagents as $reagent) {
            $planReagentsByKey[$reagent->key] = $reagent;
        }

        foreach ($crafterState->resourcefulnessSavings as $key => $savedQuantity) {
            if (! isset($planReagentsByKey[$key])) {
                throw new \InvalidArgumentException(sprintf(
                    'Resourcefulness expectation key "%s" does not match any SelectedReagent key in CraftPlan.',
                    $key,
                ));
            }

            $reagent = $planReagentsByKey[$key];
            $requiredFraction = ExactFraction::of($reagent->quantity, 1);
            if ($savedQuantity->compareTo($requiredFraction) > 0) {
                throw new \InvalidArgumentException(sprintf(
                    'Resourcefulness expected saved quantity (%s) exceeds reagent "%s" required quantity (%d).',
                    $savedQuantity,
                    $key,
                    $reagent->quantity,
                ));
            }
        }

        if ($crafterState->ingenuityConcentrationRefund !== null) {
            $baseCostFraction = ExactFraction::of($plan->baseConcentrationCost, 1);
            if ($crafterState->ingenuityConcentrationRefund->compareTo($baseCostFraction) > 0) {
                throw new \InvalidArgumentException(sprintf(
                    'Ingenuity concentration refund expectation (%s) exceeds plan base concentration cost (%d).',
                    $crafterState->ingenuityConcentrationRefund,
                    $plan->baseConcentrationCost,
                ));
            }
        }
    }

    private function resolveOutputPrice(
        CraftPlan $plan,
        ?CommodityMarketData $commodityMarket,
        ?ConnectedRealmMarketData $connectedRealmMarket,
    ): OutputPriceResolution {
        $assumption = $plan->salePriceAssumption;
        $outputTarget = $plan->output;

        if ($assumption instanceof CustomUnitSalePrice) {
            return OutputPriceResolution::customPriced($outputTarget, $assumption);
        }

        /** @var CurrentLowestAsk $assumption */
        if ($outputTarget instanceof CommodityOutputTarget) {
            if ($commodityMarket === null) {
                return OutputPriceResolution::marketDataRequired($outputTarget, $assumption);
            }

            $summary = $commodityMarket->get($outputTarget->itemId);
            if ($summary === null || empty($summary->priceLevels)) {
                return OutputPriceResolution::marketUnavailable($outputTarget, $assumption);
            }

            return OutputPriceResolution::pricedFromCurrentAsk($outputTarget, $assumption, $summary->lowestUnitPriceCopper);
        }

        /** @var NonCommodityOutputTarget $outputTarget */
        if ($connectedRealmMarket === null) {
            return OutputPriceResolution::marketDataRequired($outputTarget, $assumption);
        }

        $summary = $connectedRealmMarket->get($outputTarget->identity);
        if ($summary === null || empty($summary->priceLevels) || $summary->lowestBuyoutCopper === null) {
            return OutputPriceResolution::marketUnavailable($outputTarget, $assumption);
        }

        $minBuyout = $summary->lowestBuyoutCopper;

        // Inspect ALL NonCommodityPriceLevel entries having that exact minimum buyoutCopper:
        // Automated unit pricing is valid ONLY if EVERY level in that minimum-price bucket has quantityPerListing === 1.
        $hasLevelsAtMin = false;
        foreach ($summary->priceLevels as $level) {
            if ($level->buyoutCopper === $minBuyout) {
                $hasLevelsAtMin = true;
                if ($level->quantityPerListing !== 1) {
                    return OutputPriceResolution::ambiguousNonCommodityLot($outputTarget, $assumption);
                }
            }
        }

        if (! $hasLevelsAtMin) {
            return OutputPriceResolution::marketUnavailable($outputTarget, $assumption);
        }

        return OutputPriceResolution::pricedFromCurrentAsk($outputTarget, $assumption, $minBuyout);
    }

    /**
     * @return list<ReagentCostBreakdown>
     */
    private function resolveReagentCosts(
        CraftPlan $plan,
        CrafterState $crafterState,
        ?CommodityMarketData $commodityMarket,
    ): array {
        $breakdowns = [];
        foreach ($plan->reagents as $reagent) {
            $expectedSavedQuantity = $crafterState->resourcefulnessSavings[$reagent->key] ?? ExactFraction::zero();
            $breakdowns[] = $this->reagentPricer->priceReagent($reagent, $expectedSavedQuantity, $commodityMarket);
        }

        return $breakdowns;
    }

    /**
     * @param list<ReagentCostBreakdown> $reagentBreakdowns
     */
    private function computeBaseEconomics(
        CraftPlan $plan,
        array $reagentBreakdowns,
        int $unitSalePrice,
        AuctionHouseFeePolicy $feePolicy,
    ): BaseCraftingEconomics {
        $baseMaterialCost = 0;
        foreach ($reagentBreakdowns as $breakdown) {
            /** @var int $cost */
            $cost = $breakdown->fullAcquisitionCostCopper;
            $baseMaterialCost = SafeIntegerMath::checkedAdd($baseMaterialCost, $cost);
        }

        $baseOutputQuantity = $plan->baseOutputQuantity;
        $baseGrossRevenue = SafeIntegerMath::checkedMultiply($baseOutputQuantity, $unitSalePrice);
        $baseSaleFee = $feePolicy->calculateFee($baseGrossRevenue);
        $baseNetRevenue = SafeIntegerMath::checkedSubtract($baseGrossRevenue, $baseSaleFee);
        $baseNetProfit = SafeIntegerMath::checkedSubtract($baseNetRevenue, $baseMaterialCost);

        $baseRoi = null;
        if ($baseMaterialCost > 0) {
            $baseRoi = CraftingRoi::fromNetProfitAndCost(
                ExactFraction::of($baseNetProfit, 1),
                ExactFraction::of($baseMaterialCost, 1),
            );
        }

        return new BaseCraftingEconomics(
            baseMaterialCostCopper: $baseMaterialCost,
            baseOutputQuantity: $baseOutputQuantity,
            assumedUnitSalePriceCopper: $unitSalePrice,
            baseGrossRevenueCopper: $baseGrossRevenue,
            baseSaleFeeCopper: $baseSaleFee,
            baseNetRevenueCopper: $baseNetRevenue,
            baseNetProfitCopper: $baseNetProfit,
            baseRoi: $baseRoi,
            baseConcentrationCost: $plan->baseConcentrationCost,
        );
    }

    /**
     * @param list<ReagentCostBreakdown> $reagentBreakdowns
     */
    private function computeExpectedEconomics(
        CraftPlan $plan,
        CrafterState $crafterState,
        array $reagentBreakdowns,
        int $unitSalePrice,
        AuctionHouseFeePolicy $feePolicy,
    ): ExpectedCraftingEconomics {
        $expectedMaterialCost = ExactFraction::zero();
        foreach ($reagentBreakdowns as $breakdown) {
            /** @var ExactFraction $effectiveCost */
            $effectiveCost = $breakdown->expectedEffectiveCostCopper;
            $expectedMaterialCost = $expectedMaterialCost->add($effectiveCost);
        }

        $expectedAdditionalOutput = $crafterState->multicraftExtraOutput ?? ExactFraction::zero();
        $expectedTotalOutput = (ExactFraction::of($plan->baseOutputQuantity, 1))->add($expectedAdditionalOutput);

        $expectedGrossRevenue = $expectedTotalOutput->multiplyByInt($unitSalePrice);

        // Expected AH cut fee rate assumption: cutBasisPoints / 10000
        $feeRate = ExactFraction::of($feePolicy->cutBasisPoints, 10000);
        $expectedSaleFee = $expectedGrossRevenue->multiply($feeRate);

        $expectedNetRevenue = $expectedGrossRevenue->subtract($expectedSaleFee);
        $expectedNetProfit = $expectedNetRevenue->subtract($expectedMaterialCost);

        $expectedRoi = null;
        if ($expectedMaterialCost->isPositive()) {
            $expectedRoi = CraftingRoi::fromNetProfitAndCost($expectedNetProfit, $expectedMaterialCost);
        }

        $expectedRefund = $crafterState->ingenuityConcentrationRefund ?? ExactFraction::zero();
        $baseConcentrationFraction = ExactFraction::of($plan->baseConcentrationCost, 1);
        $expectedNetConcentration = $baseConcentrationFraction->subtract($expectedRefund);

        $profitPerConcentration = null;
        if ($expectedNetConcentration->isPositive()) {
            $profitPerConcentration = $expectedNetProfit->divide($expectedNetConcentration);
        }

        return new ExpectedCraftingEconomics(
            expectedMaterialCostCopper: $expectedMaterialCost,
            expectedAdditionalOutputQuantity: $expectedAdditionalOutput,
            expectedTotalOutputQuantity: $expectedTotalOutput,
            expectedGrossRevenueCopper: $expectedGrossRevenue,
            expectedSaleFeeCopper: $expectedSaleFee,
            expectedNetRevenueCopper: $expectedNetRevenue,
            expectedNetProfitCopper: $expectedNetProfit,
            expectedRoi: $expectedRoi,
            expectedConcentrationRefund: $expectedRefund,
            expectedNetConcentrationCost: $expectedNetConcentration,
            profitPerConcentration: $profitPerConcentration,
        );
    }
}

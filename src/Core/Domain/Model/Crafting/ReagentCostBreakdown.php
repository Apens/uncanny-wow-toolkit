<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Crafting;

use UncannyWoW\Core\Domain\Math\ExactFraction;

final readonly class ReagentCostBreakdown
{
    public function __construct(
        public SelectedReagent $reagent,
        public ReagentPricingStatus $status,
        public int $requiredQuantity,
        public int $priceableQuantity,
        public ?int $observedPartialMarketCostCopper,
        public ?int $fullAcquisitionCostCopper,
        public ExactFraction $expectedSavedQuantity,
        public ?ExactFraction $expectedSavingsCopper,
        public ?ExactFraction $expectedEffectiveCostCopper,
        public int $levelsConsumed,
        public int $lastLevelUnitsConsumed,
    ) {
        if ($this->requiredQuantity <= 0) {
            throw new \InvalidArgumentException(sprintf('Required quantity must be positive, got %d.', $this->requiredQuantity));
        }

        if ($this->priceableQuantity < 0) {
            throw new \InvalidArgumentException(sprintf('Priceable quantity cannot be negative, got %d.', $this->priceableQuantity));
        }

        if ($this->isPriced()) {
            if ($this->fullAcquisitionCostCopper === null) {
                throw new \InvalidArgumentException('Fully priced reagent must provide fullAcquisitionCostCopper.');
            }
            if ($this->fullAcquisitionCostCopper < 0) {
                throw new \InvalidArgumentException(sprintf('Full acquisition cost cannot be negative, got %d.', $this->fullAcquisitionCostCopper));
            }
            if ($this->priceableQuantity !== $this->requiredQuantity) {
                throw new \InvalidArgumentException(sprintf('Fully priced reagent priceable quantity (%d) must match required quantity (%d).', $this->priceableQuantity, $this->requiredQuantity));
            }
            if ($this->expectedSavingsCopper === null || $this->expectedEffectiveCostCopper === null) {
                throw new \InvalidArgumentException('Fully priced reagent must provide expected savings and effective cost.');
            }
        } else {
            if ($this->fullAcquisitionCostCopper !== null) {
                throw new \InvalidArgumentException('Incompletely priced reagent cannot provide fullAcquisitionCostCopper.');
            }
            if ($this->expectedSavingsCopper !== null || $this->expectedEffectiveCostCopper !== null) {
                throw new \InvalidArgumentException('Incompletely priced reagent cannot provide expected savings or effective cost.');
            }
        }
    }

    public static function pricedFromMarket(
        SelectedReagent $reagent,
        int $fullAcquisitionCostCopper,
        ExactFraction $expectedSavedQuantity,
        ExactFraction $expectedSavingsCopper,
        ExactFraction $expectedEffectiveCostCopper,
        int $levelsConsumed,
        int $lastLevelUnitsConsumed,
    ): self {
        return new self(
            reagent: $reagent,
            status: ReagentPricingStatus::Priced,
            requiredQuantity: $reagent->quantity,
            priceableQuantity: $reagent->quantity,
            observedPartialMarketCostCopper: $fullAcquisitionCostCopper,
            fullAcquisitionCostCopper: $fullAcquisitionCostCopper,
            expectedSavedQuantity: $expectedSavedQuantity,
            expectedSavingsCopper: $expectedSavingsCopper,
            expectedEffectiveCostCopper: $expectedEffectiveCostCopper,
            levelsConsumed: $levelsConsumed,
            lastLevelUnitsConsumed: $lastLevelUnitsConsumed,
        );
    }

    public static function pricedFromFixedCost(
        SelectedReagent $reagent,
        int $fullAcquisitionCostCopper,
        ExactFraction $expectedSavedQuantity,
        ExactFraction $expectedSavingsCopper,
        ExactFraction $expectedEffectiveCostCopper,
    ): self {
        return new self(
            reagent: $reagent,
            status: ReagentPricingStatus::CustomPriced,
            requiredQuantity: $reagent->quantity,
            priceableQuantity: $reagent->quantity,
            observedPartialMarketCostCopper: null,
            fullAcquisitionCostCopper: $fullAcquisitionCostCopper,
            expectedSavedQuantity: $expectedSavedQuantity,
            expectedSavingsCopper: $expectedSavingsCopper,
            expectedEffectiveCostCopper: $expectedEffectiveCostCopper,
            levelsConsumed: 0,
            lastLevelUnitsConsumed: 0,
        );
    }

    public static function marketDataRequired(SelectedReagent $reagent, ExactFraction $expectedSavedQuantity): self
    {
        return new self(
            reagent: $reagent,
            status: ReagentPricingStatus::MarketDataRequired,
            requiredQuantity: $reagent->quantity,
            priceableQuantity: 0,
            observedPartialMarketCostCopper: null,
            fullAcquisitionCostCopper: null,
            expectedSavedQuantity: $expectedSavedQuantity,
            expectedSavingsCopper: null,
            expectedEffectiveCostCopper: null,
            levelsConsumed: 0,
            lastLevelUnitsConsumed: 0,
        );
    }

    public static function marketUnavailable(SelectedReagent $reagent, ExactFraction $expectedSavedQuantity): self
    {
        return new self(
            reagent: $reagent,
            status: ReagentPricingStatus::MarketUnavailable,
            requiredQuantity: $reagent->quantity,
            priceableQuantity: 0,
            observedPartialMarketCostCopper: null,
            fullAcquisitionCostCopper: null,
            expectedSavedQuantity: $expectedSavedQuantity,
            expectedSavingsCopper: null,
            expectedEffectiveCostCopper: null,
            levelsConsumed: 0,
            lastLevelUnitsConsumed: 0,
        );
    }

    public static function insufficientMarketDepth(
        SelectedReagent $reagent,
        int $priceableQuantity,
        int $observedPartialMarketCostCopper,
        ExactFraction $expectedSavedQuantity,
        int $levelsConsumed,
        int $lastLevelUnitsConsumed,
    ): self {
        return new self(
            reagent: $reagent,
            status: ReagentPricingStatus::InsufficientMarketDepth,
            requiredQuantity: $reagent->quantity,
            priceableQuantity: $priceableQuantity,
            observedPartialMarketCostCopper: $observedPartialMarketCostCopper,
            fullAcquisitionCostCopper: null,
            expectedSavedQuantity: $expectedSavedQuantity,
            expectedSavingsCopper: null,
            expectedEffectiveCostCopper: null,
            levelsConsumed: $levelsConsumed,
            lastLevelUnitsConsumed: $lastLevelUnitsConsumed,
        );
    }

    public function isPriced(): bool
    {
        return $this->status === ReagentPricingStatus::Priced
            || $this->status === ReagentPricingStatus::CustomPriced;
    }
}

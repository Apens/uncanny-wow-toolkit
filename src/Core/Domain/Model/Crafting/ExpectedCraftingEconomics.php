<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Crafting;

use UncannyWoW\Core\Domain\Math\ExactFraction;

final readonly class ExpectedCraftingEconomics
{
    public function __construct(
        public ExactFraction $expectedMaterialCostCopper,
        public ExactFraction $expectedAdditionalOutputQuantity,
        public ExactFraction $expectedTotalOutputQuantity,
        public ExactFraction $expectedGrossRevenueCopper,
        public ExactFraction $expectedSaleFeeCopper,
        public ExactFraction $expectedNetRevenueCopper,
        public ExactFraction $expectedNetProfitCopper,
        public ?CraftingRoi $expectedRoi,
        public ExactFraction $expectedConcentrationRefund,
        public ExactFraction $expectedNetConcentrationCost,
        public ?ExactFraction $profitPerConcentration,
    ) {
        if ($this->expectedMaterialCostCopper->isNegative()) {
            throw new \InvalidArgumentException(sprintf('Expected material cost cannot be negative, got %s.', $this->expectedMaterialCostCopper));
        }

        if ($this->expectedAdditionalOutputQuantity->isNegative()) {
            throw new \InvalidArgumentException(sprintf('Expected additional output quantity cannot be negative, got %s.', $this->expectedAdditionalOutputQuantity));
        }

        if ($this->expectedTotalOutputQuantity->isNegative() || $this->expectedTotalOutputQuantity->isZero()) {
            throw new \InvalidArgumentException(sprintf('Expected total output quantity must be strictly positive, got %s.', $this->expectedTotalOutputQuantity));
        }

        if ($this->expectedGrossRevenueCopper->isNegative()) {
            throw new \InvalidArgumentException(sprintf('Expected gross revenue cannot be negative, got %s.', $this->expectedGrossRevenueCopper));
        }

        if ($this->expectedSaleFeeCopper->isNegative()) {
            throw new \InvalidArgumentException(sprintf('Expected sale fee cannot be negative, got %s.', $this->expectedSaleFeeCopper));
        }

        if ($this->expectedConcentrationRefund->isNegative()) {
            throw new \InvalidArgumentException(sprintf('Expected concentration refund cannot be negative, got %s.', $this->expectedConcentrationRefund));
        }

        if ($this->expectedNetConcentrationCost->isNegative()) {
            throw new \InvalidArgumentException(sprintf('Expected net concentration cost cannot be negative, got %s.', $this->expectedNetConcentrationCost));
        }

        if ($this->expectedMaterialCostCopper->isZero() && $this->expectedRoi !== null) {
            throw new \InvalidArgumentException('Expected ROI must be null when expected material cost is zero.');
        }

        if ($this->expectedMaterialCostCopper->isPositive() && $this->expectedRoi === null) {
            throw new \InvalidArgumentException('Expected ROI cannot be null when expected material cost is positive.');
        }

        if ($this->expectedNetConcentrationCost->isZero() && $this->profitPerConcentration !== null) {
            throw new \InvalidArgumentException('Profit per concentration must be null when expected net concentration cost is zero.');
        }

        if ($this->expectedNetConcentrationCost->isPositive() && $this->profitPerConcentration === null) {
            throw new \InvalidArgumentException('Profit per concentration cannot be null when expected net concentration cost is positive.');
        }
    }
}

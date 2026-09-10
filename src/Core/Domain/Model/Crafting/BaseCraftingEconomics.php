<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Crafting;

use UncannyWoW\Core\Domain\Math\ExactFraction;

final readonly class BaseCraftingEconomics
{
    public function __construct(
        public int $baseMaterialCostCopper,
        public int $baseOutputQuantity,
        public int $assumedUnitSalePriceCopper,
        public int $baseGrossRevenueCopper,
        public int $baseSaleFeeCopper,
        public int $baseNetRevenueCopper,
        public int $baseNetProfitCopper,
        public ?CraftingRoi $baseRoi,
        public int $baseConcentrationCost,
    ) {
        if ($this->baseMaterialCostCopper < 0) {
            throw new \InvalidArgumentException(sprintf('Base material cost cannot be negative, got %d.', $this->baseMaterialCostCopper));
        }

        if ($this->baseOutputQuantity <= 0) {
            throw new \InvalidArgumentException(sprintf('Base output quantity must be positive, got %d.', $this->baseOutputQuantity));
        }

        if ($this->assumedUnitSalePriceCopper < 0) {
            throw new \InvalidArgumentException(sprintf('Assumed unit sale price cannot be negative, got %d.', $this->assumedUnitSalePriceCopper));
        }

        if ($this->baseGrossRevenueCopper < 0) {
            throw new \InvalidArgumentException(sprintf('Base gross revenue cannot be negative, got %d.', $this->baseGrossRevenueCopper));
        }

        if ($this->baseSaleFeeCopper < 0) {
            throw new \InvalidArgumentException(sprintf('Base sale fee cannot be negative, got %d.', $this->baseSaleFeeCopper));
        }

        if ($this->baseConcentrationCost < 0) {
            throw new \InvalidArgumentException(sprintf('Base concentration cost cannot be negative, got %d.', $this->baseConcentrationCost));
        }

        if ($this->baseMaterialCostCopper === 0 && $this->baseRoi !== null) {
            throw new \InvalidArgumentException('Base ROI must be null when base material cost is zero.');
        }

        if ($this->baseMaterialCostCopper > 0 && $this->baseRoi === null) {
            throw new \InvalidArgumentException('Base ROI cannot be null when base material cost is positive.');
        }
    }
}

<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Crafting;

final readonly class CraftPlan
{
    /**
     * @param list<SelectedReagent> $reagents
     */
    public function __construct(
        public CraftOutputTarget $output,
        public int $baseOutputQuantity,
        public array $reagents,
        public OutputSalePriceAssumption $salePriceAssumption,
        public int $baseConcentrationCost = 0,
        public ?int $recipeId = null,
        public ?int $targetQuality = null,
    ) {
        if ($this->baseOutputQuantity <= 0) {
            throw new \InvalidArgumentException(sprintf('Base output quantity must be strictly positive, got %d.', $this->baseOutputQuantity));
        }

        if ($this->baseConcentrationCost < 0) {
            throw new \InvalidArgumentException(sprintf('Base concentration cost cannot be negative, got %d.', $this->baseConcentrationCost));
        }

        if ($this->recipeId !== null && $this->recipeId <= 0) {
            throw new \InvalidArgumentException(sprintf('Recipe ID must be null or a positive integer, got %d.', $this->recipeId));
        }

        if ($this->targetQuality !== null && $this->targetQuality <= 0) {
            throw new \InvalidArgumentException(sprintf('Target quality must be null or a positive integer, got %d.', $this->targetQuality));
        }

        $seenKeys = [];
        foreach ($this->reagents as $reagent) {
            if (isset($seenKeys[$reagent->key])) {
                throw new \InvalidArgumentException(sprintf('Duplicate reagent key "%s" found in CraftPlan.', $reagent->key));
            }
            $seenKeys[$reagent->key] = true;
        }
    }
}

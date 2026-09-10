<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Crafting;

final readonly class CraftingProfitabilityResult
{
    /**
     * @param list<ReagentCostBreakdown> $reagentBreakdowns
     */
    public function __construct(
        public CraftPlan $plan,
        public CrafterState $crafterState,
        public OutputPriceResolution $outputResolution,
        public array $reagentBreakdowns,
        public ?BaseCraftingEconomics $baseEconomics = null,
        public ?ExpectedCraftingEconomics $expectedEconomics = null,
    ) {
        if ($this->isFullyPriced()) {
            if ($this->baseEconomics === null || $this->expectedEconomics === null) {
                throw new \InvalidArgumentException('Fully priced CraftingProfitabilityResult must contain base and expected economics.');
            }
        } else {
            if ($this->baseEconomics !== null || $this->expectedEconomics !== null) {
                throw new \InvalidArgumentException('Incompletely priced CraftingProfitabilityResult cannot contain base or expected economics.');
            }
        }
    }

    public function isFullyPriced(): bool
    {
        if (! $this->outputResolution->isPriced()) {
            return false;
        }

        foreach ($this->reagentBreakdowns as $reagentBreakdown) {
            if (! $reagentBreakdown->isPriced()) {
                return false;
            }
        }

        return true;
    }
}

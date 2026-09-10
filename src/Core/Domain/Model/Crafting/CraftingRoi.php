<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Crafting;

use UncannyWoW\Core\Domain\Math\ExactFraction;
use UncannyWoW\Core\Domain\Model\Opportunity\SafeIntegerMath;

final readonly class CraftingRoi
{
    /**
     * @param ExactFraction $ratio Exact net profit to material cost ratio.
     */
    public function __construct(
        public ExactFraction $ratio,
    ) {}

    public static function fromNetProfitAndCost(ExactFraction $netProfitCopper, ExactFraction $materialCostCopper): self
    {
        if ($materialCostCopper->isZero() || $materialCostCopper->isNegative()) {
            throw new \InvalidArgumentException('CraftingRoi requires a strictly positive material cost denominator.');
        }

        return new self($netProfitCopper->divide($materialCostCopper));
    }

    /**
     * Convert exact ROI ratio to basis points (1 bp = 0.01% = 1/10000) using signed truncation toward zero.
     *
     * Computes on numerator magnitude using SafeIntegerMath::mulDivFloor() to prevent false intermediate overflow.
     */
    public function toBasisPoints(): int
    {
        $numerator = $this->ratio->numerator;
        if ($numerator === 0) {
            return 0;
        }

        $magnitudeBps = SafeIntegerMath::mulDivFloor(
            abs($numerator),
            10000,
            $this->ratio->denominator,
        );

        return $numerator < 0 ? -$magnitudeBps : $magnitudeBps;
    }
}

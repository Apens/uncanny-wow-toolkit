<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Opportunity;

/**
 * Exact representation of prospective Return on Investment (ROI).
 *
 * Preserves the exact rational representation (profit / acquisitionCost) as integers.
 * Provides exact overflow-free Euclidean fraction comparison (compareTo) without float
 * rounding errors or lossy basis-point truncation.
 */
final readonly class Roi
{
    /**
     * @param int $profitCopper Exact prospective profit in copper.
     * @param int $acquisitionCostCopper Exact capital cost in copper (strictly positive).
     */
    public function __construct(
        public int $profitCopper,
        public int $acquisitionCostCopper,
    ) {
        if ($this->acquisitionCostCopper <= 0) {
            throw new \InvalidArgumentException(sprintf(
                'Acquisition cost must be strictly positive, got %d.',
                $this->acquisitionCostCopper,
            ));
        }
    }

    /**
     * Return ROI in integer basis points (100 bps = 1.00%, 10,000 bps = 100.00%).
     *
     * Rounded conservatively using FLOOR division via SafeIntegerMath::mulDivFloor.
     * Note: This is a derived display metric. Exact sorting must use compareTo().
     */
    public function toBasisPoints(): int
    {
        if ($this->profitCopper <= 0) {
            return 0;
        }

        return SafeIntegerMath::mulDivFloor($this->profitCopper, 10000, $this->acquisitionCostCopper);
    }

    /**
     * Compare this ROI to another ROI exactly without floating point or multiplication overflow.
     *
     * Uses Euclidean continued-fraction quotient/remainder comparison.
     *
     * @return int -1 if this < other, 0 if this == other, 1 if this > other
     */
    public function compareTo(self $other): int
    {
        $p1 = $this->profitCopper;
        $c1 = $this->acquisitionCostCopper;

        $p2 = $other->profitCopper;
        $c2 = $other->acquisitionCostCopper;

        // Handle signs if any non-positive profits
        if ($p1 <= 0 || $p2 <= 0) {
            if ($p1 <= 0 && $p2 <= 0) {
                // If both <= 0: -p1/c1 vs -p2/c2
                // We compare normalized signs
                return ($p1 * $c2) <=> ($p2 * $c1);
            }
            return $p1 > 0 ? 1 : -1;
        }

        // Both p1 > 0 and p2 > 0: Exact continued fraction expansion
        // Because each inversion flips the order (a/b > c/d <=> b/a < d/c),
        // we track step parity to invert the comparison on alternate levels.
        $reverse = false;
        while (true) {
            $q1 = intdiv($p1, $c1);
            $q2 = intdiv($p2, $c2);

            if ($q1 !== $q2) {
                $cmp = $q1 <=> $q2;
                return $reverse ? -$cmp : $cmp;
            }

            $r1 = $p1 % $c1;
            $r2 = $p2 % $c2;

            if ($r1 === 0 || $r2 === 0) {
                if ($r1 === 0 && $r2 === 0) {
                    return 0;
                }
                // If r1 == 0, fraction 1 terminated (has no remainder), so it is smaller
                $cmp = $r1 === 0 ? -1 : 1;
                return $reverse ? -$cmp : $cmp;
            }

            // Invert fractions for next level: new fraction 1 is c1 / r1, fraction 2 is c2 / r2
            $p1 = $c1;
            $c1 = $r1;

            $p2 = $c2;
            $c2 = $r2;

            $reverse = !$reverse;
        }
    }
}

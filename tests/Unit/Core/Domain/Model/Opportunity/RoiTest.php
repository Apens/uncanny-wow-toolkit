<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\Opportunity;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Model\Opportunity\Roi;

final class RoiTest extends TestCase
{
    public function testInstantiationWithValidData(): void
    {
        $roi = new Roi(profitCopper: 4250, acquisitionCostCopper: 10000);
        self::assertSame(4250, $roi->profitCopper);
        self::assertSame(10000, $roi->acquisitionCostCopper);
        self::assertSame(4250, $roi->toBasisPoints()); // 42.50%
    }

    public function testZeroOrNegativeAcquisitionCostThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Roi(profitCopper: 100, acquisitionCostCopper: 0);
    }

    public function testZeroOrNegativeProfitYieldsZeroBasisPoints(): void
    {
        $zero = new Roi(profitCopper: 0, acquisitionCostCopper: 1000);
        self::assertSame(0, $zero->toBasisPoints());

        $neg = new Roi(profitCopper: -50, acquisitionCostCopper: 1000);
        self::assertSame(0, $neg->toBasisPoints());
    }

    public function testToBasisPointsConservativelyFloored(): void
    {
        // 1 copper profit on 3 copper cost = 33.333...% -> 3333 bps (floor)
        $roi = new Roi(profitCopper: 1, acquisitionCostCopper: 3);
        self::assertSame(3333, $roi->toBasisPoints());

        // 1 copper profit on 7 copper cost = 14.2857% -> 1428 bps (floor)
        $roi2 = new Roi(profitCopper: 1, acquisitionCostCopper: 7);
        self::assertSame(1428, $roi2->toBasisPoints());
    }

    public function testCompareToExactRationalOrder(): void
    {
        $roiA = new Roi(profitCopper: 50, acquisitionCostCopper: 100);  // 50%
        $roiB = new Roi(profitCopper: 60, acquisitionCostCopper: 100);  // 60%
        $roiC = new Roi(profitCopper: 100, acquisitionCostCopper: 200); // 50%

        self::assertSame(-1, $roiA->compareTo($roiB));
        self::assertSame(1, $roiB->compareTo($roiA));
        self::assertSame(0, $roiA->compareTo($roiC));
    }

    public function testCompareToAdversarialEqualBasisPointsTruncation(): void
    {
        // Two distinct exact ratios that truncate to the exact same basis points:
        // Fraction 1: 10000 / 30001 = 0.3333222... -> 3333 bps
        // Fraction 2: 10001 / 30001 = 0.3333555... -> 3333 bps
        $roi1 = new Roi(profitCopper: 10000, acquisitionCostCopper: 30001);
        $roi2 = new Roi(profitCopper: 10001, acquisitionCostCopper: 30001);

        self::assertSame(3333, $roi1->toBasisPoints());
        self::assertSame(3333, $roi2->toBasisPoints());

        // Exact comparison must distinguish them!
        self::assertSame(-1, $roi1->compareTo($roi2));
        self::assertSame(1, $roi2->compareTo($roi1));

        // Another adversarial case with different denominators:
        // 1 / 3 (0.333333...) vs 3333 / 10000 (0.333300...)
        $roiThird = new Roi(profitCopper: 1, acquisitionCostCopper: 3);
        $roiExact3333 = new Roi(profitCopper: 3333, acquisitionCostCopper: 10000);

        self::assertSame(3333, $roiThird->toBasisPoints());
        self::assertSame(3333, $roiExact3333->toBasisPoints());

        // 1/3 > 3333/10000
        self::assertSame(1, $roiThird->compareTo($roiExact3333));
        self::assertSame(-1, $roiExact3333->compareTo($roiThird));
    }

    public function testCompareToNearPhpIntMaxWithoutMultiplicationOverflow(): void
    {
        // Very large values where (p1 * c2) would overflow 64-bit PHP int
        $c1 = \PHP_INT_MAX - 100;
        $p1 = intdiv($c1, 2); // ~50%

        $c2 = \PHP_INT_MAX - 200;
        $p2 = intdiv($c2, 2) + 10; // slightly > 50%

        $roi1 = new Roi(profitCopper: $p1, acquisitionCostCopper: $c1);
        $roi2 = new Roi(profitCopper: $p2, acquisitionCostCopper: $c2);

        // Native $p1 * $c2 would catastrophically overflow
        // Continued fraction compareTo executes cleanly
        self::assertSame(-1, $roi1->compareTo($roi2));
        self::assertSame(1, $roi2->compareTo($roi1));
    }

    /**
     * Deterministic differential test against reference cross product
     * over several thousand pairs of positive ratios small enough that cross product cannot overflow.
     */
    public function testCompareToDeterministicDifferentialAgainstCrossProduct(): void
    {
        $seed = 777123;
        $lcg = static function () use (&$seed): int {
            $seed = (1103515245 * $seed + 12345) & 0x7FFFFFFF;
            return $seed;
        };

        // 5000 pairs of ratios
        for ($i = 0; $i < 5000; $i++) {
            // Keep profits and costs in [1, 2_000_000_000] so cross products (up to 4 * 10^18) fit in PHP_INT_MAX (9.22 * 10^18)
            $pA = (abs($lcg()) % 2000000000) + 1;
            $cB = (abs($lcg()) % 2000000000) + 1;
            $pB = (abs($lcg()) % 2000000000) + 1;
            $cA = (abs($lcg()) % 2000000000) + 1;

            $roiA = new Roi(profitCopper: $pA, acquisitionCostCopper: $cA);
            $roiB = new Roi(profitCopper: $pB, acquisitionCostCopper: $cB);

            // Safe reference cross product
            $expectedSign = ($pA * $cB) <=> ($pB * $cA);
            $actual = $roiA->compareTo($roiB);

            self::assertSame($expectedSign, $actual, "Mismatch at iteration {$i}: ({$pA}/{$cA}) vs ({$pB}/{$cB})");

            // Antisymmetry
            self::assertSame(-$actual, $roiB->compareTo($roiA));
        }
    }

    /**
     * Test reflexivity, antisymmetry, and equivalent fraction comparisons.
     */
    public function testCompareToInvariantsAndEquivalentFractions(): void
    {
        $r1 = new Roi(profitCopper: 100, acquisitionCostCopper: 300);
        self::assertSame(0, $r1->compareTo($r1)); // reflexivity

        // Equivalent fractions
        $half1 = new Roi(profitCopper: 1, acquisitionCostCopper: 2);
        $half2 = new Roi(profitCopper: 2, acquisitionCostCopper: 4);
        $half3 = new Roi(profitCopper: 500000, acquisitionCostCopper: 1000000);
        self::assertSame(0, $half1->compareTo($half2));
        self::assertSame(0, $half2->compareTo($half1));
        self::assertSame(0, $half1->compareTo($half3));

        $seventh1 = new Roi(profitCopper: 3, acquisitionCostCopper: 7);
        $seventh2 = new Roi(profitCopper: 6, acquisitionCostCopper: 14);
        self::assertSame(0, $seventh1->compareTo($seventh2));
        self::assertSame(0, $seventh2->compareTo($seventh1));
    }

    /**
     * Explicit near-PHP_INT_MAX ordering cases where cross multiplication would overflow natively.
     */
    public function testCompareToNearPhpIntMaxRatios(): void
    {
        $oneOverOne = new Roi(profitCopper: 1, acquisitionCostCopper: 1);

        // (PHP_INT_MAX - 1) / PHP_INT_MAX < 1/1
        $lessThanOne = new Roi(profitCopper: \PHP_INT_MAX - 1, acquisitionCostCopper: \PHP_INT_MAX);
        self::assertSame(-1, $lessThanOne->compareTo($oneOverOne));
        self::assertSame(1, $oneOverOne->compareTo($lessThanOne));

        // PHP_INT_MAX / PHP_INT_MAX == 1/1
        $equalOne = new Roi(profitCopper: \PHP_INT_MAX, acquisitionCostCopper: \PHP_INT_MAX);
        self::assertSame(0, $equalOne->compareTo($oneOverOne));
        self::assertSame(0, $oneOverOne->compareTo($equalOne));

        // PHP_INT_MAX / (PHP_INT_MAX - 1) > 1/1
        $greaterThanOne = new Roi(profitCopper: \PHP_INT_MAX, acquisitionCostCopper: \PHP_INT_MAX - 1);
        self::assertSame(1, $greaterThanOne->compareTo($oneOverOne));
        self::assertSame(-1, $oneOverOne->compareTo($greaterThanOne));
    }
}

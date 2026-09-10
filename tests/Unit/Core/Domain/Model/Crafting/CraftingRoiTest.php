<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\Crafting;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Math\ExactFraction;
use UncannyWoW\Core\Domain\Model\Crafting\CraftingRoi;

#[CoversClass(CraftingRoi::class)]
final class CraftingRoiTest extends TestCase
{
    public function testPositiveRoiToBasisPoints(): void
    {
        // 1/3 -> 3333 bps (signed truncation toward zero)
        $roi = new CraftingRoi(ExactFraction::of(1, 3));
        $this->assertSame(3333, $roi->toBasisPoints());

        // 1/1 -> 10000 bps
        $roi1 = new CraftingRoi(ExactFraction::of(1, 1));
        $this->assertSame(10000, $roi1->toBasisPoints());

        // 1/2 -> 5000 bps
        $roiHalf = new CraftingRoi(ExactFraction::of(1, 2));
        $this->assertSame(5000, $roiHalf->toBasisPoints());
    }

    public function testNegativeRoiToBasisPoints(): void
    {
        // -1/3 -> -3333 bps
        $roi = new CraftingRoi(ExactFraction::of(-1, 3));
        $this->assertSame(-3333, $roi->toBasisPoints());

        // -1/2 -> -5000 bps
        $roiHalf = new CraftingRoi(ExactFraction::of(-1, 2));
        $this->assertSame(-5000, $roiHalf->toBasisPoints());
    }

    public function testZeroRoi(): void
    {
        $roi = new CraftingRoi(ExactFraction::zero());
        $this->assertSame(0, $roi->toBasisPoints());
    }

    public function testNearPhpIntMaxRatioWithoutFalseIntermediateOverflow(): void
    {
        // Numerator and denominator near PHP_INT_MAX, ratio approximately 1
        // (PHP_INT_MAX - 1000) / PHP_INT_MAX
        // Naive (numerator * 10000) would severely overflow 64-bit integer.
        // mulDivFloor handles magnitude directly.
        $num = \PHP_INT_MAX - 1000;
        $den = \PHP_INT_MAX;

        $roi = new CraftingRoi(ExactFraction::of($num, $den));
        // Ratio is 0.9999999999999999... -> 9999 bps
        $this->assertSame(9999, $roi->toBasisPoints());

        // Negative counterpart
        $negRoi = new CraftingRoi(ExactFraction::of(-$num, $den));
        $this->assertSame(-9999, $negRoi->toBasisPoints());
    }

    public function testFromNetProfitAndCost(): void
    {
        $roi = CraftingRoi::fromNetProfitAndCost(
            ExactFraction::of(250, 1),
            ExactFraction::of(1000, 1),
        );
        $this->assertSame(2500, $roi->toBasisPoints());
    }

    public function testRejectsZeroOrNegativeCostDenominator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('strictly positive material cost denominator');
        CraftingRoi::fromNetProfitAndCost(
            ExactFraction::of(100, 1),
            ExactFraction::zero(),
        );
    }
}

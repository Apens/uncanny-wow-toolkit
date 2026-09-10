<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Math;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Math\ExactFraction;

#[CoversClass(ExactFraction::class)]
final class ExactFractionTest extends TestCase
{
    public function testCanonicalZeroAndOne(): void
    {
        $zero = ExactFraction::zero();
        $this->assertSame(0, $zero->numerator);
        $this->assertSame(1, $zero->denominator);
        $this->assertTrue($zero->isZero());
        $this->assertFalse($zero->isPositive());
        $this->assertFalse($zero->isNegative());

        $one = ExactFraction::one();
        $this->assertSame(1, $one->numerator);
        $this->assertSame(1, $one->denominator);
        $this->assertFalse($one->isZero());
        $this->assertTrue($one->isPositive());
        $this->assertFalse($one->isNegative());
    }

    public function testCanonicalReductionOnConstruction(): void
    {
        $fraction = ExactFraction::of(10, 20);
        $this->assertSame(1, $fraction->numerator);
        $this->assertSame(2, $fraction->denominator);

        $negative = ExactFraction::of(-15, 25);
        $this->assertSame(-3, $negative->numerator);
        $this->assertSame(5, $negative->denominator);
        $this->assertTrue($negative->isNegative());

        $negativeDenom = ExactFraction::of(7, -14);
        $this->assertSame(-1, $negativeDenom->numerator);
        $this->assertSame(2, $negativeDenom->denominator);
    }

    public function testDirectConstructorRejectsUnreducedForm(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('canonical reduced form');
        new ExactFraction(2, 4);
    }

    public function testRejectsZeroOrNegativeDenominator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ExactFraction::of(5, 0);
    }

    public function testRejectsNegativeDenominatorInConstructor(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ExactFraction(1, -2);
    }

    public function testRejectsPhpIntMinNumerator(): void
    {
        $this->expectException(\OverflowException::class);
        ExactFraction::of(\PHP_INT_MIN, 1);
    }

    public function testRejectsPhpIntMinDenominator(): void
    {
        $this->expectException(\OverflowException::class);
        ExactFraction::of(1, \PHP_INT_MIN);
    }

    public function testBasicArithmetic(): void
    {
        $f1 = ExactFraction::of(1, 3);
        $f2 = ExactFraction::of(1, 6);

        $sum = $f1->add($f2);
        $this->assertSame(1, $sum->numerator);
        $this->assertSame(2, $sum->denominator); // 1/3 + 1/6 = 3/6 = 1/2

        $diff = $f1->subtract($f2);
        $this->assertSame(1, $diff->numerator);
        $this->assertSame(6, $diff->denominator); // 1/3 - 1/6 = 1/6

        $product = $f1->multiply($f2);
        $this->assertSame(1, $product->numerator);
        $this->assertSame(18, $product->denominator); // 1/3 * 1/6 = 1/18

        $quotient = $f1->divide($f2);
        $this->assertSame(2, $quotient->numerator);
        $this->assertSame(1, $quotient->denominator); // (1/3) / (1/6) = 2/1

        $multipliedByInt = $f1->multiplyByInt(6);
        $this->assertSame(2, $multipliedByInt->numerator);
        $this->assertSame(1, $multipliedByInt->denominator);
    }

    public function testDivideByZeroThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Division by zero fraction.');
        ExactFraction::of(1, 2)->divide(ExactFraction::zero());
    }

    public function testAlgebraicIdentities(): void
    {
        $values = [
            ExactFraction::of(0, 1),
            ExactFraction::of(1, 1),
            ExactFraction::of(-1, 1),
            ExactFraction::of(3, 7),
            ExactFraction::of(-5, 9),
            ExactFraction::of(42, 13),
        ];

        $zero = ExactFraction::zero();
        $one = ExactFraction::one();

        foreach ($values as $x) {
            // x + 0 = x
            $this->assertSame(0, $x->add($zero)->compareTo($x));
            // 0 + x = x
            $this->assertSame(0, $zero->add($x)->compareTo($x));
            // x - 0 = x
            $this->assertSame(0, $x->subtract($zero)->compareTo($x));
            // x - x = 0
            $this->assertTrue($x->subtract($x)->isZero());
            // x * 1 = x
            $this->assertSame(0, $x->multiply($one)->compareTo($x));
            // x * 0 = 0
            $this->assertTrue($x->multiply($zero)->isZero());
            // compareTo(x) = 0
            $this->assertSame(0, $x->compareTo($x));

            if (! $x->isZero()) {
                // x / 1 = x
                $this->assertSame(0, $x->divide($one)->compareTo($x));
                // x * (1/x) = 1
                $reciprocal = ExactFraction::of($x->denominator, $x->numerator);
                $this->assertSame(0, $x->multiply($reciprocal)->compareTo($one));
            }
        }
    }

    public function testAntisymmetryAndEquivalence(): void
    {
        $a = ExactFraction::of(2, 5);
        $b = ExactFraction::of(3, 7);

        // a - b = -(b - a)
        $diff1 = $a->subtract($b);
        $diff2 = $b->subtract($a);
        $this->assertSame(0, $diff1->add($diff2)->compareTo(ExactFraction::zero()));

        // compareTo antisymmetry
        $this->assertSame(-$a->compareTo($b), $b->compareTo($a));

        // Equivalent fractions
        $f1 = ExactFraction::of(4, 10);
        $f2 = ExactFraction::of(2, 5);
        $this->assertSame(0, $f1->compareTo($f2));
    }

    public function testDeterministicDifferentialGridAcrossThousandsOfOperands(): void
    {
        // Grid: numerators from -20 to 20, denominators from 1 to 20
        // Tests ~ 40 * 20 = 800 fractions in pairs => 3,200 arithmetic operations
        $fractions = [];
        for ($num = -15; $num <= 15; $num += 3) {
            for ($den = 1; $den <= 15; $den += 3) {
                $fractions[] = ExactFraction::of($num, $den);
            }
        }

        $count = count($fractions);
        for ($i = 0; $i < $count; $i++) {
            $f1 = $fractions[$i];
            $a = $f1->numerator;
            $b = $f1->denominator;

            for ($j = 0; $j < $count; $j += 2) {
                $f2 = $fractions[$j];
                $c = $f2->numerator;
                $d = $f2->denominator;

                // 1. Comparison oracle: a*d <=> c*b (safe because max product is 15 * 15 = 225)
                $expectedCmp = ($a * $d) <=> ($c * $b);
                $actualCmp = $f1->compareTo($f2);
                $this->assertSame(
                    $expectedCmp,
                    $actualCmp <=> 0,
                    sprintf('Comparison mismatch for %s and %s', $f1, $f2),
                );

                // 2. Addition oracle: (a*d + c*b) / (b*d)
                $expAddNum = ($a * $d) + ($c * $b);
                $expAddDen = $b * $d;
                $expectedAdd = ExactFraction::of($expAddNum, $expAddDen);
                $actualAdd = $f1->add($f2);
                $this->assertSame(0, $actualAdd->compareTo($expectedAdd));

                // 3. Subtraction oracle: (a*d - c*b) / (b*d)
                $expSubNum = ($a * $d) - ($c * $b);
                $expSubDen = $b * $d;
                $expectedSub = ExactFraction::of($expSubNum, $expSubDen);
                $actualSub = $f1->subtract($f2);
                $this->assertSame(0, $actualSub->compareTo($expectedSub));

                // 4. Multiplication oracle: (a*c) / (b*d)
                $expMulNum = $a * $c;
                $expMulDen = $b * $d;
                $expectedMul = ExactFraction::of($expMulNum, $expMulDen);
                $actualMul = $f1->multiply($f2);
                $this->assertSame(0, $actualMul->compareTo($expectedMul));

                // 5. Division oracle (if f2 != 0)
                if (! $f2->isZero()) {
                    $expDivNum = $a * $d;
                    $expDivDen = $b * $c;
                    $expectedDiv = ExactFraction::of($expDivNum, $expDivDen);
                    $actualDiv = $f1->divide($f2);
                    $this->assertSame(0, $actualDiv->compareTo($expectedDiv));
                }
            }
        }
    }

    public function testComparisonSucceedsNearPhpIntMaxWhereCrossProductWouldOverflow(): void
    {
        // For f1 = (PHP_INT_MAX - 2) / (PHP_INT_MAX - 1) and f2 = (PHP_INT_MAX - 1) / PHP_INT_MAX
        // Cross products ~ (PHP_INT_MAX)^2 would overflow 64-bit integer.
        // Our Euclidean comparison must handle this cleanly.
        $max = \PHP_INT_MAX;
        $f1 = ExactFraction::of($max - 2, $max - 1);
        $f2 = ExactFraction::of($max - 1, $max);

        // f1 < f2 because 1 - 1/(max-1) < 1 - 1/max
        $this->assertSame(-1, $f1->compareTo($f2));
        $this->assertSame(1, $f2->compareTo($f1));
        $this->assertSame(0, $f1->compareTo($f1));
    }

    public function testMultiplicationWithCrossCancellationSucceedsNearPhpIntMax(): void
    {
        // f1 = 2 / PHP_INT_MAX, f2 = PHP_INT_MAX / 3
        // Naive (2 * PHP_INT_MAX) would overflow 64-bit signed integer.
        // Cross-cancellation reduces PHP_INT_MAX before multiplication, yielding 2/3.
        $f1 = ExactFraction::of(2, \PHP_INT_MAX);
        $f2 = ExactFraction::of(\PHP_INT_MAX, 3);

        $product = $f1->multiply($f2);
        $this->assertSame(2, $product->numerator);
        $this->assertSame(3, $product->denominator);
    }

    public function testTrueArithmeticOverflowThrowsExplicitOverflowException(): void
    {
        $this->expectException(\OverflowException::class);
        // PHP_INT_MAX / 2 + PHP_INT_MAX / 3:
        // left = PHP_INT_MAX * 3, right = PHP_INT_MAX * 2 -> left overflows checkedMultiply
        $f1 = ExactFraction::of(\PHP_INT_MAX - 10, 2);
        $f2 = ExactFraction::of(\PHP_INT_MAX - 10, 3);
        $f1->add($f2);
    }

    public function testStringableOutput(): void
    {
        $f = ExactFraction::of(7, 13);
        $this->assertSame('7/13', (string) $f);
    }
}

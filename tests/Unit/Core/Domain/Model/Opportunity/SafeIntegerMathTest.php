<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\Opportunity;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Model\Opportunity\SafeIntegerMath;

final class SafeIntegerMathTest extends TestCase
{
    public function testCheckedAddNormal(): void
    {
        self::assertSame(300, SafeIntegerMath::checkedAdd(100, 200));
        self::assertSame(100, SafeIntegerMath::checkedAdd(100, 0));
        self::assertSame(-50, SafeIntegerMath::checkedAdd(-100, 50));
    }

    public function testCheckedAddOverflowThrows(): void
    {
        $this->expectException(\OverflowException::class);
        SafeIntegerMath::checkedAdd(\PHP_INT_MAX, 1);
    }

    public function testCheckedAddUnderflowThrows(): void
    {
        $this->expectException(\OverflowException::class);
        SafeIntegerMath::checkedAdd(\PHP_INT_MIN, -1);
    }

    public function testCheckedSubtractNormal(): void
    {
        self::assertSame(50, SafeIntegerMath::checkedSubtract(150, 100));
        self::assertSame(-50, SafeIntegerMath::checkedSubtract(100, 150));
    }

    public function testCheckedSubtractOverflowThrows(): void
    {
        $this->expectException(\OverflowException::class);
        SafeIntegerMath::checkedSubtract(\PHP_INT_MAX, -1);
    }

    public function testCheckedSubtractUnderflowThrows(): void
    {
        $this->expectException(\OverflowException::class);
        SafeIntegerMath::checkedSubtract(\PHP_INT_MIN, 1);
    }

    public function testCheckedMultiplyNormalAndZero(): void
    {
        self::assertSame(0, SafeIntegerMath::checkedMultiply(0, 100));
        self::assertSame(0, SafeIntegerMath::checkedMultiply(100, 0));
        self::assertSame(5000, SafeIntegerMath::checkedMultiply(50, 100));
        self::assertSame(-5000, SafeIntegerMath::checkedMultiply(-50, 100));
    }

    public function testCheckedMultiplyOverflowThrows(): void
    {
        $this->expectException(\OverflowException::class);
        SafeIntegerMath::checkedMultiply(\PHP_INT_MAX, 2);
    }

    public function testMulDivFloorNormalAndZero(): void
    {
        self::assertSame(0, SafeIntegerMath::mulDivFloor(0, 500, 10000));
        self::assertSame(0, SafeIntegerMath::mulDivFloor(100, 0, 10000));
        self::assertSame(5, SafeIntegerMath::mulDivFloor(100, 500, 10000)); // 50000 / 10000 = 5
        self::assertSame(5, SafeIntegerMath::mulDivFloor(101, 500, 10000)); // 50500 / 10000 = 5.05 -> floor 5
    }

    public function testMulDivCeilNormalAndZero(): void
    {
        self::assertSame(0, SafeIntegerMath::mulDivCeil(0, 500, 10000));
        self::assertSame(0, SafeIntegerMath::mulDivCeil(100, 0, 10000));
        self::assertSame(5, SafeIntegerMath::mulDivCeil(100, 500, 10000)); // 50000 / 10000 = 5 (exact)
        self::assertSame(6, SafeIntegerMath::mulDivCeil(101, 500, 10000)); // 50500 / 10000 = 5.05 -> ceil 6
    }

    public function testMulDivValidationOccursBeforeZeroShortCircuiting(): void
    {
        // Divisor 0 rejected even when value is 0
        try {
            SafeIntegerMath::mulDivCeil(0, 500, 0);
            self::fail('Expected InvalidArgumentException for divisor 0');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Divisor must be strictly positive', $e->getMessage());
        }

        try {
            SafeIntegerMath::mulDivFloor(0, 500, 0);
            self::fail('Expected InvalidArgumentException for divisor 0');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Divisor must be strictly positive', $e->getMessage());
        }

        // Negative multiplier rejected even when value is 0
        try {
            SafeIntegerMath::mulDivCeil(0, -1, 10000);
            self::fail('Expected InvalidArgumentException for negative multiplier');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Multiplier must be between 0 and 10000', $e->getMessage());
        }

        // Multiplier > 10000 rejected
        try {
            SafeIntegerMath::mulDivFloor(100, 10001, 10000);
            self::fail('Expected InvalidArgumentException for multiplier > 10000');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Multiplier must be between 0 and 10000', $e->getMessage());
        }

        // Negative value rejected
        try {
            SafeIntegerMath::mulDivFloor(-1, 500, 10000);
            self::fail('Expected InvalidArgumentException for negative value');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Value must be non-negative', $e->getMessage());
        }
    }

    public function testMulDivNearPhpIntMaxWhereNativeMultiplicationWouldOverflow(): void
    {
        // Value close to PHP_INT_MAX
        // e.g. PHP_INT_MAX - 1000
        // value * 500 would overflow PHP_INT_MAX natively
        $value = \PHP_INT_MAX - 1000;
        $multiplier = 500; // 5%
        $divisor = 10000;

        // Native ($value * $multiplier) would overflow to float or error
        // But the result (value / 20) easily fits in 64-bit int
        $expectedFloor = intdiv($value, 20);
        $floorResult = SafeIntegerMath::mulDivFloor($value, $multiplier, $divisor);
        self::assertSame($expectedFloor, $floorResult);

        // (PHP_INT_MAX - 1000) % 20 is non-zero (it is 7 on 64-bit PHP), so ceiling is expectedFloor + 1
        $expectedCeil = $expectedFloor + 1;
        $ceilResult = SafeIntegerMath::mulDivCeil($value, $multiplier, $divisor);
        self::assertSame($expectedCeil, $ceilResult);
    }

    public function testMulDivMaxMultiplier10000(): void
    {
        $value = 123456789;
        self::assertSame($value, SafeIntegerMath::mulDivFloor($value, 10000, 10000));
        self::assertSame($value, SafeIntegerMath::mulDivCeil($value, 10000, 10000));
    }

    public function testMulDivTrueMathematicalOverflowThrows(): void
    {
        // If final result itself exceeds PHP_INT_MAX
        // (e.g. value = PHP_INT_MAX, multiplier = 2, divisor = 1)
        $this->expectException(\OverflowException::class);
        SafeIntegerMath::mulDivFloor(\PHP_INT_MAX, 2, 1);
    }

    public function testMulDivCeilTrueMathematicalOverflowThrows(): void
    {
        $this->expectException(\OverflowException::class);
        SafeIntegerMath::mulDivCeil(\PHP_INT_MAX, 2, 1);
    }

    /**
     * Deterministic differential test against simple native integer arithmetic
     * across thousands of combinations where native ($value * $multiplier) cannot overflow.
     */
    public function testMulDivDeterministicDifferentialAgainstTrivialReference(): void
    {
        // Seed an LCG deterministically
        $seed = 424242;
        $lcg = static function () use (&$seed): int {
            $seed = (1103515245 * $seed + 12345) & 0x7FFFFFFF;
            return $seed;
        };

        // 5000 test cases
        for ($i = 0; $i < 5000; $i++) {
            $valRand = ($lcg() << 15) ^ $lcg();
            $value = abs($valRand) % 900000000000;
            $multiplier = abs($lcg()) % 10001;
            $divisor = (abs($lcg()) % 2000000) + 1;

            // Reference implementation using safe native multiplication
            $product = $value * $multiplier;
            $expectedFloor = intdiv($product, $divisor);
            $expectedCeil = $this->referenceCeil($product, $divisor);

            $actualFloor = SafeIntegerMath::mulDivFloor($value, $multiplier, $divisor);
            $actualCeil = SafeIntegerMath::mulDivCeil($value, $multiplier, $divisor);

            self::assertSame($expectedFloor, $actualFloor, "Mismatch in floor at iteration {$i}: ({$value} * {$multiplier}) / {$divisor}");
            self::assertSame($expectedCeil, $actualCeil, "Mismatch in ceil at iteration {$i}: ({$value} * {$multiplier}) / {$divisor}");
        }
    }

    private function referenceCeil(int $product, int $divisor): int
    {
        if ($product === 0) {
            return 0;
        }

        return intdiv($product - 1, $divisor) + 1;
    }

    /**
     * Explicit cases where native multiplication WOULD overflow PHP_INT_MAX,
     * but the expected mathematical result is known independently and fits in int.
     */
    public function testMulDivNearPhpIntMaxIdentities(): void
    {
        self::assertSame(\PHP_INT_MAX, SafeIntegerMath::mulDivFloor(\PHP_INT_MAX, 500, 500));
        self::assertSame(\PHP_INT_MAX, SafeIntegerMath::mulDivCeil(\PHP_INT_MAX, 500, 500));

        self::assertSame(\PHP_INT_MAX, SafeIntegerMath::mulDivFloor(\PHP_INT_MAX, 9999, 9999));
        self::assertSame(\PHP_INT_MAX, SafeIntegerMath::mulDivCeil(\PHP_INT_MAX, 9999, 9999));

        self::assertSame(1, SafeIntegerMath::mulDivFloor(\PHP_INT_MAX, 1, \PHP_INT_MAX));
        self::assertSame(1, SafeIntegerMath::mulDivCeil(\PHP_INT_MAX, 1, \PHP_INT_MAX));
    }
}

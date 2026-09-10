<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Opportunity;

/**
 * Overflow-safe integer arithmetic primitives for currency and ratio calculations.
 *
 * @internal
 */
final class SafeIntegerMath
{
    public static function checkedAdd(int $a, int $b): int
    {
        if ($b > 0 && $a > \PHP_INT_MAX - $b) {
            throw new \OverflowException(sprintf('Integer overflow during addition: %d + %d.', $a, $b));
        }
        if ($b < 0 && $a < \PHP_INT_MIN - $b) {
            throw new \OverflowException(sprintf('Integer underflow during addition: %d + %d.', $a, $b));
        }

        return $a + $b;
    }

    public static function checkedSubtract(int $a, int $b): int
    {
        if ($b < 0 && $a > \PHP_INT_MAX + $b) {
            throw new \OverflowException(sprintf('Integer overflow during subtraction: %d - %d.', $a, $b));
        }
        if ($b > 0 && $a < \PHP_INT_MIN + $b) {
            throw new \OverflowException(sprintf('Integer underflow during subtraction: %d - %d.', $a, $b));
        }

        return $a - $b;
    }

    public static function checkedMultiply(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }

        if ($a > 0) {
            if ($b > 0 && $a > intdiv(\PHP_INT_MAX, $b)) {
                throw new \OverflowException(sprintf('Integer overflow during multiplication: %d * %d.', $a, $b));
            }
            if ($b < 0 && $b < intdiv(\PHP_INT_MIN, $a)) {
                throw new \OverflowException(sprintf('Integer underflow during multiplication: %d * %d.', $a, $b));
            }
        } else {
            if ($b > 0 && $a < intdiv(\PHP_INT_MIN, $b)) {
                throw new \OverflowException(sprintf('Integer underflow during multiplication: %d * %d.', $a, $b));
            }
            if ($b < 0 && $a < intdiv(\PHP_INT_MAX, $b)) {
                throw new \OverflowException(sprintf('Integer overflow during multiplication: %d * %d.', $a, $b));
            }
        }

        return $a * $b;
    }

    /**
     * Exact floor division of (value * multiplier) / divisor for non-negative values and small multipliers.
     *
     * Preconditions: value >= 0, 0 <= multiplier <= 10000, divisor > 0.
     */
    public static function mulDivFloor(int $value, int $multiplier, int $divisor): int
    {
        self::validateMulDivInputs($value, $multiplier, $divisor);

        if ($value === 0 || $multiplier === 0) {
            return 0;
        }

        $q = intdiv($value, $divisor);
        $r = $value % $divisor;

        $whole = self::checkedMultiply($q, $multiplier);
        [$fracQ, ] = self::mulDivModSmallScale($r, $multiplier, $divisor);

        return self::checkedAdd($whole, $fracQ);
    }

    /**
     * Exact ceiling division of (value * multiplier) / divisor for non-negative values and small multipliers.
     *
     * Preconditions: value >= 0, 0 <= multiplier <= 10000, divisor > 0.
     */
    public static function mulDivCeil(int $value, int $multiplier, int $divisor): int
    {
        self::validateMulDivInputs($value, $multiplier, $divisor);

        if ($value === 0 || $multiplier === 0) {
            return 0;
        }

        $q = intdiv($value, $divisor);
        $r = $value % $divisor;

        $whole = self::checkedMultiply($q, $multiplier);
        [$fracQ, $exactRem] = self::mulDivModSmallScale($r, $multiplier, $divisor);

        $floorResult = self::checkedAdd($whole, $fracQ);

        return $exactRem > 0 ? self::checkedAdd($floorResult, 1) : $floorResult;
    }

    private static function validateMulDivInputs(int $value, int $multiplier, int $divisor): void
    {
        if ($divisor <= 0) {
            throw new \InvalidArgumentException(sprintf('Divisor must be strictly positive, got %d.', $divisor));
        }

        if ($value < 0) {
            throw new \InvalidArgumentException(sprintf('Value must be non-negative, got %d.', $value));
        }

        if ($multiplier < 0 || $multiplier > 10000) {
            throw new \InvalidArgumentException(sprintf('Multiplier must be between 0 and 10000, got %d.', $multiplier));
        }
    }

    /**
     * Computes intdiv(r * multiplier, divisor) and (r * multiplier) % divisor
     * safely without intermediate overflow using binary double-and-add.
     *
     * Preconditions: 0 <= remainder < divisor, 0 <= multiplier <= 10000, divisor > 0.
     *
     * @return array{0: int, 1: int} [floorQuotient, exactRemainder]
     */
    private static function mulDivModSmallScale(int $remainder, int $multiplier, int $divisor): array
    {
        $quotient = 0;
        $rem = 0;

        $curQ = 0;
        $curR = $remainder;

        while ($multiplier > 0) {
            if (($multiplier & 1) === 1) {
                $quotient = self::checkedAdd($quotient, $curQ);
                if ($rem >= $divisor - $curR) {
                    $rem -= ($divisor - $curR);
                    $quotient = self::checkedAdd($quotient, 1);
                } else {
                    $rem += $curR;
                }
            }

            $multiplier >>= 1;
            if ($multiplier > 0) {
                $curQ = self::checkedAdd($curQ, $curQ);
                if ($curR >= $divisor - $curR) {
                    $curR -= ($divisor - $curR);
                    $curQ = self::checkedAdd($curQ, 1);
                } else {
                    $curR += $curR;
                }
            }
        }

        return [$quotient, $rem];
    }
}

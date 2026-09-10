<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Math;

/**
 * Small, auditable, exact signed rational arithmetic in the signed 64-bit integer domain.
 *
 * Invariants:
 * - Numerator is within [PHP_INT_MIN + 1, PHP_INT_MAX]
 * - Denominator is strictly positive within [1, PHP_INT_MAX]
 * - Fractions are canonically reduced by GCD: gcd(|numerator|, denominator) === 1
 * - Canonical zero is uniquely represented as 0/1
 * - Arithmetic overflow throws an explicit \OverflowException
 * - Division by zero throws \InvalidArgumentException
 *
 * No floats, no BCMath, no GMP, and no arbitrary-precision dependencies.
 */
final readonly class ExactFraction implements \Stringable
{
    public function __construct(
        public int $numerator,
        public int $denominator,
    ) {
        if ($this->numerator === \PHP_INT_MIN) {
            throw new \OverflowException('ExactFraction numerator cannot be PHP_INT_MIN as its magnitude is not representable.');
        }

        if ($this->denominator <= 0) {
            throw new \InvalidArgumentException(sprintf('ExactFraction denominator must be strictly positive, got %d.', $this->denominator));
        }

        $gcd = self::gcd(abs($this->numerator), $this->denominator);
        if ($gcd !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'ExactFraction must be initialized in canonical reduced form (gcd(%d, %d) = %d). Use ExactFraction::of() instead.',
                $this->numerator,
                $this->denominator,
                $gcd,
            ));
        }
    }

    public static function of(int $numerator, int $denominator = 1): self
    {
        if ($numerator === \PHP_INT_MIN) {
            throw new \OverflowException('ExactFraction numerator cannot be PHP_INT_MIN.');
        }

        if ($denominator === 0) {
            throw new \InvalidArgumentException('ExactFraction denominator cannot be zero.');
        }

        if ($denominator < 0) {
            if ($denominator === \PHP_INT_MIN) {
                throw new \OverflowException('ExactFraction denominator cannot be PHP_INT_MIN.');
            }
            $numerator = -$numerator;
            $denominator = -$denominator;
        }

        if ($numerator === 0) {
            return self::zero();
        }

        $g = self::gcd(abs($numerator), $denominator);

        return new self(
            numerator: intdiv($numerator, $g),
            denominator: intdiv($denominator, $g),
        );
    }

    public static function zero(): self
    {
        return new self(0, 1);
    }

    public static function one(): self
    {
        return new self(1, 1);
    }

    public function isZero(): bool
    {
        return $this->numerator === 0;
    }

    public function isPositive(): bool
    {
        return $this->numerator > 0;
    }

    public function isNegative(): bool
    {
        return $this->numerator < 0;
    }

    public function add(self $other): self
    {
        if ($this->isZero()) {
            return $other;
        }
        if ($other->isZero()) {
            return $this;
        }

        return $this->combineAddSub($other, isAdd: true);
    }

    public function subtract(self $other): self
    {
        if ($other->isZero()) {
            return $this;
        }
        if ($this->isZero()) {
            return self::of(-$other->numerator, $other->denominator);
        }

        return $this->combineAddSub($other, isAdd: false);
    }

    public function multiply(self $other): self
    {
        if ($this->isZero() || $other->isZero()) {
            return self::zero();
        }

        // Cross-cancel before multiplication:
        // g1 = gcd(|a|, d), g2 = gcd(|c|, b)
        $g1 = self::gcd(abs($this->numerator), $other->denominator);
        $g2 = self::gcd(abs($other->numerator), $this->denominator);

        $aRed = intdiv($this->numerator, $g1);
        $dRed = intdiv($other->denominator, $g1);

        $cRed = intdiv($other->numerator, $g2);
        $bRed = intdiv($this->denominator, $g2);

        $num = self::checkedMultiply($aRed, $cRed);
        $den = self::checkedMultiply($bRed, $dRed);

        // Factors are already cross-cancelled with each other's denominators.
        return new self($num, $den);
    }

    public function multiplyByInt(int $multiplier): self
    {
        if ($multiplier === 0 || $this->isZero()) {
            return self::zero();
        }
        if ($multiplier === 1) {
            return $this;
        }

        return $this->multiply(self::of($multiplier, 1));
    }

    public function divide(self $other): self
    {
        if ($other->isZero()) {
            throw new \InvalidArgumentException('Division by zero fraction.');
        }
        if ($this->isZero()) {
            return self::zero();
        }

        // Invert other: (c/d) becomes (sgn(c)*d / |c|)
        $recipNum = $other->numerator < 0 ? -$other->denominator : $other->denominator;
        $recipDen = abs($other->numerator);

        $reciprocal = new self($recipNum, $recipDen);

        return $this->multiply($reciprocal);
    }

    /**
     * Overflow-safe exact signed comparison without cross-multiplication.
     *
     * Returns:
     * - negative integer if $this < $other
     * - 0 if $this == $other
     * - positive integer if $this > $other
     */
    public function compareTo(self $other): int
    {
        // 1. Compare signs
        $signThis = $this->numerator <=> 0;
        $signOther = $other->numerator <=> 0;

        if ($signThis !== $signOther) {
            return $signThis <=> $signOther;
        }

        if ($signThis === 0) {
            return 0; // Both are zero
        }

        // Both are non-zero with identical sign.
        // If both are negative, compare magnitudes |a|/b vs |c|/d and reverse result.
        $reverse = ($signThis < 0);

        $p1 = abs($this->numerator);
        $q1 = $this->denominator;
        $p2 = abs($other->numerator);
        $q2 = $other->denominator;

        $magCompare = self::comparePositiveMagnitudes($p1, $q1, $p2, $q2);

        return $reverse ? -$magCompare : $magCompare;
    }

    public function __toString(): string
    {
        return sprintf('%d/%d', $this->numerator, $this->denominator);
    }

    private function combineAddSub(self $other, bool $isAdd): self
    {
        $a = $this->numerator;
        $b = $this->denominator;
        $c = $other->numerator;
        $d = $other->denominator;

        $g = self::gcd($b, $d);
        $lFactor = intdiv($d, $g);
        $rFactor = intdiv($b, $g);

        $left = self::checkedMultiply($a, $lFactor);
        $right = self::checkedMultiply($c, $rFactor);

        $num = $isAdd ? self::checkedAdd($left, $right) : self::checkedSubtract($left, $right);
        if ($num === 0) {
            return self::zero();
        }

        $den = self::checkedMultiply(intdiv($b, $g), $d);

        $finalG = self::gcd(abs($num), $den);

        return new self(intdiv($num, $finalG), intdiv($den, $finalG));
    }

    /**
     * Compare positive fractions p1/q1 and p2/q2 without cross-product multiplication
     * using Euclidean continued-fraction quotient/remainder loop.
     */
    private static function comparePositiveMagnitudes(int $p1, int $q1, int $p2, int $q2): int
    {
        $rev = false;
        while (true) {
            $quot1 = intdiv($p1, $q1);
            $quot2 = intdiv($p2, $q2);

            if ($quot1 !== $quot2) {
                return ($quot1 > $quot2 xor $rev) ? 1 : -1;
            }

            $rem1 = $p1 % $q1;
            $rem2 = $p2 % $q2;

            if ($rem1 === 0 && $rem2 === 0) {
                return 0;
            }
            if ($rem1 === 0) {
                return $rev ? 1 : -1;
            }
            if ($rem2 === 0) {
                return $rev ? -1 : 1;
            }

            $p1 = $q1;
            $q1 = $rem1;
            $p2 = $q2;
            $q2 = $rem2;
            $rev = ! $rev;
        }
    }

    public static function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            $t = $b;
            $b = $a % $b;
            $a = $t;
        }

        return abs($a);
    }

    private static function checkedAdd(int $a, int $b): int
    {
        if ($b > 0 && $a > \PHP_INT_MAX - $b) {
            throw new \OverflowException(sprintf('Integer overflow during fraction addition: %d + %d.', $a, $b));
        }
        if ($b < 0 && $a < \PHP_INT_MIN - $b) {
            throw new \OverflowException(sprintf('Integer underflow during fraction addition: %d + %d.', $a, $b));
        }

        return $a + $b;
    }

    private static function checkedSubtract(int $a, int $b): int
    {
        if ($b < 0 && $a > \PHP_INT_MAX + $b) {
            throw new \OverflowException(sprintf('Integer overflow during fraction subtraction: %d - %d.', $a, $b));
        }
        if ($b > 0 && $a < \PHP_INT_MIN + $b) {
            throw new \OverflowException(sprintf('Integer underflow during fraction subtraction: %d - %d.', $a, $b));
        }

        return $a - $b;
    }

    private static function checkedMultiply(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }

        if ($a > 0) {
            if ($b > 0 && $a > intdiv(\PHP_INT_MAX, $b)) {
                throw new \OverflowException(sprintf('Integer overflow during fraction multiplication: %d * %d.', $a, $b));
            }
            if ($b < 0 && $b < intdiv(\PHP_INT_MIN, $a)) {
                throw new \OverflowException(sprintf('Integer underflow during fraction multiplication: %d * %d.', $a, $b));
            }
        } else {
            if ($b > 0 && $a < intdiv(\PHP_INT_MIN, $b)) {
                throw new \OverflowException(sprintf('Integer underflow during fraction multiplication: %d * %d.', $a, $b));
            }
            if ($b < 0 && $a < intdiv(\PHP_INT_MAX, $b)) {
                throw new \OverflowException(sprintf('Integer overflow during fraction multiplication: %d * %d.', $a, $b));
            }
        }

        return $a * $b;
    }
}

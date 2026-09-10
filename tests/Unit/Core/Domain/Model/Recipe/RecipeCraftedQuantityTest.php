<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\Recipe;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Model\Recipe\RecipeCraftedQuantity;

#[CoversClass(RecipeCraftedQuantity::class)]
final class RecipeCraftedQuantityTest extends TestCase
{
    public function testFixedQuantity(): void
    {
        $qty = RecipeCraftedQuantity::fixed(5);

        $this->assertTrue($qty->isKnown());
        $this->assertTrue($qty->isFixed());
        $this->assertFalse($qty->isRange());
        $this->assertSame(5, $qty->minimum);
        $this->assertSame(5, $qty->maximum);
    }

    public function testRangeQuantity(): void
    {
        $qty = RecipeCraftedQuantity::range(2, 4);

        $this->assertTrue($qty->isKnown());
        $this->assertFalse($qty->isFixed());
        $this->assertTrue($qty->isRange());
        $this->assertSame(2, $qty->minimum);
        $this->assertSame(4, $qty->maximum);
    }

    public function testRangeWithEqualMinMaxBecomesFixed(): void
    {
        $qty = RecipeCraftedQuantity::range(3, 3);

        $this->assertTrue($qty->isKnown());
        $this->assertTrue($qty->isFixed());
        $this->assertFalse($qty->isRange());
        $this->assertSame(3, $qty->minimum);
        $this->assertSame(3, $qty->maximum);
    }

    public function testUnknownQuantity(): void
    {
        $qty = RecipeCraftedQuantity::unknown();

        $this->assertFalse($qty->isKnown());
        $this->assertFalse($qty->isFixed());
        $this->assertFalse($qty->isRange());
        $this->assertNull($qty->minimum);
        $this->assertNull($qty->maximum);
    }

    public function testFixedNegativeOrZeroThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RecipeCraftedQuantity::fixed(0);
    }

    public function testRangeNegativeOrZeroMinThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RecipeCraftedQuantity::range(0, 5);
    }

    public function testRangeMinGreaterThanMaxThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RecipeCraftedQuantity::range(5, 2);
    }
}

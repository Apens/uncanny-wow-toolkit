<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\Economy;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Model\Economy\PriceLevel;

final class PriceLevelTest extends TestCase
{
    public function testInstantiationWithValidData(): void
    {
        $level = new PriceLevel(
            priceCopper: 12500,
            quantity: 50,
            listingCount: 3,
        );

        self::assertSame(12500, $level->priceCopper);
        self::assertSame(50, $level->quantity);
        self::assertSame(3, $level->listingCount);

        $reflection = new \ReflectionClass($level);
        self::assertFalse($reflection->hasProperty('unitPriceCopper'));
    }

    public function testNegativePriceThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Price copper must be non-negative, got -1.');

        new PriceLevel(priceCopper: -1, quantity: 10, listingCount: 1);
    }

    public function testZeroQuantityThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be positive, got 0.');

        new PriceLevel(priceCopper: 100, quantity: 0, listingCount: 1);
    }

    public function testZeroListingCountThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Listing count must be positive, got 0.');

        new PriceLevel(priceCopper: 100, quantity: 5, listingCount: 0);
    }
}

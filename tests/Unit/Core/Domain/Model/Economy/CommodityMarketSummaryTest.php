<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\Economy;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketSummary;
use UncannyWoW\Core\Domain\Model\Economy\PriceLevel;

final class CommodityMarketSummaryTest extends TestCase
{
    public function testInstantiationAndGetters(): void
    {
        $levels = [
            new PriceLevel(12500, 100, 2),
            new PriceLevel(12600, 250, 5),
        ];

        $summary = new CommodityMarketSummary(
            itemId: 190381,
            auctionCount: 7,
            totalQuantity: 350,
            lowestUnitPriceCopper: 12500,
            quantityAtLowestPrice: 100,
            highestUnitPriceCopper: 12600,
            priceLevels: $levels,
        );

        self::assertSame(190381, $summary->itemId);
        self::assertSame(7, $summary->auctionCount);
        self::assertSame(350, $summary->totalQuantity);
        self::assertSame(12500, $summary->lowestUnitPriceCopper);
        self::assertSame(100, $summary->quantityAtLowestPrice);
        self::assertSame(12600, $summary->highestUnitPriceCopper);
        self::assertSame($levels, $summary->priceLevels);
        self::assertSame(350, $summary->getQuantityAtPriceLevels());
    }

    public function testInvalidItemIdThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Item ID must be positive, got 0.');

        new CommodityMarketSummary(
            itemId: 0,
            auctionCount: 1,
            totalQuantity: 1,
            lowestUnitPriceCopper: 100,
            quantityAtLowestPrice: 1,
            highestUnitPriceCopper: 100,
        );
    }
}

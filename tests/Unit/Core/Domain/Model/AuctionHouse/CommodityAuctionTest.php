<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\AuctionHouse;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Enum\AuctionTimeLeft;
use UncannyWoW\Core\Domain\Model\AuctionHouse\CommodityAuction;

final class CommodityAuctionTest extends TestCase
{
    public function testCommodityAuctionInstantiationAndProperties(): void
    {
        $auction = new CommodityAuction(
            id: 20000001,
            itemId: 190381,
            quantity: 500,
            unitPriceCopper: 12500,
            timeLeft: AuctionTimeLeft::VERY_LONG,
        );

        self::assertSame(20000001, $auction->id);
        self::assertSame(190381, $auction->itemId);
        self::assertSame(500, $auction->quantity);
        self::assertSame(12500, $auction->unitPriceCopper);
        self::assertSame(AuctionTimeLeft::VERY_LONG, $auction->timeLeft);
    }
}

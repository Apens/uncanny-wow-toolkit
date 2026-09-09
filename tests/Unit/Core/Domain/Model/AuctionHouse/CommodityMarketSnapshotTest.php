<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\AuctionHouse;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Enum\AuctionTimeLeft;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\AuctionHouse\CommodityAuction;
use UncannyWoW\Core\Domain\Model\AuctionHouse\CommodityMarketSnapshot;

final class CommodityMarketSnapshotTest extends TestCase
{
    public function testCommodityMarketSnapshotInstantiationAndProperties(): void
    {
        $auction = new CommodityAuction(
            id: 20000001,
            itemId: 190381,
            quantity: 500,
            unitPriceCopper: 12500,
            timeLeft: AuctionTimeLeft::VERY_LONG,
        );

        $snapshot = new CommodityMarketSnapshot(
            region: Region::EU,
            auctions: [$auction],
        );

        self::assertSame(Region::EU, $snapshot->region);
        $collected = [];
        foreach ($snapshot as $item) {
            $collected[] = $item;
        }

        self::assertCount(1, $collected);
        self::assertSame($auction, $collected[0]);
    }

    public function testCommodityMarketSnapshotWithLazyTraversable(): void
    {
        $auction = new CommodityAuction(
            id: 20000001,
            itemId: 190381,
            quantity: 500,
            unitPriceCopper: 12500,
            timeLeft: AuctionTimeLeft::VERY_LONG,
        );

        $generator = (function () use ($auction): \Generator {
            yield $auction;
        })();

        $snapshot = new CommodityMarketSnapshot(
            region: Region::EU,
            auctions: $generator,
        );

        self::assertSame(Region::EU, $snapshot->region);
        $count = 0;
        foreach ($snapshot as $item) {
            $count++;
            self::assertSame($auction, $item);
        }
        self::assertSame(1, $count);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('This auction snapshot has already been consumed.');

        foreach ($snapshot as $item) {
            // Should not be reached
        }
    }

    public function testCommodityMarketSnapshotArrayIsAlsoSinglePass(): void
    {
        $auction = new CommodityAuction(
            id: 20000001,
            itemId: 190381,
            quantity: 500,
            unitPriceCopper: 12500,
            timeLeft: AuctionTimeLeft::VERY_LONG,
        );

        $snapshot = new CommodityMarketSnapshot(
            region: Region::EU,
            auctions: [$auction],
        );

        foreach ($snapshot as $item) {
            self::assertSame($auction, $item);
        }

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('This auction snapshot has already been consumed.');

        foreach ($snapshot as $item) {
            // Should not be reached
        }
    }
}

<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\AuctionHouse;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Enum\AuctionTimeLeft;
use UncannyWoW\Core\Domain\Model\AuctionHouse\Auction;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionHouseSnapshot;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItem;

final class AuctionHouseSnapshotTest extends TestCase
{
    public function testAuctionHouseSnapshotInstantiationAndProperties(): void
    {
        $auction = new Auction(
            id: 10000001,
            item: new AuctionItem(id: 19019),
            quantity: 1,
            buyoutCopper: 50000000,
            bidCopper: 45000000,
            timeLeft: AuctionTimeLeft::VERY_LONG,
        );

        $snapshot = new AuctionHouseSnapshot(
            connectedRealmId: 1127,
            auctions: [$auction],
        );

        self::assertSame(1127, $snapshot->connectedRealmId);
        $collected = [];
        foreach ($snapshot as $item) {
            $collected[] = $item;
        }

        self::assertCount(1, $collected);
        self::assertSame($auction, $collected[0]);
    }

    public function testAuctionHouseSnapshotWithLazyTraversable(): void
    {
        $auction = new Auction(
            id: 10000001,
            item: new AuctionItem(id: 19019),
            quantity: 1,
            buyoutCopper: 50000000,
            bidCopper: 45000000,
            timeLeft: AuctionTimeLeft::VERY_LONG,
        );

        $generator = (function () use ($auction): \Generator {
            yield $auction;
        })();

        $snapshot = new AuctionHouseSnapshot(
            connectedRealmId: 1127,
            auctions: $generator,
        );

        self::assertSame(1127, $snapshot->connectedRealmId);
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

    public function testAuctionHouseSnapshotArrayIsAlsoSinglePass(): void
    {
        $auction = new Auction(
            id: 10000001,
            item: new AuctionItem(id: 19019),
            quantity: 1,
            buyoutCopper: 50000000,
            bidCopper: 45000000,
            timeLeft: AuctionTimeLeft::VERY_LONG,
        );

        $snapshot = new AuctionHouseSnapshot(
            connectedRealmId: 1127,
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

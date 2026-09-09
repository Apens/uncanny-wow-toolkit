<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\AuctionHouse;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Enum\AuctionTimeLeft;
use UncannyWoW\Core\Domain\Model\AuctionHouse\Auction;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItem;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItemModifier;

final class AuctionTest extends TestCase
{
    public function testAuctionWithCompleteItemModifiersAndBuyout(): void
    {
        $modifier = new AuctionItemModifier(type: 28, value: 156);
        $item = new AuctionItem(
            id: 19019,
            context: 0,
            bonusLists: [1487, 6652],
            modifiers: [$modifier],
            petSpeciesId: null,
            petBreedId: null,
            petLevel: null,
            petQualityId: null,
        );

        $auction = new Auction(
            id: 10000001,
            item: $item,
            quantity: 1,
            buyoutCopper: 50000000,
            bidCopper: 45000000,
            timeLeft: AuctionTimeLeft::VERY_LONG,
        );

        self::assertSame(10000001, $auction->id);
        self::assertSame($item, $auction->item);
        self::assertSame(19019, $auction->item->id);
        self::assertSame(0, $auction->item->context);
        self::assertSame([1487, 6652], $auction->item->bonusLists);
        self::assertCount(1, $auction->item->modifiers);
        self::assertSame(28, $auction->item->modifiers[0]->type);
        self::assertSame(156, $auction->item->modifiers[0]->value);
        self::assertNull($auction->item->petSpeciesId);
        self::assertSame(1, $auction->quantity);
        self::assertSame(50000000, $auction->buyoutCopper);
        self::assertSame(45000000, $auction->bidCopper);
        self::assertSame(AuctionTimeLeft::VERY_LONG, $auction->timeLeft);
    }

    public function testAuctionWithPetFieldsAndBidOnly(): void
    {
        $item = new AuctionItem(
            id: 210781,
            petSpeciesId: 256,
            petBreedId: 3,
            petLevel: 25,
            petQualityId: 3,
        );

        $auction = new Auction(
            id: 10000002,
            item: $item,
            quantity: 1,
            buyoutCopper: null,
            bidCopper: 1250000,
            timeLeft: AuctionTimeLeft::SHORT,
        );

        self::assertSame(10000002, $auction->id);
        self::assertSame(256, $auction->item->petSpeciesId);
        self::assertSame(3, $auction->item->petBreedId);
        self::assertSame(25, $auction->item->petLevel);
        self::assertSame(3, $auction->item->petQualityId);
        self::assertNull($auction->buyoutCopper);
        self::assertSame(1250000, $auction->bidCopper);
        self::assertSame(AuctionTimeLeft::SHORT, $auction->timeLeft);
    }
}

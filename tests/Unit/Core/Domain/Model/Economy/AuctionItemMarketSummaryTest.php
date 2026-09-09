<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\Economy;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItem;
use UncannyWoW\Core\Domain\Model\Economy\AuctionItemMarketSummary;
use UncannyWoW\Core\Domain\Model\Economy\MarketItemIdentity;
use UncannyWoW\Core\Domain\Model\Economy\NonCommodityPriceLevel;

final class AuctionItemMarketSummaryTest extends TestCase
{
    public function testInstantiationWithBuyout(): void
    {
        $item = new AuctionItem(id: 19019);
        $identity = MarketItemIdentity::fromAuctionItem($item);
        $levels = [new NonCommodityPriceLevel(50000000, 1, 1, 1)];

        $summary = new AuctionItemMarketSummary(
            item: $item,
            identity: $identity,
            listingCount: 1,
            totalQuantity: 1,
            buyoutListingCount: 1,
            bidOnlyListingCount: 0,
            lowestBuyoutCopper: 50000000,
            quantityAtLowestBuyout: 1,
            lowestBidCopper: 45000000,
            priceLevels: $levels,
        );

        self::assertSame($item, $summary->item);
        self::assertSame($identity, $summary->identity);
        self::assertSame(1, $summary->listingCount);
        self::assertSame(1, $summary->totalQuantity);
        self::assertSame(1, $summary->buyoutListingCount);
        self::assertSame(0, $summary->bidOnlyListingCount);
        self::assertSame(50000000, $summary->lowestBuyoutCopper);
        self::assertSame(1, $summary->quantityAtLowestBuyout);
        self::assertSame(45000000, $summary->lowestBidCopper);
        self::assertTrue($summary->hasBuyout());
        self::assertSame(1, $summary->getQuantityAtPriceLevels());
    }

    public function testInstantiationBidOnly(): void
    {
        $item = new AuctionItem(id: 124105);
        $identity = MarketItemIdentity::fromAuctionItem($item);

        $summary = new AuctionItemMarketSummary(
            item: $item,
            identity: $identity,
            listingCount: 2,
            totalQuantity: 40,
            buyoutListingCount: 0,
            bidOnlyListingCount: 2,
            lowestBuyoutCopper: null,
            quantityAtLowestBuyout: 0,
            lowestBidCopper: 10000,
            priceLevels: [],
        );

        self::assertFalse($summary->hasBuyout());
        self::assertNull($summary->lowestBuyoutCopper);
        self::assertSame(0, $summary->quantityAtLowestBuyout);
        self::assertSame(10000, $summary->lowestBidCopper);
        self::assertSame(0, $summary->getQuantityAtPriceLevels());
    }
}

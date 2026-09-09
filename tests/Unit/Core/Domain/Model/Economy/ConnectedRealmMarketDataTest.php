<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\Economy;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItem;
use UncannyWoW\Core\Domain\Model\Economy\AuctionItemMarketSummary;
use UncannyWoW\Core\Domain\Model\Economy\ConnectedRealmMarketData;
use UncannyWoW\Core\Domain\Model\Economy\MarketItemIdentity;

final class ConnectedRealmMarketDataTest extends TestCase
{
    public function testCollectionOperations(): void
    {
        $itemA = new AuctionItem(id: 19019, context: 1);
        $idA = MarketItemIdentity::fromAuctionItem($itemA);
        $summaryA = new AuctionItemMarketSummary(
            item: $itemA,
            identity: $idA,
            listingCount: 1,
            totalQuantity: 1,
            buyoutListingCount: 1,
            bidOnlyListingCount: 0,
            lowestBuyoutCopper: 50000000,
            quantityAtLowestBuyout: 1,
        );

        $itemB = new AuctionItem(id: 19019, context: 2);
        $idB = MarketItemIdentity::fromAuctionItem($itemB);
        $summaryB = new AuctionItemMarketSummary(
            item: $itemB,
            identity: $idB,
            listingCount: 2,
            totalQuantity: 2,
            buyoutListingCount: 2,
            bidOnlyListingCount: 0,
            lowestBuyoutCopper: 60000000,
            quantityAtLowestBuyout: 1,
        );

        $data = new ConnectedRealmMarketData(
            connectedRealmId: 1127,
            summaries: [
                $idA->getFingerprint() => $summaryA,
                $idB->getFingerprint() => $summaryB,
            ],
            totalAuctions: 3,
            totalQuantity: 3,
        );

        self::assertSame(1127, $data->connectedRealmId);
        self::assertSame(3, $data->totalAuctions);
        self::assertSame(3, $data->totalQuantity);
        self::assertCount(2, $data);
        self::assertSame(2, $data->uniqueVariantCount());

        self::assertTrue($data->has($idA));
        self::assertTrue($data->has($idA->getFingerprint()));
        self::assertSame($summaryA, $data->get($idA));

        // Test getByItemId
        $matches = $data->getByItemId(19019);
        self::assertCount(2, $matches);

        $emptyMatches = $data->getByItemId(999999);
        self::assertCount(0, $emptyMatches);
    }
}

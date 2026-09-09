<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Aggregator;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Aggregator\ConnectedRealmMarketAggregator;
use UncannyWoW\Core\Domain\Model\AuctionHouse\Auction;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItem;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItemModifier;

final class ConnectedRealmMarketAggregatorTest extends TestCase
{
    private ConnectedRealmMarketAggregator $aggregator;

    protected function setUp(): void
    {
        $this->aggregator = new ConnectedRealmMarketAggregator();
    }

    public function testAggregateConnectedRealmAuctions(): void
    {
        $auctions = [
            // Item 1: Thunderfury, 1 unit, buyout 500k, bid 450k
            new Auction(
                id: 101,
                item: new AuctionItem(id: 19019, context: 0, bonusLists: [1487, 6652]),
                quantity: 1,
                buyoutCopper: 50000000,
                bidCopper: 45000000,
            ),
            // Item 2: Same Thunderfury variant (identical received order), 1 unit, lower buyout 400k
            new Auction(
                id: 102,
                item: new AuctionItem(id: 19019, context: 0, bonusLists: [1487, 6652]),
                quantity: 1,
                buyoutCopper: 40000000,
                bidCopper: 38000000,
            ),
            // Item 3: Different variant of Thunderfury (different bonus list)
            new Auction(
                id: 103,
                item: new AuctionItem(id: 19019, context: 0, bonusLists: [9999]),
                quantity: 1,
                buyoutCopper: 60000000,
            ),
            // Item 4: Bid-only item with quantity 20 (buyout null, bid 200k)
            new Auction(
                id: 104,
                item: new AuctionItem(id: 124105),
                quantity: 20,
                buyoutCopper: null,
                bidCopper: 200000,
            ),
            // Item 5: Multi-quantity item with buyout = 100 copper, quantity = 3
            // Proves exact lot price 100 is preserved and NEVER becomes lossy 33
            new Auction(
                id: 105,
                item: new AuctionItem(id: 168487),
                quantity: 3,
                buyoutCopper: 100,
                bidCopper: 80,
            ),
        ];

        $data = $this->aggregator->aggregate($auctions, connectedRealmId: 1127, maxPriceLevels: 5);

        self::assertSame(1127, $data->connectedRealmId);
        self::assertSame(5, $data->totalAuctions);
        self::assertSame(26, $data->totalQuantity); // 1 + 1 + 1 + 20 + 3
        self::assertSame(4, $data->uniqueVariantCount());

        // First Thunderfury variant (2 listings merged)
        $tfMatches = $data->getByItemId(19019);
        self::assertCount(2, $tfMatches);

        $tfMain = null;
        foreach ($tfMatches as $tf) {
            if ($tf->listingCount === 2) {
                $tfMain = $tf;
                break;
            }
        }
        self::assertNotNull($tfMain);
        self::assertSame(2, $tfMain->totalQuantity);
        self::assertSame(2, $tfMain->buyoutListingCount);
        self::assertSame(0, $tfMain->bidOnlyListingCount);
        self::assertSame(40000000, $tfMain->lowestBuyoutCopper);
        self::assertSame(1, $tfMain->quantityAtLowestBuyout);
        self::assertSame(38000000, $tfMain->lowestBidCopper);
        self::assertCount(2, $tfMain->priceLevels);
        self::assertSame(40000000, $tfMain->priceLevels[0]->buyoutCopper);
        self::assertSame(1, $tfMain->priceLevels[0]->quantityPerListing);
        self::assertSame(1, $tfMain->priceLevels[0]->listingCount);
        self::assertSame(1, $tfMain->priceLevels[0]->totalQuantity);

        self::assertSame(50000000, $tfMain->priceLevels[1]->buyoutCopper);
        self::assertSame(1, $tfMain->priceLevels[1]->quantityPerListing);
        self::assertSame(1, $tfMain->priceLevels[1]->listingCount);
        self::assertSame(1, $tfMain->priceLevels[1]->totalQuantity);

        // Bid-only item (exact bid 200000 preserved, not 10000)
        $bidOnlyMatches = $data->getByItemId(124105);
        self::assertCount(1, $bidOnlyMatches);
        $bidOnly = $bidOnlyMatches[0];
        self::assertSame(1, $bidOnly->listingCount);
        self::assertSame(20, $bidOnly->totalQuantity);
        self::assertSame(0, $bidOnly->buyoutListingCount);
        self::assertSame(1, $bidOnly->bidOnlyListingCount);
        self::assertNull($bidOnly->lowestBuyoutCopper);
        self::assertSame(200000, $bidOnly->lowestBidCopper);
        self::assertCount(0, $bidOnly->priceLevels);

        // Multi-quantity buyout item: 100 copper for quantity 3
        // Proves it never becomes 33 copper
        $multiMatches = $data->getByItemId(168487);
        self::assertCount(1, $multiMatches);
        $multi = $multiMatches[0];
        self::assertSame(1, $multi->listingCount);
        self::assertSame(3, $multi->totalQuantity);
        self::assertSame(100, $multi->lowestBuyoutCopper);
        self::assertNotSame(33, $multi->lowestBuyoutCopper);
        self::assertSame(3, $multi->quantityAtLowestBuyout);
        self::assertCount(1, $multi->priceLevels);
        self::assertSame(100, $multi->priceLevels[0]->buyoutCopper);
        self::assertNotSame(33, $multi->priceLevels[0]->buyoutCopper);
        self::assertSame(3, $multi->priceLevels[0]->quantityPerListing);
        self::assertSame(1, $multi->priceLevels[0]->listingCount);
        self::assertSame(3, $multi->priceLevels[0]->totalQuantity);
    }

    public function testDifferentBonusListOrderingProducesDistinctVariants(): void
    {
        $auctions = [
            new Auction(
                id: 1,
                item: new AuctionItem(id: 19019, bonusLists: [1, 2]),
                quantity: 1,
                buyoutCopper: 100,
            ),
            new Auction(
                id: 2,
                item: new AuctionItem(id: 19019, bonusLists: [2, 1]),
                quantity: 1,
                buyoutCopper: 100,
            ),
        ];

        $data = $this->aggregator->aggregate($auctions, connectedRealmId: 1127);
        self::assertSame(2, $data->uniqueVariantCount());
    }

    public function testEmptyAuctionsReturnsEmptyMarketData(): void
    {
        $data = $this->aggregator->aggregate([], connectedRealmId: 1127);

        self::assertSame(0, $data->totalAuctions);
        self::assertSame(0, $data->totalQuantity);
        self::assertCount(0, $data);
    }

    public function testInvalidConnectedRealmIdThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connected realm ID must be a positive integer, got 0.');

        $this->aggregator->aggregate([], 0);
    }

    public function testInvalidMaxPriceLevelsBoundaries(): void
    {
        foreach ([0, -1, 11, \PHP_INT_MAX] as $invalidN) {
            try {
                $this->aggregator->aggregate([], 1127, $invalidN);
                self::fail(sprintf('Expected InvalidArgumentException for maxPriceLevels = %d', $invalidN));
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Max price levels must be between 1 and 10', $e->getMessage());
            }
        }
    }

    public function testValidMaxPriceLevelsBoundaries(): void
    {
        $data1 = $this->aggregator->aggregate([], 1127, 1);
        self::assertCount(0, $data1);

        $data10 = $this->aggregator->aggregate([], 1127, 10);
        self::assertCount(0, $data10);
    }

    public function testPriceLevelsPreserveLotSizeAndAggregateIdenticalBuyoutAndQuantity(): void
    {
        $auctions = [
            // Listing A: 100 copper, qty 1
            new Auction(
                id: 1,
                item: new AuctionItem(id: 12345),
                quantity: 1,
                buyoutCopper: 100,
            ),
            // Listing B: 100 copper, qty 3 (distinct lot size from A!)
            new Auction(
                id: 2,
                item: new AuctionItem(id: 12345),
                quantity: 3,
                buyoutCopper: 100,
            ),
            // Listing C: 100 copper, qty 1 (identical buyout & lot size to A -> aggregates!)
            new Auction(
                id: 3,
                item: new AuctionItem(id: 12345),
                quantity: 1,
                buyoutCopper: 100,
            ),
        ];

        $data = $this->aggregator->aggregate($auctions, 1127, maxPriceLevels: 5);
        $summaries = $data->getByItemId(12345);
        self::assertCount(1, $summaries);

        $summary = $summaries[0];
        self::assertSame(3, $summary->listingCount);
        self::assertSame(5, $summary->totalQuantity);
        self::assertCount(2, $summary->priceLevels);

        // Level 0: 100 copper, quantityPerListing = 1 (2 listings, total quantity = 2)
        self::assertSame(100, $summary->priceLevels[0]->buyoutCopper);
        self::assertSame(1, $summary->priceLevels[0]->quantityPerListing);
        self::assertSame(2, $summary->priceLevels[0]->listingCount);
        self::assertSame(2, $summary->priceLevels[0]->totalQuantity);

        // Level 1: 100 copper, quantityPerListing = 3 (1 listing, total quantity = 3)
        self::assertSame(100, $summary->priceLevels[1]->buyoutCopper);
        self::assertSame(3, $summary->priceLevels[1]->quantityPerListing);
        self::assertSame(1, $summary->priceLevels[1]->listingCount);
        self::assertSame(3, $summary->priceLevels[1]->totalQuantity);
    }
}

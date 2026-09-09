<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Aggregator;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Aggregator\CommodityMarketAggregator;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\AuctionHouse\CommodityAuction;

final class CommodityMarketAggregatorTest extends TestCase
{
    private CommodityMarketAggregator $aggregator;

    protected function setUp(): void
    {
        $this->aggregator = new CommodityMarketAggregator();
    }

    public function testAggregateCommoditiesSinglePass(): void
    {
        $auctions = [
            new CommodityAuction(id: 1, itemId: 190381, quantity: 500, unitPriceCopper: 12500),
            new CommodityAuction(id: 2, itemId: 190381, quantity: 1000, unitPriceCopper: 12600),
            new CommodityAuction(id: 3, itemId: 190381, quantity: 200, unitPriceCopper: 12500), // Same price, add to level
            new CommodityAuction(id: 4, itemId: 200111, quantity: 25, unitPriceCopper: 450000),
        ];

        $data = $this->aggregator->aggregate($auctions, Region::EU, maxPriceLevels: 5);

        self::assertSame(Region::EU, $data->region);
        self::assertSame(4, $data->totalAuctions);
        self::assertSame(1725, $data->totalQuantity);
        self::assertCount(2, $data);

        $summary1 = $data->get(190381);
        self::assertNotNull($summary1);
        self::assertSame(190381, $summary1->itemId);
        self::assertSame(3, $summary1->auctionCount);
        self::assertSame(1700, $summary1->totalQuantity);
        self::assertSame(12500, $summary1->lowestUnitPriceCopper);
        self::assertSame(700, $summary1->quantityAtLowestPrice); // 500 + 200
        self::assertSame(12600, $summary1->highestUnitPriceCopper);

        // Price levels
        self::assertCount(2, $summary1->priceLevels);
        self::assertSame(12500, $summary1->priceLevels[0]->priceCopper);
        self::assertSame(700, $summary1->priceLevels[0]->quantity);
        self::assertSame(2, $summary1->priceLevels[0]->listingCount);

        self::assertSame(12600, $summary1->priceLevels[1]->priceCopper);
        self::assertSame(1000, $summary1->priceLevels[1]->quantity);
        self::assertSame(1, $summary1->priceLevels[1]->listingCount);
    }

    public function testPriceLevelRetentionBoundsToMaxN(): void
    {
        // 6 distinct prices, max 3
        $auctions = [
            new CommodityAuction(id: 1, itemId: 100, quantity: 10, unitPriceCopper: 50),
            new CommodityAuction(id: 2, itemId: 100, quantity: 10, unitPriceCopper: 10),
            new CommodityAuction(id: 3, itemId: 100, quantity: 10, unitPriceCopper: 30),
            // Map now has [50, 10, 30]. Max is 50.
            new CommodityAuction(id: 4, itemId: 100, quantity: 10, unitPriceCopper: 40),
            // 40 < 50 => 50 evicted, map has [10, 30, 40]. Max is 40.
            new CommodityAuction(id: 5, itemId: 100, quantity: 10, unitPriceCopper: 60),
            // 60 > 40 => ignored
            new CommodityAuction(id: 6, itemId: 100, quantity: 10, unitPriceCopper: 20),
            // 20 < 40 => 40 evicted, map has [10, 30, 20]. Max is 30.
        ];

        $data = $this->aggregator->aggregate($auctions, Region::EU, maxPriceLevels: 3);
        $summary = $data->get(100);
        self::assertNotNull($summary);

        self::assertSame(6, $summary->auctionCount);
        self::assertSame(60, $summary->totalQuantity);
        self::assertSame(10, $summary->lowestUnitPriceCopper);
        self::assertSame(60, $summary->highestUnitPriceCopper);

        // Price levels must be exactly [10, 20, 30] sorted ascending
        self::assertCount(3, $summary->priceLevels);
        self::assertSame(10, $summary->priceLevels[0]->priceCopper);
        self::assertSame(20, $summary->priceLevels[1]->priceCopper);
        self::assertSame(30, $summary->priceLevels[2]->priceCopper);
    }

    public function testEmptyAuctionsReturnsEmptyMarketData(): void
    {
        $data = $this->aggregator->aggregate([], Region::EU);

        self::assertSame(0, $data->totalAuctions);
        self::assertSame(0, $data->totalQuantity);
        self::assertCount(0, $data);
    }

    public function testInvalidMaxPriceLevelsBoundaries(): void
    {
        foreach ([0, -1, 11, \PHP_INT_MAX] as $invalidN) {
            try {
                $this->aggregator->aggregate([], Region::EU, $invalidN);
                self::fail(sprintf('Expected InvalidArgumentException for maxPriceLevels = %d', $invalidN));
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Max price levels must be between 1 and 10', $e->getMessage());
            }
        }
    }

    public function testValidMaxPriceLevelsBoundaries(): void
    {
        $data1 = $this->aggregator->aggregate([], Region::EU, 1);
        self::assertCount(0, $data1);

        $data10 = $this->aggregator->aggregate([], Region::EU, 10);
        self::assertCount(0, $data10);
    }
}

<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Aggregator\CommodityMarketAggregator;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\AuctionHouse\CommodityAuction;

final class SyntheticMarketStreamingMemoryTest extends TestCase
{
    /**
     * Verify that aggregating 100,000+ auctions consumes streams on the fly with bounded memory.
     */
    public function testSynthetic100kStreamAggregationBoundedMemory(): void
    {
        $aggregator = new CommodityMarketAggregator();

        $totalAuctionsToGenerate = 100000;
        $uniqueItemCount = 500;

        /**
         * Generator yielding 100,000 auctions lazily without storing them in an array.
         *
         * @return \Generator<int, CommodityAuction>
         */
        $generator = (function () use ($totalAuctionsToGenerate, $uniqueItemCount): \Generator {
            for ($i = 1; $i <= $totalAuctionsToGenerate; $i++) {
                $itemId = 1000 + ($i % $uniqueItemCount);
                $price = 1000 + ($i % 23) * 50; // Coprime to 500 so items have multiple price points
                $quantity = 1 + ($i % 50);

                yield new CommodityAuction(
                    id: $i,
                    itemId: $itemId,
                    quantity: $quantity,
                    unitPriceCopper: $price,
                );
            }
        })();

        gc_collect_cycles();
        $startMemory = memory_get_usage(true);

        $marketData = $aggregator->aggregate(
            auctions: $generator,
            region: Region::EU,
            maxPriceLevels: 5,
        );

        $endMemory = memory_get_usage(true);
        $memoryDeltaMb = ($endMemory - $startMemory) / 1024 / 1024;

        self::assertSame(Region::EU, $marketData->region);
        self::assertSame($totalAuctionsToGenerate, $marketData->totalAuctions);
        // Architectural assertions:
        // 1. Exact cardinality matches unique item count
        self::assertSame($uniqueItemCount, $marketData->uniqueItemCount());

        // 2. Every aggregated item summary respects maxPriceLevels bounded constraint (<= 5)
        foreach ($marketData->all() as $summary) {
            self::assertLessThanOrEqual(5, count($summary->priceLevels));
        }

        // Verify arbitrary item summary math
        $sample = $marketData->get(1000);
        self::assertNotNull($sample);
        self::assertSame(200, $sample->auctionCount); // 100000 / 500
        self::assertCount(5, $sample->priceLevels);

        // Coarse memory sanity check: processing 100k items aggregated into 500 summaries must remain well within normal limit (< 128 MB)
        self::assertLessThan(128.0, $memoryDeltaMb, sprintf('Coarse memory delta exceeded 128 MB (was %.2f MB)', $memoryDeltaMb));
    }
}

<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\Economy;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketData;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketSummary;

final class CommodityMarketDataTest extends TestCase
{
    public function testCollectionOperations(): void
    {
        $summary1 = new CommodityMarketSummary(190381, 2, 100, 1000, 50, 1200);
        $summary2 = new CommodityMarketSummary(200111, 1, 25, 450000, 25, 450000);

        $data = new CommodityMarketData(
            region: Region::EU,
            summaries: [
                190381 => $summary1,
                200111 => $summary2,
            ],
            totalAuctions: 3,
            totalQuantity: 125,
        );

        self::assertSame(Region::EU, $data->region);
        self::assertSame(3, $data->totalAuctions);
        self::assertSame(125, $data->totalQuantity);
        self::assertCount(2, $data);
        self::assertSame(2, $data->uniqueItemCount());

        self::assertTrue($data->has(190381));
        self::assertFalse($data->has(999999));

        self::assertSame($summary1, $data->get(190381));
        self::assertNull($data->get(999999));

        $iterated = [];
        foreach ($data as $itemId => $summary) {
            $iterated[$itemId] = $summary;
        }

        self::assertCount(2, $iterated);
        self::assertSame($summary1, $iterated[190381]);
    }
}

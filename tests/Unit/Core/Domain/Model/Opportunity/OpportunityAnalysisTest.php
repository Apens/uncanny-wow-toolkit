<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\Opportunity;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Model\Opportunity\CommodityOpportunity;
use UncannyWoW\Core\Domain\Model\Opportunity\OpportunityAnalysis;
use UncannyWoW\Core\Domain\Model\Opportunity\Roi;

final class OpportunityAnalysisTest extends TestCase
{
    private function createDummyCommodityOpportunity(
        int $itemId,
        int $profitCopper,
        int $acquisitionCostCopper,
    ): CommodityOpportunity {
        return new CommodityOpportunity(
            itemId: $itemId,
            clearedPriceLevelCount: 1,
            acquisitionQuantity: 1,
            acquisitionCostCopper: $acquisitionCostCopper,
            targetUnitPriceCopper: $acquisitionCostCopper + $profitCopper + 10,
            targetLevelQuantity: 1,
            targetLevelListingCount: 1,
            grossTargetRevenueCopper: $acquisitionCostCopper + $profitCopper + 10,
            saleFeeCopper: 10,
            netTargetRevenueCopper: $acquisitionCostCopper + $profitCopper,
            prospectiveProfitCopper: $profitCopper,
            unitPriceSpreadCopper: $profitCopper + 10,
            roi: new Roi(profitCopper: $profitCopper, acquisitionCostCopper: $acquisitionCostCopper),
        );
    }

    public function testLazyAndReiterable(): void
    {
        $counter = 0;
        $factory = function () use (&$counter): \Generator {
            $counter++;
            yield $this->createDummyCommodityOpportunity(100, 50, 100);
            yield $this->createDummyCommodityOpportunity(200, 60, 200);
        };

        $analysis = new OpportunityAnalysis($factory);

        // Not invoked upon construction
        self::assertSame(0, $counter);

        // First iteration
        $firstRun = [];
        foreach ($analysis as $item) {
            $firstRun[] = $item->itemId;
        }
        self::assertSame(1, $counter);
        self::assertSame([100, 200], $firstRun);

        // Second iteration
        $secondRun = [];
        foreach ($analysis as $item) {
            $secondRun[] = $item->itemId;
        }
        self::assertSame(2, $counter);
        self::assertSame([100, 200], $secondRun);
    }

    public function testCountIsAccurateAndReiterates(): void
    {
        $factory = function (): \Generator {
            yield $this->createDummyCommodityOpportunity(1, 10, 100);
            yield $this->createDummyCommodityOpportunity(2, 20, 100);
            yield $this->createDummyCommodityOpportunity(3, 30, 100);
        };

        $analysis = new OpportunityAnalysis($factory);

        self::assertCount(3, $analysis);
        self::assertSame(3, $analysis->count());
    }

    public function testFilterIsLazyAndReiterable(): void
    {
        $factory = function (): \Generator {
            yield $this->createDummyCommodityOpportunity(1, 10, 100);
            yield $this->createDummyCommodityOpportunity(2, 50, 100);
            yield $this->createDummyCommodityOpportunity(3, 30, 100);
        };

        $analysis = new OpportunityAnalysis($factory);
        $filtered = $analysis->filter(static fn(CommodityOpportunity $o): bool => $o->prospectiveProfitCopper >= 30);

        $results = iterator_to_array($filtered);
        self::assertCount(2, $results);
        self::assertSame(2, $results[0]->itemId);
        self::assertSame(3, $results[1]->itemId);

        // Filter is also re-iterable
        $results2 = iterator_to_array($filtered);
        self::assertCount(2, $results2);
        self::assertSame(2, $results2[0]->itemId);
        self::assertSame(3, $results2[1]->itemId);
    }

    public function testAllMaterializesArray(): void
    {
        $factory = function (): \Generator {
            yield $this->createDummyCommodityOpportunity(1, 10, 100);
            yield $this->createDummyCommodityOpportunity(2, 20, 100);
        };

        $analysis = new OpportunityAnalysis($factory);
        $all = $analysis->all();

        self::assertCount(2, $all);
        self::assertSame(1, $all[0]->itemId);
        self::assertSame(2, $all[1]->itemId);
    }

    public function testSortByProfitDesc(): void
    {
        $factory = function (): \Generator {
            yield $this->createDummyCommodityOpportunity(1, 10, 100);
            yield $this->createDummyCommodityOpportunity(2, 50, 100);
            yield $this->createDummyCommodityOpportunity(3, 30, 100);
        };

        $analysis = new OpportunityAnalysis($factory);
        $sorted = $analysis->sortByProfitDesc();

        self::assertSame([2, 3, 1], array_map(static fn($o) => $o->itemId, $sorted));
    }

    public function testSortByRoiDesc(): void
    {
        $factory = function (): \Generator {
            // Item 1: 50 profit on 100 cost = 50%
            yield $this->createDummyCommodityOpportunity(1, 50, 100);
            // Item 2: 80 profit on 200 cost = 40%
            yield $this->createDummyCommodityOpportunity(2, 80, 200);
            // Item 3: 20 profit on 20 cost = 100%
            yield $this->createDummyCommodityOpportunity(3, 20, 20);
        };

        $analysis = new OpportunityAnalysis($factory);
        $sorted = $analysis->sortByRoiDesc();

        // 100% (item 3) > 50% (item 1) > 40% (item 2)
        self::assertSame([3, 1, 2], array_map(static fn($o) => $o->itemId, $sorted));
    }

    public function testSortByCapitalAsc(): void
    {
        $factory = function (): \Generator {
            yield $this->createDummyCommodityOpportunity(1, 50, 500);
            yield $this->createDummyCommodityOpportunity(2, 10, 50);
            yield $this->createDummyCommodityOpportunity(3, 20, 200);
        };

        $analysis = new OpportunityAnalysis($factory);
        $sorted = $analysis->sortByCapitalAsc();

        self::assertSame([2, 3, 1], array_map(static fn($o) => $o->itemId, $sorted));
    }

    public function testDistinctByHighestProfit(): void
    {
        $factory = function (): \Generator {
            // Item 1 has 2 boundaries
            yield $this->createDummyCommodityOpportunity(1, 20, 100);
            yield $this->createDummyCommodityOpportunity(1, 50, 200);
            // Item 2 has 2 boundaries
            yield $this->createDummyCommodityOpportunity(2, 90, 500);
            yield $this->createDummyCommodityOpportunity(2, 30, 150);
        };

        $analysis = new OpportunityAnalysis($factory);
        $distinct = $analysis->distinctByHighestProfit();

        self::assertCount(2, $distinct);
        $byItem = [];
        foreach ($distinct as $d) {
            $byItem[$d->itemId] = $d->prospectiveProfitCopper;
        }

        self::assertSame(50, $byItem[1]);
        self::assertSame(90, $byItem[2]);
    }

    public function testDistinctByHighestRoi(): void
    {
        $factory = function (): \Generator {
            // Item 1: Boundary A = 50 on 100 (50%), Boundary B = 80 on 200 (40%)
            yield $this->createDummyCommodityOpportunity(1, 50, 100);
            yield $this->createDummyCommodityOpportunity(1, 80, 200);
        };

        $analysis = new OpportunityAnalysis($factory);
        $distinct = $analysis->distinctByHighestRoi();

        self::assertCount(1, $distinct);
        self::assertSame(50, $distinct[0]->prospectiveProfitCopper);
        self::assertSame(100, $distinct[0]->acquisitionCostCopper);
    }

    public function testDistinctByLowestCapital(): void
    {
        $factory = function (): \Generator {
            // Item 1: Boundary A = 50 on 100, Boundary B = 80 on 200
            yield $this->createDummyCommodityOpportunity(1, 80, 200);
            yield $this->createDummyCommodityOpportunity(1, 50, 100);
        };

        $analysis = new OpportunityAnalysis($factory);
        $distinct = $analysis->distinctByLowestCapital();

        self::assertCount(1, $distinct);
        self::assertSame(100, $distinct[0]->acquisitionCostCopper);
    }
}

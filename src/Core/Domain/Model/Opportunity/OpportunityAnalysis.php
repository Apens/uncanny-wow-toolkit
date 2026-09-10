<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Opportunity;

use Closure;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Lazy, re-iterable opportunity analysis result.
 *
 * Traversal evaluates structural opportunities on the fly from already-materialized M7 MarketData
 * using pure computation without eager memory buffering and without secondary HTTP requests.
 *
 * Re-iterating this object evaluates the underlying market data again deterministically.
 *
 * Complexity Characteristics:
 * - Default iteration (foreach): O(1) additional memory, pure generator stream.
 * - filter(callable): O(1) additional memory, lazy and re-iterable.
 * - count(): O(1) additional memory, O(U * P) time (traverses underlying market data).
 * - all(), sort*, distinct*: EXPLICIT MATERIALIZATION in O(C) or O(U) memory.
 *
 * @template T of (CommodityOpportunity|AuctionItemOpportunity)
 * @implements IteratorAggregate<int, T>
 */
final readonly class OpportunityAnalysis implements Countable, IteratorAggregate
{
    /**
     * @param Closure(): \Generator<int, T> $generatorFactory
     */
    public function __construct(
        private Closure $generatorFactory,
    ) {}

    /**
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable
    {
        return ($this->generatorFactory)();
    }

    /**
     * Number of opportunity candidates.
     *
     * Complexity: O(1) memory, O(U * P) time (traverses market data).
     */
    public function count(): int
    {
        $count = 0;
        foreach ($this as $_) {
            $count++;
        }
        return $count;
    }

    /**
     * Lazy, re-iterable filter on candidates.
     *
     * Complexity: O(1) memory, evaluated on traversal.
     *
     * @param callable(T): bool $predicate
     * @return self<T>
     */
    public function filter(callable $predicate): self
    {
        $factory = $this->generatorFactory;

        return new self(
            generatorFactory: static function () use ($factory, $predicate): \Generator {
                foreach ($factory() as $candidate) {
                    if ($predicate($candidate)) {
                        yield $candidate;
                    }
                }
            },
        );
    }

    /**
     * Eagerly materialize all candidates into a list.
     *
     * Complexity: O(C) memory.
     *
     * @return list<T>
     */
    public function all(): array
    {
        $items = [];
        foreach ($this as $candidate) {
            $items[] = $candidate;
        }
        return $items;
    }

    /**
     * Sort candidates by prospective net profit in descending order.
     *
     * Materializes candidates into memory: O(C) memory.
     *
     * @return list<T>
     */
    public function sortByProfitDesc(): array
    {
        $items = $this->all();
        usort($items, static function ($a, $b): int {
            if ($a->prospectiveProfitCopper !== $b->prospectiveProfitCopper) {
                return $b->prospectiveProfitCopper <=> $a->prospectiveProfitCopper;
            }
            // Tie-break by higher exact ROI, then lower acquisition cost
            $roiCmp = $b->roi->compareTo($a->roi);
            if ($roiCmp !== 0) {
                return $roiCmp;
            }
            return $a->acquisitionCostCopper <=> $b->acquisitionCostCopper;
        });

        return $items;
    }

    /**
     * Sort candidates by exact ROI in descending order.
     *
     * Uses exact rational comparison without float rounding or basis-point truncation.
     * Materializes candidates into memory: O(C) memory.
     *
     * @return list<T>
     */
    public function sortByRoiDesc(): array
    {
        $items = $this->all();
        usort($items, static function ($a, $b): int {
            $roiCmp = $b->roi->compareTo($a->roi);
            if ($roiCmp !== 0) {
                return $roiCmp;
            }
            if ($a->prospectiveProfitCopper !== $b->prospectiveProfitCopper) {
                return $b->prospectiveProfitCopper <=> $a->prospectiveProfitCopper;
            }
            return $a->acquisitionCostCopper <=> $b->acquisitionCostCopper;
        });

        return $items;
    }

    /**
     * Sort candidates by required acquisition capital in ascending order.
     *
     * Materializes candidates into memory: O(C) memory.
     *
     * @return list<T>
     */
    public function sortByCapitalAsc(): array
    {
        $items = $this->all();
        usort($items, static function ($a, $b): int {
            if ($a->acquisitionCostCopper !== $b->acquisitionCostCopper) {
                return $a->acquisitionCostCopper <=> $b->acquisitionCostCopper;
            }
            if ($a->prospectiveProfitCopper !== $b->prospectiveProfitCopper) {
                return $b->prospectiveProfitCopper <=> $a->prospectiveProfitCopper;
            }
            return $b->roi->compareTo($a->roi);
        });

        return $items;
    }

    /**
     * Retain exactly one candidate per asset (Item ID or variant) with the highest prospective profit.
     *
     * Materializes candidates into memory: O(U) memory.
     *
     * @return list<T>
     */
    public function distinctByHighestProfit(): array
    {
        /** @var array<int|string, T> $bestByAsset */
        $bestByAsset = [];

        foreach ($this as $candidate) {
            $assetKey = $this->extractAssetKey($candidate);
            if (!isset($bestByAsset[$assetKey])) {
                $bestByAsset[$assetKey] = $candidate;
            } else {
                $currentBest = $bestByAsset[$assetKey];
                if ($candidate->prospectiveProfitCopper > $currentBest->prospectiveProfitCopper) {
                    $bestByAsset[$assetKey] = $candidate;
                }
            }
        }

        return array_values($bestByAsset);
    }

    /**
     * Retain exactly one candidate per asset with the highest exact ROI.
     *
     * Uses exact rational comparison without float rounding or basis-point truncation.
     * Materializes candidates into memory: O(U) memory.
     *
     * @return list<T>
     */
    public function distinctByHighestRoi(): array
    {
        /** @var array<int|string, T> $bestByAsset */
        $bestByAsset = [];

        foreach ($this as $candidate) {
            $assetKey = $this->extractAssetKey($candidate);
            if (!isset($bestByAsset[$assetKey])) {
                $bestByAsset[$assetKey] = $candidate;
            } else {
                $currentBest = $bestByAsset[$assetKey];
                if ($candidate->roi->compareTo($currentBest->roi) > 0) {
                    $bestByAsset[$assetKey] = $candidate;
                }
            }
        }

        return array_values($bestByAsset);
    }

    /**
     * Retain exactly one candidate per asset with the lowest required acquisition capital.
     *
     * Materializes candidates into memory: O(U) memory.
     *
     * @return list<T>
     */
    public function distinctByLowestCapital(): array
    {
        /** @var array<int|string, T> $bestByAsset */
        $bestByAsset = [];

        foreach ($this as $candidate) {
            $assetKey = $this->extractAssetKey($candidate);
            if (!isset($bestByAsset[$assetKey])) {
                $bestByAsset[$assetKey] = $candidate;
            } else {
                $currentBest = $bestByAsset[$assetKey];
                if ($candidate->acquisitionCostCopper < $currentBest->acquisitionCostCopper) {
                    $bestByAsset[$assetKey] = $candidate;
                }
            }
        }

        return array_values($bestByAsset);
    }

    /**
     * @param T $candidate
     */
    private function extractAssetKey(mixed $candidate): int|string
    {
        if ($candidate instanceof CommodityOpportunity) {
            return $candidate->itemId;
        }

        return $candidate->identity->getFingerprint();
    }
}

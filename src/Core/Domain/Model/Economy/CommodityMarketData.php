<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Economy;

use Traversable;
use UncannyWoW\Core\Domain\Enum\Region;

/**
 * Immutable collection of aggregated commodity market summaries for a region.
 *
 * @implements \IteratorAggregate<int, CommodityMarketSummary>
 */
final readonly class CommodityMarketData implements \Countable, \IteratorAggregate
{
    /**
     * @param Region $region
     * @param array<int, CommodityMarketSummary> $summaries Map of itemId => CommodityMarketSummary
     * @param int $totalAuctions
     * @param int $totalQuantity
     */
    public function __construct(
        public Region $region,
        private array $summaries,
        public int $totalAuctions,
        public int $totalQuantity,
    ) {}

    public function get(int $itemId): ?CommodityMarketSummary
    {
        return $this->summaries[$itemId] ?? null;
    }

    public function has(int $itemId): bool
    {
        return isset($this->summaries[$itemId]);
    }

    /**
     * @return array<int, CommodityMarketSummary>
     */
    public function all(): array
    {
        return $this->summaries;
    }

    public function count(): int
    {
        return count($this->summaries);
    }

    public function uniqueItemCount(): int
    {
        return count($this->summaries);
    }

    /**
     * @return Traversable<int, CommodityMarketSummary>
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->summaries);
    }
}

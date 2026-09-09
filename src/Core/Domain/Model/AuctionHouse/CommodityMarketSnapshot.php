<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\AuctionHouse;

use Traversable;
use UncannyWoW\Core\Domain\Enum\Region;

/**
 * Snapshot of regional commodity auctions.
 *
 * Implements IteratorAggregate so consumers can directly foreach the snapshot:
 * foreach ($snapshot as $commodity) { ... }
 * or access the underlying iterable via $snapshot->auctions.
 *
 * @implements \IteratorAggregate<int, CommodityAuction>
 */
final class CommodityMarketSnapshot implements \IteratorAggregate
{
    /**
     * @var iterable<int, CommodityAuction>
     */
    public readonly iterable $auctions;

    private bool $isConsumed = false;

    /**
     * @param Region $region
     * @param iterable<int, CommodityAuction> $auctions
     */
    public function __construct(
        public readonly Region $region,
        iterable $auctions,
    ) {
        $this->auctions = $auctions;
    }

    /**
     * @return Traversable<int, CommodityAuction>
     */
    public function getIterator(): Traversable
    {
        if ($this->isConsumed) {
            throw new \LogicException('This auction snapshot has already been consumed.');
        }

        $this->isConsumed = true;

        if ($this->auctions instanceof Traversable) {
            return $this->auctions;
        }

        return new \ArrayIterator($this->auctions);
    }
}

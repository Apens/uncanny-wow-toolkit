<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\AuctionHouse;

use Traversable;

/**
 * Snapshot of connected-realm non-commodity auctions.
 *
 * Implements IteratorAggregate so consumers can directly foreach the snapshot:
 * foreach ($snapshot as $auction) { ... }
 * or access the underlying iterable via $snapshot->auctions.
 *
 * @implements \IteratorAggregate<int, Auction>
 */
final class AuctionHouseSnapshot implements \IteratorAggregate
{
    /**
     * @var iterable<int, Auction>
     */
    public readonly iterable $auctions;

    private bool $isConsumed = false;

    /**
     * @param int $connectedRealmId
     * @param iterable<int, Auction> $auctions
     */
    public function __construct(
        public readonly int $connectedRealmId,
        iterable $auctions,
    ) {
        $this->auctions = $auctions;
    }

    /**
     * @return Traversable<int, Auction>
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

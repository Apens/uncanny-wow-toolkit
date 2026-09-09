<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Economy;

use Traversable;

/**
 * Immutable collection of aggregated non-commodity market summaries for a connected realm.
 *
 * @implements \IteratorAggregate<string, AuctionItemMarketSummary>
 */
final readonly class ConnectedRealmMarketData implements \Countable, \IteratorAggregate
{
    /**
     * @param int $connectedRealmId
     * @param array<string, AuctionItemMarketSummary> $summaries Map of identityFingerprint => AuctionItemMarketSummary
     * @param int $totalAuctions
     * @param int $totalQuantity
     */
    public function __construct(
        public int $connectedRealmId,
        private array $summaries,
        public int $totalAuctions,
        public int $totalQuantity,
    ) {}

    public function get(MarketItemIdentity|string $identity): ?AuctionItemMarketSummary
    {
        $fingerprint = $identity instanceof MarketItemIdentity ? $identity->getFingerprint() : $identity;
        return $this->summaries[$fingerprint] ?? null;
    }

    /**
     * Retrieve all variant market summaries matching a base Blizzard Item ID.
     *
     * @return list<AuctionItemMarketSummary>
     */
    public function getByItemId(int $itemId): array
    {
        $matches = [];
        foreach ($this->summaries as $summary) {
            if ($summary->item->id === $itemId) {
                $matches[] = $summary;
            }
        }
        return $matches;
    }

    public function has(MarketItemIdentity|string $identity): bool
    {
        $fingerprint = $identity instanceof MarketItemIdentity ? $identity->getFingerprint() : $identity;
        return isset($this->summaries[$fingerprint]);
    }

    /**
     * @return array<string, AuctionItemMarketSummary>
     */
    public function all(): array
    {
        return $this->summaries;
    }

    public function count(): int
    {
        return count($this->summaries);
    }

    public function uniqueVariantCount(): int
    {
        return count($this->summaries);
    }

    /**
     * @return Traversable<string, AuctionItemMarketSummary>
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->summaries);
    }
}

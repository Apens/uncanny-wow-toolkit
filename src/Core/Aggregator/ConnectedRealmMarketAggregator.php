<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Aggregator;

use UncannyWoW\Core\Domain\Model\AuctionHouse\Auction;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItem;
use UncannyWoW\Core\Domain\Model\Economy\AuctionItemMarketSummary;
use UncannyWoW\Core\Domain\Model\Economy\ConnectedRealmMarketData;
use UncannyWoW\Core\Domain\Model\Economy\MarketItemIdentity;
use UncannyWoW\Core\Domain\Model\Economy\NonCommodityPriceLevel;

/**
 * Pure domain aggregator that summarizes connected-realm non-commodity auctions in a single streaming pass.
 */
final class ConnectedRealmMarketAggregator
{
    /**
     * @param iterable<int, Auction> $auctions
     * @param int $connectedRealmId
     * @param int $maxPriceLevels Maximum distinct lowest buyout price levels to retain per item variant (must be positive).
     */
    public function aggregate(iterable $auctions, int $connectedRealmId, int $maxPriceLevels = 5): ConnectedRealmMarketData
    {
        if ($connectedRealmId <= 0) {
            throw new \InvalidArgumentException(sprintf('Connected realm ID must be a positive integer, got %d.', $connectedRealmId));
        }

        if ($maxPriceLevels < 1 || $maxPriceLevels > 10) {
            throw new \InvalidArgumentException(sprintf('Max price levels must be between 1 and 10, got %d.', $maxPriceLevels));
        }

        /**
         * @var array<string, array{
         *     item: AuctionItem,
         *     identity: MarketItemIdentity,
         *     listingCount: int,
         *     totalQuantity: int,
         *     buyoutListingCount: int,
         *     bidOnlyListingCount: int,
         *     lowestBuyout: ?int,
         *     quantityAtLowestBuyout: int,
         *     lowestBid: ?int,
         *     priceLevels: array<string, array{buyoutCopper: int, quantityPerListing: int, listingCount: int, totalQuantity: int}>
         * }> $items
         */
        $items = [];
        $totalAuctions = 0;
        $totalQuantity = 0;

        foreach ($auctions as $auction) {
            $identity = MarketItemIdentity::fromAuctionItem($auction->item);
            $fingerprint = $identity->getFingerprint();
            $quantity = $auction->quantity;

            $totalAuctions++;
            if ($totalQuantity > \PHP_INT_MAX - $quantity) {
                throw new \OverflowException('Total market quantity exceeded PHP_INT_MAX.');
            }
            $totalQuantity += $quantity;

            if (!isset($items[$fingerprint])) {
                $items[$fingerprint] = [
                    'item' => $auction->item,
                    'identity' => $identity,
                    'listingCount' => 0,
                    'totalQuantity' => 0,
                    'buyoutListingCount' => 0,
                    'bidOnlyListingCount' => 0,
                    'lowestBuyout' => null,
                    'quantityAtLowestBuyout' => 0,
                    'lowestBid' => null,
                    'priceLevels' => [],
                ];
            }

            $entry = &$items[$fingerprint];
            $entry['listingCount']++;

            if ($entry['totalQuantity'] > \PHP_INT_MAX - $quantity) {
                throw new \OverflowException('Total quantity for item variant exceeded PHP_INT_MAX.');
            }
            $entry['totalQuantity'] += $quantity;

            if ($auction->buyoutCopper !== null) {
                $entry['buyoutListingCount']++;
                $buyout = $auction->buyoutCopper;

                if ($entry['lowestBuyout'] === null || $buyout < $entry['lowestBuyout']) {
                    $entry['lowestBuyout'] = $buyout;
                    $entry['quantityAtLowestBuyout'] = $quantity;
                } elseif ($buyout === $entry['lowestBuyout']) {
                    $entry['quantityAtLowestBuyout'] += $quantity;
                }

                // Update bounded buyout price levels (keyed by composite buyout:quantityPerListing)
                $levelKey = $buyout . ':' . $quantity;
                if (isset($entry['priceLevels'][$levelKey])) {
                    $entry['priceLevels'][$levelKey]['listingCount']++;
                    $entry['priceLevels'][$levelKey]['totalQuantity'] += $quantity;
                } elseif (count($entry['priceLevels']) < $maxPriceLevels) {
                    $entry['priceLevels'][$levelKey] = [
                        'buyoutCopper' => $buyout,
                        'quantityPerListing' => $quantity,
                        'listingCount' => 1,
                        'totalQuantity' => $quantity,
                    ];
                } else {
                    // Evict highest buyout level (tie-break by larger lot size)
                    $highestKey = null;
                    $highestBuyout = -1;
                    $highestLot = -1;
                    foreach ($entry['priceLevels'] as $k => $lvl) {
                        if ($lvl['buyoutCopper'] > $highestBuyout || ($lvl['buyoutCopper'] === $highestBuyout && $lvl['quantityPerListing'] > $highestLot)) {
                            $highestBuyout = $lvl['buyoutCopper'];
                            $highestLot = $lvl['quantityPerListing'];
                            $highestKey = $k;
                        }
                    }

                    if ($highestKey !== null && ($buyout < $highestBuyout || ($buyout === $highestBuyout && $quantity < $highestLot))) {
                        unset($entry['priceLevels'][$highestKey]);
                        $entry['priceLevels'][$levelKey] = [
                            'buyoutCopper' => $buyout,
                            'quantityPerListing' => $quantity,
                            'listingCount' => 1,
                            'totalQuantity' => $quantity,
                        ];
                    }
                }
            } else {
                $entry['bidOnlyListingCount']++;
            }

            if ($auction->bidCopper !== null) {
                $bid = $auction->bidCopper;
                if ($entry['lowestBid'] === null || $bid < $entry['lowestBid']) {
                    $entry['lowestBid'] = $bid;
                }
            }

            unset($entry);
        }

        $summaries = [];
        foreach ($items as $fingerprint => $data) {
            uasort($data['priceLevels'], static function (array $a, array $b): int {
                if ($a['buyoutCopper'] !== $b['buyoutCopper']) {
                    return $a['buyoutCopper'] <=> $b['buyoutCopper'];
                }

                return $a['quantityPerListing'] <=> $b['quantityPerListing'];
            });

            $levels = [];
            foreach ($data['priceLevels'] as $levelData) {
                $levels[] = new NonCommodityPriceLevel(
                    buyoutCopper: $levelData['buyoutCopper'],
                    quantityPerListing: $levelData['quantityPerListing'],
                    listingCount: $levelData['listingCount'],
                    totalQuantity: $levelData['totalQuantity'],
                );
            }

            $summaries[$fingerprint] = new AuctionItemMarketSummary(
                item: $data['item'],
                identity: $data['identity'],
                listingCount: $data['listingCount'],
                totalQuantity: $data['totalQuantity'],
                buyoutListingCount: $data['buyoutListingCount'],
                bidOnlyListingCount: $data['bidOnlyListingCount'],
                lowestBuyoutCopper: $data['lowestBuyout'],
                quantityAtLowestBuyout: $data['quantityAtLowestBuyout'],
                lowestBidCopper: $data['lowestBid'],
                priceLevels: $levels,
            );
        }

        return new ConnectedRealmMarketData(
            connectedRealmId: $connectedRealmId,
            summaries: $summaries,
            totalAuctions: $totalAuctions,
            totalQuantity: $totalQuantity,
        );
    }
}

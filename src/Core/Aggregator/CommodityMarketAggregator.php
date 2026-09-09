<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Aggregator;

use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\AuctionHouse\CommodityAuction;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketData;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketSummary;
use UncannyWoW\Core\Domain\Model\Economy\PriceLevel;

/**
 * Pure domain aggregator that summarizes regional commodity auctions in a single streaming pass.
 */
final class CommodityMarketAggregator
{
    /**
     * @param iterable<int, CommodityAuction> $auctions
     * @param Region $region
     * @param int $maxPriceLevels Maximum distinct lowest price levels to retain per item (must be positive).
     */
    public function aggregate(iterable $auctions, Region $region, int $maxPriceLevels = 5): CommodityMarketData
    {
        if ($maxPriceLevels < 1 || $maxPriceLevels > 10) {
            throw new \InvalidArgumentException(sprintf('Max price levels must be between 1 and 10, got %d.', $maxPriceLevels));
        }

        /**
         * @var array<int, array{
         *     auctionCount: int,
         *     totalQuantity: int,
         *     lowestPrice: int,
         *     quantityAtLowestPrice: int,
         *     highestPrice: int,
         *     priceLevels: array<int, array{quantity: int, listingCount: int}>
         * }> $items
         */
        $items = [];
        $totalAuctions = 0;
        $totalQuantity = 0;

        foreach ($auctions as $auction) {
            $itemId = $auction->itemId;
            $quantity = $auction->quantity;
            $price = $auction->unitPriceCopper;

            $totalAuctions++;
            if ($totalQuantity > \PHP_INT_MAX - $quantity) {
                throw new \OverflowException('Total market quantity exceeded PHP_INT_MAX.');
            }
            $totalQuantity += $quantity;

            if (!isset($items[$itemId])) {
                $items[$itemId] = [
                    'auctionCount' => 1,
                    'totalQuantity' => $quantity,
                    'lowestPrice' => $price,
                    'quantityAtLowestPrice' => $quantity,
                    'highestPrice' => $price,
                    'priceLevels' => [
                        $price => ['quantity' => $quantity, 'listingCount' => 1],
                    ],
                ];
                continue;
            }

            $item = &$items[$itemId];
            $item['auctionCount']++;

            if ($item['totalQuantity'] > \PHP_INT_MAX - $quantity) {
                throw new \OverflowException(sprintf('Total quantity for item %d exceeded PHP_INT_MAX.', $itemId));
            }
            $item['totalQuantity'] += $quantity;

            if ($price < $item['lowestPrice']) {
                $item['lowestPrice'] = $price;
                $item['quantityAtLowestPrice'] = $quantity;
            } elseif ($price === $item['lowestPrice']) {
                $item['quantityAtLowestPrice'] += $quantity;
            }

            if ($price > $item['highestPrice']) {
                $item['highestPrice'] = $price;
            }

            // Update bounded price levels
            if (isset($item['priceLevels'][$price])) {
                $item['priceLevels'][$price]['quantity'] += $quantity;
                $item['priceLevels'][$price]['listingCount']++;
            } elseif (count($item['priceLevels']) < $maxPriceLevels) {
                $item['priceLevels'][$price] = ['quantity' => $quantity, 'listingCount' => 1];
            } else {
                $maxKnownPrice = max(array_keys($item['priceLevels']));
                if ($price < $maxKnownPrice) {
                    unset($item['priceLevels'][$maxKnownPrice]);
                    $item['priceLevels'][$price] = ['quantity' => $quantity, 'listingCount' => 1];
                }
            }
            unset($item);
        }

        $summaries = [];
        foreach ($items as $itemId => $data) {
            ksort($data['priceLevels'], \SORT_NUMERIC);

            $levels = [];
            foreach ($data['priceLevels'] as $price => $levelData) {
                $levels[] = new PriceLevel(
                    priceCopper: $price,
                    quantity: $levelData['quantity'],
                    listingCount: $levelData['listingCount'],
                );
            }

            $summaries[$itemId] = new CommodityMarketSummary(
                itemId: $itemId,
                auctionCount: $data['auctionCount'],
                totalQuantity: $data['totalQuantity'],
                lowestUnitPriceCopper: $data['lowestPrice'],
                quantityAtLowestPrice: $data['quantityAtLowestPrice'],
                highestUnitPriceCopper: $data['highestPrice'],
                priceLevels: $levels,
            );
        }

        return new CommodityMarketData(
            region: $region,
            summaries: $summaries,
            totalAuctions: $totalAuctions,
            totalQuantity: $totalQuantity,
        );
    }
}

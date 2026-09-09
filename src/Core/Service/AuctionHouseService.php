<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Service;

use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Contract\Repository\AuctionHouseRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionHouseSnapshot;
use UncannyWoW\Core\Domain\Model\AuctionHouse\CommodityMarketSnapshot;

class AuctionHouseService
{
    public function __construct(
        private readonly AuctionHouseRepositoryInterface $auctionHouseRepository,
        private readonly ClientConfiguration $config,
    ) {}

    /**
     * Retrieve non-commodity auctions for a connected realm cluster.
     *
     * @param int $connectedRealmId Positive integer connected realm ID.
     * @param Region|null $region Optional override for target region (defaults to client configured region).
     */
    public function auctions(int $connectedRealmId, ?Region $region = null): AuctionHouseSnapshot
    {
        if ($connectedRealmId <= 0) {
            throw new \InvalidArgumentException(sprintf('Connected realm ID must be a positive integer, got %d.', $connectedRealmId));
        }

        $targetRegion = $region ?? $this->config->region;

        return $this->auctionHouseRepository->getAuctions($targetRegion, $connectedRealmId);
    }

    /**
     * Retrieve region-wide commodity auctions.
     *
     * @param Region|null $region Optional override for target region (defaults to client configured region).
     */
    public function commodities(?Region $region = null): CommodityMarketSnapshot
    {
        $targetRegion = $region ?? $this->config->region;

        return $this->auctionHouseRepository->getCommodities($targetRegion);
    }
}

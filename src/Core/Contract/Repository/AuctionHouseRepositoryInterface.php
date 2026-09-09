<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Contract\Repository;

use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionHouseSnapshot;
use UncannyWoW\Core\Domain\Model\AuctionHouse\CommodityMarketSnapshot;

interface AuctionHouseRepositoryInterface
{
    public function getAuctions(Region $region, int $connectedRealmId): AuctionHouseSnapshot;

    public function getCommodities(Region $region): CommodityMarketSnapshot;
}

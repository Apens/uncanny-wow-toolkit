<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\AuctionHouse;

use UncannyWoW\Core\Domain\Enum\AuctionTimeLeft;

final readonly class CommodityAuction
{
    public function __construct(
        public int $id,
        public int $itemId,
        public int $quantity,
        public int $unitPriceCopper,
        public AuctionTimeLeft $timeLeft = AuctionTimeLeft::SHORT,
    ) {}
}

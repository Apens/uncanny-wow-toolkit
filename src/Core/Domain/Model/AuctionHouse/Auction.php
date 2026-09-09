<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\AuctionHouse;

use UncannyWoW\Core\Domain\Enum\AuctionTimeLeft;

final readonly class Auction
{
    public function __construct(
        public int $id,
        public AuctionItem $item,
        public int $quantity,
        public ?int $buyoutCopper = null,
        public ?int $bidCopper = null,
        public AuctionTimeLeft $timeLeft = AuctionTimeLeft::SHORT,
    ) {}
}

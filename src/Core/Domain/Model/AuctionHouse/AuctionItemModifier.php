<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\AuctionHouse;

final readonly class AuctionItemModifier
{
    public function __construct(
        public int $type,
        public int $value,
    ) {}
}

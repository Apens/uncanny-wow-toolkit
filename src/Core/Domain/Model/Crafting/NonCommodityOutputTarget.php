<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Crafting;

use UncannyWoW\Core\Domain\Model\Economy\MarketItemIdentity;

final readonly class NonCommodityOutputTarget extends CraftOutputTarget
{
    public function __construct(
        public MarketItemIdentity $identity,
    ) {}

    public function isCommodity(): bool
    {
        return false;
    }
}

<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Crafting;

enum ReagentPricingStatus: string
{
    case Priced = 'PRICED';
    case CustomPriced = 'CUSTOM_PRICED';
    case MarketDataRequired = 'MARKET_DATA_REQUIRED';
    case MarketUnavailable = 'MARKET_UNAVAILABLE';
    case InsufficientMarketDepth = 'INSUFFICIENT_MARKET_DEPTH';
}

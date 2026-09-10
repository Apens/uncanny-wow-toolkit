<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Crafting;

enum OutputPriceStatus: string
{
    case PricedFromCurrentAsk = 'PRICED_FROM_CURRENT_ASK';
    case CustomPriced = 'CUSTOM_PRICED';
    case MarketDataRequired = 'MARKET_DATA_REQUIRED';
    case MarketUnavailable = 'MARKET_UNAVAILABLE';
    case AmbiguousNonCommodityLot = 'AMBIGUOUS_NON_COMMODITY_LOT';
}

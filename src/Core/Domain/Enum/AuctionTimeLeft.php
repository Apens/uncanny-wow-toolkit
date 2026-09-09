<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Enum;

enum AuctionTimeLeft: string
{
    case SHORT = 'SHORT';
    case MEDIUM = 'MEDIUM';
    case LONG = 'LONG';
    case VERY_LONG = 'VERY_LONG';
}

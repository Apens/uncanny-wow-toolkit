<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Enum;

enum ItemQuality: string
{
    case POOR = 'poor';
    case COMMON = 'common';
    case UNCOMMON = 'uncommon';
    case RARE = 'rare';
    case EPIC = 'epic';
    case LEGENDARY = 'legendary';
    case ARTIFACT = 'artifact';
    case HEIRLOOM = 'heirloom';
    case WOW_TOKEN = 'wow_token';
}

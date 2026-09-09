<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Enum;

enum Faction: string
{
    case ALLIANCE = 'ALLIANCE';
    case HORDE = 'HORDE';
    case NEUTRAL = 'NEUTRAL';
}

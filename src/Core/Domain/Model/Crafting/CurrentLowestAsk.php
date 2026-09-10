<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Crafting;

final readonly class CurrentLowestAsk extends OutputSalePriceAssumption
{
    public function isCustom(): bool
    {
        return false;
    }
}

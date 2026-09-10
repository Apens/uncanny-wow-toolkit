<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Crafting;

abstract readonly class OutputSalePriceAssumption
{
    abstract public function isCustom(): bool;
}

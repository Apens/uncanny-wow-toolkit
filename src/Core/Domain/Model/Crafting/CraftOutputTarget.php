<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Crafting;

abstract readonly class CraftOutputTarget
{
    abstract public function isCommodity(): bool;
}

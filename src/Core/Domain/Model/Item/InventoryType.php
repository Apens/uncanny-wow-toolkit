<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Item;

final readonly class InventoryType
{
    public function __construct(
        public string $type,
        public string $name,
    ) {}
}

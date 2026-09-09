<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Item;

use UncannyWoW\Core\Domain\Enum\ItemQuality;

final readonly class Item
{
    public function __construct(
        public int $id,
        public string $name,
        public ItemQuality $quality,
        public int $level,
        public int $requiredLevel,
        public ?ItemClass $itemClass = null,
        public ?ItemSubclass $itemSubclass = null,
        public ?InventoryType $inventoryType = null,
        public int $maxCount = 1,
        public bool $isEquippable = false,
    ) {}
}

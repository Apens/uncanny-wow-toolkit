<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Item;

final readonly class ItemSubclass
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}

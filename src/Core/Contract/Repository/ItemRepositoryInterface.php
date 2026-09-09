<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Contract\Repository;

use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Item\Item;

interface ItemRepositoryInterface
{
    public function getById(Region $region, int $id, Locale $locale): Item;
}
